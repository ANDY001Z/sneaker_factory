<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Validator;
use App\Repositories\OrderRepository;
use App\Repositories\VariantRepository;
use Config\Database;
use PDO;
use Throwable;

final class OrderService
{
    private const ALLOWED_STATUSES = ['pendiente', 'confirmado', 'preparacion', 'enviado', 'cancelado'];

    /** Transiciones válidas de estado para personal interno */
    private const ALLOWED_TRANSITIONS = [
        'pendiente'   => ['confirmado', 'cancelado'],
        'confirmado'  => ['preparacion', 'cancelado'],
        'preparacion' => ['enviado', 'cancelado'],
        'enviado'     => [],
        'cancelado'   => [],
    ];

    /** Estados en los que un cliente aún puede cancelar su propio pedido */
    private const CLIENT_CANCELLABLE_STATUSES = ['pendiente', 'confirmado'];

    private PDO $pdo;
    private OrderRepository $orders;
    private VariantRepository $variants;
    private AuditService $audit;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->orders = new OrderRepository();
        $this->variants = new VariantRepository();
        $this->audit = new AuditService();
    }

    public function listOwnOrders(int $userId, array $query): array
    {
        $filters = $this->parseListFilters($query);
        $filters['usuario_id'] = $userId;

        return $this->executePaginatedSearch($filters, $query);
    }

    public function listAllOrders(array $query): array
    {
        $filters = $this->parseListFilters($query);

        if (isset($query['usuario_id']) && $query['usuario_id'] !== '') {
            if (filter_var($query['usuario_id'], FILTER_VALIDATE_INT) === false || (int) $query['usuario_id'] <= 0) {
                throw new HttpException(400, 'El filtro usuario_id debe ser un entero positivo.', 'INVALID_FILTER');
            }
            $filters['usuario_id'] = (int) $query['usuario_id'];
        }

        return $this->executePaginatedSearch($filters, $query);
    }

    public function getOrderDetail(int $orderId, array $actor, string $clientIp): array
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new HttpException(404, 'Pedido no encontrado.', 'ORDER_NOT_FOUND');
        }

        $actorId = (int) $actor['id'];
        $actorRole = (string) $actor['rol'];

        if ($actorRole === 'cliente' && $order->usuarioId !== $actorId) {
            $this->audit->record(
                $actorId,
                'ACCESO_PEDIDO_AJENO',
                'pedido',
                $orderId,
                'DENEGADO',
                ['propietario_id' => $order->usuarioId],
                $clientIp
            );
            throw new HttpException(403, 'No tienes permiso para consultar este pedido.', 'FORBIDDEN');
        }

        return $order->toArray(true);
    }

    public function createOrder(int $userId, array $input, string $clientIp): array
    {
        $validator = new Validator();
        $direccionEnvio = $validator->requiredString($input, 'direccion_envio', 5, 255);
        $notas = $validator->optionalString($input, 'notas', 1000);

        $rawLines = $input['lineas'] ?? null;
        if (!is_array($rawLines) || $rawLines === []) {
            $validator->addError('lineas', 'El pedido debe incluir al menos una línea.');
        } elseif (count($rawLines) > 50) {
            $validator->addError('lineas', 'Un pedido no puede superar las 50 líneas.');
        }

        $parsedLines = [];
        if (is_array($rawLines) && $rawLines !== [] && count($rawLines) <= 50) {
            foreach ($rawLines as $index => $lineInput) {
                if (!is_array($lineInput)) {
                    $validator->addError("lineas.{$index}", 'Cada línea debe ser un objeto válido.');
                } else {
                    $lineValidator = new Validator();
                    $variantId = $lineValidator->positiveInt($lineInput, 'variante_id', 1000000);
                    $cantidad = $lineValidator->positiveInt($lineInput, 'cantidad', 50);

                    $customOptions = null;
                    if (array_key_exists('opciones_personalizacion', $lineInput) && $lineInput['opciones_personalizacion'] !== null) {
                        if (!is_array($lineInput['opciones_personalizacion']) || $lineInput['opciones_personalizacion'] === []) {
                            $lineValidator->addError('opciones_personalizacion', 'Las opciones de personalización deben ser un objeto JSON no vacío.');
                        } elseif (count($lineInput['opciones_personalizacion']) > 10) {
                            $lineValidator->addError('opciones_personalizacion', 'Demasiadas opciones de personalización (máximo 10).');
                        } else {
                            $customOptions = $this->sanitizeCustomOptions($lineInput['opciones_personalizacion']);
                        }
                    }

                    try {
                        $lineValidator->failIfErrors();
                        $parsedLines[] = [
                            'variante_id'              => $variantId,
                            'cantidad'                 => $cantidad,
                            'opciones_personalizacion' => $customOptions,
                        ];
                    } catch (HttpException $e) {
                        $validator->addError("lineas.{$index}", implode(' ', array_values($e->getDetails())));
                    }
                }
            }
        }

        $validator->failIfErrors();

        // Ordenar IDs de variantes ascendentemente para evitar interbloqueos (deadlocks) en concurrencia
        $uniqueVariantIds = array_values(array_unique(array_column($parsedLines, 'variante_id')));
        sort($uniqueVariantIds, SORT_NUMERIC);

        $orderId = 0;
        $orderCode = 'SF-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $importeTotal = 0.0;
        $esPersonalizado = false;

        try {
            $this->pdo->beginTransaction();

            $lockedVariants = [];
            $remainingStock = [];

            foreach ($uniqueVariantIds as $variantId) {
                $variantRow = $this->variants->findByIdForUpdate($variantId);
                if ($variantRow === null) {
                    throw new HttpException(404, "La variante con ID {$variantId} no existe.", 'VARIANT_NOT_FOUND');
                }
                if (!(bool) $variantRow['modelo_activo']) {
                    throw new HttpException(409, "El modelo '{$variantRow['modelo_nombre']}' no está disponible actualmente.", 'MODEL_INACTIVE');
                }

                $lockedVariants[$variantId] = $variantRow;
                $remainingStock[$variantId] = (int) $variantRow['stock'];
            }

            $preparedLines = [];
            foreach ($parsedLines as $line) {
                $variantId = $line['variante_id'];
                $cantidad = $line['cantidad'];
                $variantRow = $lockedVariants[$variantId];

                if ($line['opciones_personalizacion'] !== null) {
                    if (!(bool) $variantRow['personalizable']) {
                        throw new HttpException(
                            400,
                            "El modelo '{$variantRow['modelo_nombre']}' no admite personalización.",
                            'MODEL_NOT_CUSTOMIZABLE'
                        );
                    }
                    $esPersonalizado = true;
                }

                if ($remainingStock[$variantId] < $cantidad) {
                    throw new HttpException(
                        409,
                        "Stock insuficiente para '{$variantRow['modelo_nombre']}' (talla {$variantRow['talla']}, {$variantRow['color']}).",
                        'INSUFFICIENT_STOCK',
                        [
                            'variante_id'      => $variantId,
                            'stock_disponible' => $remainingStock[$variantId],
                            'solicitado'       => $cantidad,
                        ]
                    );
                }

                $remainingStock[$variantId] -= $cantidad;
                $precioUnitario = round((float) $variantRow['precio_base'], 2);
                $importeTotal = round($importeTotal + ($precioUnitario * $cantidad), 2);

                $preparedLines[] = [
                    'variante_id'              => $variantId,
                    'cantidad'                 => $cantidad,
                    'precio_unitario'          => $precioUnitario,
                    'opciones_personalizacion' => $line['opciones_personalizacion'],
                ];
            }

            $orderId = $this->orders->createOrder([
                'codigo_pedido'    => $orderCode,
                'usuario_id'       => $userId,
                'estado'           => 'pendiente',
                'es_personalizado' => $esPersonalizado,
                'importe_total'    => $importeTotal,
                'direccion_envio'  => $direccionEnvio,
                'notas'            => $notas,
            ]);

            foreach ($preparedLines as $preparedLine) {
                $this->orders->createOrderLine([
                    'pedido_id'                => $orderId,
                    'variante_id'              => $preparedLine['variante_id'],
                    'cantidad'                 => $preparedLine['cantidad'],
                    'precio_unitario'          => $preparedLine['precio_unitario'],
                    'opciones_personalizacion' => $preparedLine['opciones_personalizacion'],
                ]);
                $this->variants->decrementStock($preparedLine['variante_id'], $preparedLine['cantidad']);
            }

            $this->orders->insertStatusHistory(
                $orderId,
                null,
                'pendiente',
                $userId,
                $esPersonalizado ? 'Pedido personalizado creado por el cliente' : 'Pedido creado por el cliente'
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->audit->record(
            $userId,
            'ALTA_PEDIDO',
            'pedido',
            $orderId,
            'OK',
            [
                'codigo_pedido'    => $orderCode,
                'importe_total'    => $importeTotal,
                'es_personalizado' => $esPersonalizado,
                'num_lineas'       => count($preparedLines),
            ],
            $clientIp
        );

        $createdOrder = $this->orders->findById($orderId);
        if ($createdOrder === null) {
            throw new \RuntimeException('No se pudo recuperar el pedido recién creado.');
        }

        return $createdOrder->toArray(true);
    }

    public function cancelOwnOrder(int $orderId, array $actor, array $input, string $clientIp): array
    {
        $actorId = (int) $actor['id'];
        $validator = new Validator();
        $motivo = $validator->optionalString($input, 'motivo', 255) ?? 'Cancelado por el cliente';
        $validator->failIfErrors();

        $previousStatus = '';

        try {
            $this->pdo->beginTransaction();

            $lockedOrder = $this->orders->findByIdForUpdate($orderId);
            if ($lockedOrder === null) {
                throw new HttpException(404, 'Pedido no encontrado.', 'ORDER_NOT_FOUND');
            }

            if ((int) $lockedOrder['usuario_id'] !== $actorId) {
                $this->pdo->rollBack();
                $this->audit->record(
                    $actorId,
                    'CANCELACION_PEDIDO',
                    'pedido',
                    $orderId,
                    'DENEGADO',
                    ['motivo_rechazo' => 'Intento de cancelar pedido de otro cliente'],
                    $clientIp
                );
                throw new HttpException(403, 'No puedes cancelar un pedido que pertenece a otro cliente.', 'FORBIDDEN');
            }

            $previousStatus = (string) $lockedOrder['estado'];
            if (!in_array($previousStatus, self::CLIENT_CANCELLABLE_STATUSES, true)) {
                throw new HttpException(
                    409,
                    "No se puede cancelar un pedido en estado '{$previousStatus}'.",
                    'INVALID_STATE_TRANSITION'
                );
            }

            $this->restoreStockForOrder($orderId);
            $this->orders->updateStatus($orderId, 'cancelado');
            $this->orders->insertStatusHistory($orderId, $previousStatus, 'cancelado', $actorId, $motivo);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->audit->record(
            $actorId,
            'CANCELACION_PEDIDO',
            'pedido',
            $orderId,
            'OK',
            ['estado_anterior' => $previousStatus, 'motivo' => $motivo],
            $clientIp
        );

        $updatedOrder = $this->orders->findById($orderId);
        if ($updatedOrder === null) {
            throw new \RuntimeException('No se pudo recuperar el pedido cancelado.');
        }

        return $updatedOrder->toArray(true);
    }

    public function changeOrderStatus(int $orderId, array $actor, array $input, string $clientIp): array
    {
        $actorId = (int) $actor['id'];

        $validator = new Validator();
        $newStatus = $validator->inList($input, 'estado', self::ALLOWED_STATUSES);
        $comentario = $validator->optionalString($input, 'comentario', 255);
        $validator->failIfErrors();

        $previousStatus = '';

        try {
            $this->pdo->beginTransaction();

            $lockedOrder = $this->orders->findByIdForUpdate($orderId);
            if ($lockedOrder === null) {
                throw new HttpException(404, 'Pedido no encontrado.', 'ORDER_NOT_FOUND');
            }

            $previousStatus = (string) $lockedOrder['estado'];
            $allowedNext = self::ALLOWED_TRANSITIONS[$previousStatus] ?? [];

            if (!in_array($newStatus, $allowedNext, true)) {
                throw new HttpException(
                    409,
                    "Transición no permitida: no se puede pasar de '{$previousStatus}' a '{$newStatus}'.",
                    'INVALID_STATE_TRANSITION',
                    ['estado_actual' => $previousStatus, 'transiciones_validas' => $allowedNext]
                );
            }

            if ($newStatus === 'cancelado') {
                $this->restoreStockForOrder($orderId);
            }

            $this->orders->updateStatus($orderId, $newStatus);
            $this->orders->insertStatusHistory(
                $orderId,
                $previousStatus,
                $newStatus,
                $actorId,
                $comentario ?? "Estado actualizado a {$newStatus}"
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $eventType = $newStatus === 'cancelado' ? 'CANCELACION_PEDIDO' : 'CAMBIO_ESTADO';
        $this->audit->record(
            $actorId,
            $eventType,
            'pedido',
            $orderId,
            'OK',
            ['de' => $previousStatus, 'a' => $newStatus, 'comentario' => $comentario],
            $clientIp
        );

        $updatedOrder = $this->orders->findById($orderId);
        if ($updatedOrder === null) {
            throw new \RuntimeException('No se pudo recuperar el pedido actualizado.');
        }

        return $updatedOrder->toArray(true);
    }

    private function restoreStockForOrder(int $orderId): void
    {
        $itemsToRestore = $this->orders->findVariantQuantitiesByOrder($orderId);

        foreach ($itemsToRestore as $item) {
            $this->variants->findByIdForUpdate($item['variante_id']);
            $this->variants->incrementStock($item['variante_id'], $item['cantidad']);
        }
    }

    private function parseListFilters(array $query): array
    {
        $filters = [];

        if (isset($query['estado']) && is_string($query['estado']) && trim($query['estado']) !== '') {
            $estado = strtolower(trim($query['estado']));
            if (!in_array($estado, self::ALLOWED_STATUSES, true)) {
                throw new HttpException(400, 'Estado de filtro no válido.', 'INVALID_FILTER');
            }
            $filters['estado'] = $estado;
        }

        if (isset($query['es_personalizado']) && $query['es_personalizado'] !== '') {
            $parsed = filter_var($query['es_personalizado'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed === null) {
                throw new HttpException(400, 'El filtro es_personalizado debe ser true o false.', 'INVALID_FILTER');
            }
            $filters['es_personalizado'] = $parsed;
        }

        if (isset($query['q']) && is_string($query['q']) && trim($query['q']) !== '') {
            $filters['q'] = trim($query['q']);
        }

        return $filters;
    }

    private function executePaginatedSearch(array $filters, array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(1, min(50, (int) ($query['limit'] ?? 10)));

        $result = $this->orders->search($filters, $page, $limit);
        $items = [];
        foreach ($result['items'] as $order) {
            $items[] = $order->toArray(false);
        }

        $total = $result['total'];
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $items,
            'meta'  => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    private function sanitizeCustomOptions(array $rawOptions): array
    {
        $clean = [];

        foreach ($rawOptions as $key => $value) {
            $cleanKey = substr(trim((string) $key), 0, 50);
            if ($cleanKey !== '') {
                if (is_bool($value) || is_int($value) || is_float($value)) {
                    $clean[$cleanKey] = $value;
                } elseif (is_string($value)) {
                    $clean[$cleanKey] = mb_substr(trim($value), 0, 100);
                }
            }
        }

        return $clean;
    }
}