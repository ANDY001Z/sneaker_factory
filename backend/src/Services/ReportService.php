<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Validator;
use App\Repositories\ReportRepository;
use App\Repositories\VariantRepository;
use Config\Database;
use DateTimeImmutable;
use PDO;
use Throwable;

final class ReportService
{
    private const ALLOWED_STATUSES = ['pendiente', 'confirmado', 'preparacion', 'enviado', 'cancelado'];
    private const ALLOWED_EXPORT_FORMATS = ['json', 'csv'];

    private PDO $pdo;
    private ReportRepository $reports;
    private VariantRepository $variants;
    private AuditService $audit;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->reports = new ReportRepository();
        $this->variants = new VariantRepository();
        $this->audit = new AuditService();
    }

    public function getDashboardKpis(array $query): array
    {
        $lowStockLimit = (int) ($query['limite_stock'] ?? 5);
        return $this->reports->getDashboardSummary($lowStockLimit);
    }

    public function getSalesByModel(array $query): array
    {
        $desde = $this->parseOptionalDate($query['desde'] ?? null, 'desde');
        $hasta = $this->parseOptionalDate($query['hasta'] ?? null, 'hasta');

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw new HttpException(400, 'La fecha "desde" no puede ser posterior a "hasta".', 'INVALID_DATE_RANGE');
        }

        return [
            'filtros' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'items' => $this->reports->getSalesByModelReport($desde, $hasta),
        ];
    }

    public function getTopCustomers(array $query): array
    {
        $limit = (int) ($query['limit'] ?? 10);

        return [
            'limit' => max(1, min($limit, 50)),
            'items' => $this->reports->getTopCustomersReport($limit),
        ];
    }

    public function exportOrders(array $query, int $actorId, string $clientIp): array
    {
        $formato = strtolower(trim((string) ($query['formato'] ?? 'json')));
        if (!in_array($formato, self::ALLOWED_EXPORT_FORMATS, true)) {
            throw new HttpException(400, 'Formato de exportación no válido. Usa "csv" o "json".', 'INVALID_FORMAT');
        }

        $filters = [];
        if (isset($query['estado']) && is_string($query['estado']) && trim($query['estado']) !== '') {
            $estado = strtolower(trim($query['estado']));
            if (!in_array($estado, self::ALLOWED_STATUSES, true)) {
                throw new HttpException(400, 'Estado de filtro no válido.', 'INVALID_FILTER');
            }
            $filters['estado'] = $estado;
        }

        $desde = $this->parseOptionalDate($query['desde'] ?? null, 'desde');
        $hasta = $this->parseOptionalDate($query['hasta'] ?? null, 'hasta');

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw new HttpException(400, 'La fecha "desde" no puede ser posterior a "hasta".', 'INVALID_DATE_RANGE');
        }

        if ($desde !== null) {
            $filters['desde'] = $desde;
        }
        if ($hasta !== null) {
            $filters['hasta'] = $hasta;
        }

        $orders = $this->reports->getOrdersForExport($filters);
        $timestamp = date('Ymd_His');

        $this->audit->record(
            $actorId,
            'EXPORTACION_PEDIDOS',
            'pedido',
            null,
            'OK',
            [
                'formato'   => $formato,
                'registros' => count($orders),
                'filtros'   => $filters,
            ],
            $clientIp
        );

        if ($formato === 'csv') {
            $headers = [
                'ID',
                'Codigo',
                'Fecha',
                'Cliente',
                'Email',
                'Estado',
                'Personalizado',
                'Lineas',
                'Unidades',
                'Importe_Total',
                'Direccion_Envio',
            ];

            $rows = [];
            foreach ($orders as $order) {
                $rows[] = [
                    $order['id'],
                    $order['codigo_pedido'],
                    $order['fecha'],
                    $order['cliente'],
                    $order['cliente_email'],
                    $order['estado'],
                    $order['es_personalizado'] ? 'SI' : 'NO',
                    $order['num_lineas'],
                    $order['total_unidades'],
                    number_format($order['importe_total'], 2, '.', ''),
                    $order['direccion_envio'],
                ];
            }

            return [
                'formato'  => 'csv',
                'filename' => "pedidos_sneaker_factory_{$timestamp}.csv",
                'headers'  => $headers,
                'rows'     => $rows,
            ];
        }

        return [
            'formato'  => 'json',
            'filename' => "pedidos_sneaker_factory_{$timestamp}.json",
            'payload'  => [
                'generado_en'     => date(DATE_ATOM),
                'total_registros' => count($orders),
                'filtros'         => $filters,
                'pedidos'         => $orders,
            ],
        ];
    }

    /** Importación masiva validada de stock por SKU + talla + color (transacción todo o nada) */
    public function importStock(array $input, int $actorId, string $clientIp): array
    {
        $rawItems = $input['items'] ?? null;

        if (!is_array($rawItems) || $rawItems === []) {
            throw new HttpException(400, 'Debes enviar una lista no vacía en el campo "items".', 'VALIDATION_ERROR');
        }

        if (count($rawItems) > 200) {
            throw new HttpException(400, 'El lote de importación no puede superar los 200 registros.', 'VALIDATION_ERROR');
        }

        $validatedRows = [];
        $rowErrors = [];

        foreach ($rawItems as $index => $item) {
            $rowNumber = $index + 1;

            if (!is_array($item)) {
                $rowErrors["fila_{$rowNumber}"] = 'El registro debe ser un objeto JSON.';
            } else {
                $validator = new Validator();
                $sku = strtoupper($validator->requiredString($item, 'sku', 3, 30));
                $talla = $validator->decimal($item, 'talla', 34.0, 50.0);
                $color = $validator->requiredString($item, 'color', 2, 50);
                $stock = $validator->nonNegativeInt($item, 'stock', 50000);
                $stockMinimo = array_key_exists('stock_minimo', $item) && $item['stock_minimo'] !== null
                    ? $validator->nonNegativeInt($item, 'stock_minimo', 5000)
                    : null;

                try {
                    $validator->failIfErrors();
                    $validatedRows[] = [
                        'fila'         => $rowNumber,
                        'sku'          => $sku,
                        'talla'        => $talla,
                        'color'        => $color,
                        'stock'        => $stock,
                        'stock_minimo' => $stockMinimo,
                    ];
                } catch (HttpException $e) {
                    $rowErrors["fila_{$rowNumber}"] = implode(' ', array_values($e->getDetails()));
                }
            }
        }

        if ($rowErrors !== []) {
            throw new HttpException(
                400,
                'La importación contiene filas con errores de validación. No se ha aplicado ningún cambio.',
                'IMPORT_VALIDATION_ERROR',
                $rowErrors
            );
        }

        $updatedSummary = [];

        try {
            $this->pdo->beginTransaction();

            foreach ($validatedRows as $row) {
                $variantRow = $this->reports->findVariantBySkuTallaColorForUpdate(
                    $row['sku'],
                    $row['talla'],
                    $row['color']
                );

                if ($variantRow === null) {
                    throw new HttpException(
                        404,
                        "Fila {$row['fila']}: no existe variante para SKU '{$row['sku']}', talla {$row['talla']} y color '{$row['color']}'.",
                        'VARIANT_NOT_FOUND'
                    );
                }

                $variantId = (int) $variantRow['id'];
                $stockAnterior = (int) $variantRow['stock'];
                $updated = $this->variants->updateStock($variantId, $row['stock'], $row['stock_minimo']);

                $updatedSummary[] = [
                    'variante_id'    => $variantId,
                    'sku'            => $row['sku'],
                    'talla'          => $row['talla'],
                    'color'          => $row['color'],
                    'stock_anterior' => $stockAnterior,
                    'stock_nuevo'    => $updated->stock,
                ];
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->audit->record(
            $actorId,
            'IMPORTACION_STOCK',
            'variante_modelo',
            null,
            'OK',
            [
                'total_actualizadas' => count($updatedSummary),
                'skus'               => array_values(array_unique(array_column($updatedSummary, 'sku'))),
            ],
            $clientIp
        );

        return [
            'procesados'    => count($updatedSummary),
            'actualizados'  => $updatedSummary,
        ];
    }

    private function parseOptionalDate(mixed $raw, string $field): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_string($raw)) {
            throw new HttpException(400, "El parámetro '{$field}' debe tener formato YYYY-MM-DD.", 'INVALID_DATE');
        }

        $clean = trim($raw);
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $clean);
        if ($parsed === false || $parsed->format('Y-m-d') !== $clean) {
            throw new HttpException(400, "El parámetro '{$field}' debe tener formato YYYY-MM-DD.", 'INVALID_DATE');
        }

        return $clean;
    }
}