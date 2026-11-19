<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderLine;
use Config\Database;
use PDO;

final class OrderRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findById(int $id): ?Order
    {
        $sql = "SELECT p.id, p.codigo_pedido, p.usuario_id,
                       (u.nombre || ' ' || u.apellidos) AS usuario_nombre,
                       u.email AS usuario_email,
                       p.estado, p.es_personalizado, p.importe_total,
                       p.direccion_envio, p.notas, p.creado_en, p.actualizado_en
                FROM pedidos p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $linesMap = $this->findLinesByOrderIds([$id]);
        $history = $this->findHistoryByOrderId($id);

        return Order::fromRow($row, $linesMap[$id] ?? [], $history);
    }

    /** Bloquea la fila del pedido dentro de una transacción para evitar cambios simultáneos */
    public function findByIdForUpdate(int $id): ?array
    {
        $sql = 'SELECT id, codigo_pedido, usuario_id, estado, es_personalizado, importe_total
                FROM pedidos
                WHERE id = :id
                FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array{items: array<int, Order>, total: int} */
    public function search(array $filters, int $page, int $limit): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['usuario_id'])) {
            $conditions[] = 'p.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $filters['usuario_id'];
        }

        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $conditions[] = 'p.estado = :estado';
            $params[':estado'] = (string) $filters['estado'];
        }

        if (isset($filters['es_personalizado']) && is_bool($filters['es_personalizado'])) {
            $conditions[] = 'p.es_personalizado = :es_personalizado';
            $params[':es_personalizado'] = $filters['es_personalizado'];
        }

        if (isset($filters['q']) && $filters['q'] !== '') {
            $conditions[] = '(p.codigo_pedido ILIKE :q OR u.email ILIKE :q OR u.nombre ILIKE :q OR u.apellidos ILIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(*)
                     FROM pedidos p
                     INNER JOIN usuarios u ON u.id = p.usuario_id
                     {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $this->bindParams($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        $offset = ($page - 1) * $limit;
        $dataSql = "SELECT p.id, p.codigo_pedido, p.usuario_id,
                           (u.nombre || ' ' || u.apellidos) AS usuario_nombre,
                           u.email AS usuario_email,
                           p.estado, p.es_personalizado, p.importe_total,
                           p.direccion_envio, p.notas, p.creado_en, p.actualizado_en
                    FROM pedidos p
                    INNER JOIN usuarios u ON u.id = p.usuario_id
                    {$whereClause}
                    ORDER BY p.creado_en DESC, p.id DESC
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        $this->bindParams($dataStmt, $params);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        $orderIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $linesMap = $this->findLinesByOrderIds($orderIds);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $items[] = Order::fromRow($row, $linesMap[$id] ?? [], []);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function createOrder(array $data): int
    {
        $sql = 'INSERT INTO pedidos
                    (codigo_pedido, usuario_id, estado, es_personalizado, importe_total, direccion_envio, notas)
                VALUES
                    (:codigo_pedido, :usuario_id, :estado, :es_personalizado, :importe_total, :direccion_envio, :notas)
                RETURNING id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':codigo_pedido', $data['codigo_pedido'], PDO::PARAM_STR);
        $stmt->bindValue(':usuario_id', (int) $data['usuario_id'], PDO::PARAM_INT);
        $stmt->bindValue(':estado', $data['estado'], PDO::PARAM_STR);
        $stmt->bindValue(':es_personalizado', (bool) $data['es_personalizado'], PDO::PARAM_BOOL);
        $stmt->bindValue(':importe_total', $data['importe_total']);
        $stmt->bindValue(':direccion_envio', $data['direccion_envio'], PDO::PARAM_STR);
        $stmt->bindValue(':notas', $data['notas'], $data['notas'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function createOrderLine(array $data): void
    {
        $sql = 'INSERT INTO pedido_lineas
                    (pedido_id, variante_id, cantidad, precio_unitario, opciones_personalizacion)
                VALUES
                    (:pedido_id, :variante_id, :cantidad, :precio_unitario, :opciones::jsonb)';

        $optionsJson = $data['opciones_personalizacion'] !== null
            ? json_encode($data['opciones_personalizacion'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pedido_id', (int) $data['pedido_id'], PDO::PARAM_INT);
        $stmt->bindValue(':variante_id', (int) $data['variante_id'], PDO::PARAM_INT);
        $stmt->bindValue(':cantidad', (int) $data['cantidad'], PDO::PARAM_INT);
        $stmt->bindValue(':precio_unitario', $data['precio_unitario']);
        $stmt->bindValue(':opciones', $optionsJson, $optionsJson !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function updateStatus(int $orderId, string $newStatus): void
    {
        $sql = 'UPDATE pedidos
                SET estado = :estado,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':estado' => $newStatus,
            ':id'     => $orderId,
        ]);
    }

    public function insertStatusHistory(
        int $orderId,
        ?string $previousStatus,
        string $newStatus,
        ?int $userId,
        ?string $comment
    ): void {
        $sql = 'INSERT INTO pedido_historial_estados
                    (pedido_id, estado_anterior, estado_nuevo, usuario_id, comentario)
                VALUES
                    (:pedido_id, :estado_anterior, :estado_nuevo, :usuario_id, :comentario)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pedido_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':estado_anterior', $previousStatus, $previousStatus !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado_nuevo', $newStatus, PDO::PARAM_STR);
        $stmt->bindValue(':usuario_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':comentario', $comment, $comment !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
    }

    /** @return array<int, array{variante_id: int, cantidad: int}> */
    public function findVariantQuantitiesByOrder(int $orderId): array
    {
        $sql = 'SELECT variante_id, SUM(cantidad) AS cantidad
                FROM pedido_lineas
                WHERE pedido_id = :pedido_id
                GROUP BY variante_id
                ORDER BY variante_id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pedido_id' => $orderId]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'variante_id' => (int) $row['variante_id'],
                'cantidad'    => (int) $row['cantidad'],
            ];
        }

        return $result;
    }

    /** @param array<int, int> $orderIds @return array<int, array<int, OrderLine>> */
    private function findLinesByOrderIds(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach (array_values($orderIds) as $index => $orderId) {
            $key = ':o' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        $sql = 'SELECT pl.id, pl.pedido_id, pl.variante_id, v.modelo_id,
                       m.sku AS modelo_sku, m.nombre AS modelo_nombre,
                       v.talla, v.color, pl.cantidad, pl.precio_unitario,
                       pl.subtotal, pl.opciones_personalizacion
                FROM pedido_lineas pl
                INNER JOIN variantes_modelo v ON v.id = pl.variante_id
                INNER JOIN modelos m ON m.id = v.modelo_id
                WHERE pl.pedido_id IN (' . implode(', ', $placeholders) . ')
                ORDER BY pl.pedido_id ASC, pl.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $line = OrderLine::fromRow($row);
            $grouped[$line->pedidoId][] = $line;
        }

        return $grouped;
    }

    /** @return array<int, array<string, mixed>> */
    private function findHistoryByOrderId(int $orderId): array
    {
        $sql = "SELECT h.id, h.estado_anterior, h.estado_nuevo, h.comentario, h.cambiado_en,
                       h.usuario_id,
                       CASE WHEN u.id IS NOT NULL THEN (u.nombre || ' ' || u.apellidos) ELSE NULL END AS usuario_nombre
                FROM pedido_historial_estados h
                LEFT JOIN usuarios u ON u.id = h.usuario_id
                WHERE h.pedido_id = :pedido_id
                ORDER BY h.cambiado_en ASC, h.id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':pedido_id' => $orderId]);
        $rows = $stmt->fetchAll();

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'id'              => (int) $row['id'],
                'estado_anterior' => $row['estado_anterior'],
                'estado_nuevo'    => (string) $row['estado_nuevo'],
                'comentario'      => $row['comentario'],
                'usuario_id'      => $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null,
                'usuario_nombre'  => $row['usuario_nombre'],
                'cambiado_en'     => (string) $row['cambiado_en'],
            ];
        }

        return $history;
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, (string) $value, PDO::PARAM_STR);
            }
        }
    }
}