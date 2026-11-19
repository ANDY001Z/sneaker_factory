<?php

declare(strict_types=1);

namespace App\Repositories;

use Config\Database;
use PDO;

final class ReportRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    /** KPIs del panel resumen: pedidos por estado, unidades vendidas y modelos con menos existencias */
    public function getDashboardSummary(int $lowStockLimit = 5): array
    {
        $safeLimit = max(1, min($lowStockLimit, 20));

        // 1. Pedidos por estado
        $statusSql = 'SELECT estado,
                             COUNT(*) AS total_pedidos,
                             COALESCE(SUM(importe_total), 0)::NUMERIC(10,2) AS importe_acumulado
                      FROM pedidos
                      GROUP BY estado
                      ORDER BY estado ASC';
        $statusRows = $this->pdo->query($statusSql)->fetchAll();

        $pedidosPorEstado = [];
        foreach ($statusRows as $row) {
            $pedidosPorEstado[] = [
                'estado'            => (string) $row['estado'],
                'total_pedidos'     => (int) $row['total_pedidos'],
                'importe_acumulado' => (float) $row['importe_acumulado'],
            ];
        }

        // 2. Unidades vendidas e ingresos (excluyendo pedidos cancelados)
        $salesSql = "SELECT COALESCE(SUM(pl.cantidad), 0) AS unidades_vendidas,
                            COALESCE(SUM(pl.subtotal), 0)::NUMERIC(10,2) AS ingresos_totales,
                            COUNT(DISTINCT p.id) AS pedidos_activos,
                            COUNT(DISTINCT p.id) FILTER (WHERE p.es_personalizado = TRUE) AS pedidos_personalizados
                     FROM pedidos p
                     INNER JOIN pedido_lineas pl ON pl.pedido_id = p.id
                     WHERE p.estado <> 'cancelado'";
        $salesRow = $this->pdo->query($salesSql)->fetch() ?: [];

        // 3. Modelos con menos existencias (agregando stock de todas sus variantes)
        $lowStockSql = 'SELECT m.id, m.sku, m.marca, m.nombre, m.categoria,
                               COALESCE(SUM(v.stock), 0) AS stock_total,
                               COUNT(v.id) FILTER (WHERE v.stock <= v.stock_minimo) AS variantes_en_alerta,
                               COUNT(v.id) FILTER (WHERE v.stock = 0) AS variantes_agotadas
                        FROM modelos m
                        LEFT JOIN variantes_modelo v ON v.modelo_id = m.id
                        WHERE m.activo = TRUE
                        GROUP BY m.id, m.sku, m.marca, m.nombre, m.categoria
                        ORDER BY stock_total ASC, variantes_agotadas DESC, m.id ASC
                        LIMIT :limite';
        $lowStockStmt = $this->pdo->prepare($lowStockSql);
        $lowStockStmt->bindValue(':limite', $safeLimit, PDO::PARAM_INT);
        $lowStockStmt->execute();
        $lowStockRows = $lowStockStmt->fetchAll();

        $modelosMenosExistencias = [];
        foreach ($lowStockRows as $row) {
            $modelosMenosExistencias[] = [
                'id'                  => (int) $row['id'],
                'sku'                 => (string) $row['sku'],
                'marca'               => (string) $row['marca'],
                'nombre'              => (string) $row['nombre'],
                'categoria'           => (string) $row['categoria'],
                'stock_total'         => (int) $row['stock_total'],
                'variantes_en_alerta' => (int) $row['variantes_en_alerta'],
                'variantes_agotadas'  => (int) $row['variantes_agotadas'],
            ];
        }

        return [
            'pedidos_por_estado'        => $pedidosPorEstado,
            'ventas'                    => [
                'unidades_vendidas'      => (int) ($salesRow['unidades_vendidas'] ?? 0),
                'ingresos_totales'       => (float) ($salesRow['ingresos_totales'] ?? 0),
                'pedidos_activos'        => (int) ($salesRow['pedidos_activos'] ?? 0),
                'pedidos_personalizados' => (int) ($salesRow['pedidos_personalizados'] ?? 0),
            ],
            'modelos_menos_existencias' => $modelosMenosExistencias,
        ];
    }

    /** Informe 1 (JOIN + agregación): Rendimiento de ventas y stock actual por modelo */
    public function getSalesByModelReport(?string $desde = null, ?string $hasta = null): array
    {
        $dateConditions = ["p.estado <> 'cancelado'"];
        $params = [];

        if ($desde !== null) {
            $dateConditions[] = 'p.creado_en >= :desde::date';
            $params[':desde'] = $desde;
        }

        if ($hasta !== null) {
            $dateConditions[] = "p.creado_en < (:hasta::date + INTERVAL '1 day')";
            $params[':hasta'] = $hasta;
        }

        $joinFilter = implode(' AND ', $dateConditions);

        $sql = "SELECT m.id,
                       m.sku,
                       m.marca,
                       m.nombre,
                       m.categoria,
                       m.precio_base,
                       COALESCE(stock_agg.stock_actual, 0) AS stock_actual,
                       COUNT(DISTINCT p.id) AS total_pedidos,
                       COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN pl.cantidad ELSE 0 END), 0) AS unidades_vendidas,
                       COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN pl.subtotal ELSE 0 END), 0)::NUMERIC(10,2) AS ingresos_brutos
                FROM modelos m
                LEFT JOIN (
                    SELECT modelo_id, SUM(stock) AS stock_actual
                    FROM variantes_modelo
                    GROUP BY modelo_id
                ) stock_agg ON stock_agg.modelo_id = m.id
                LEFT JOIN variantes_modelo v ON v.modelo_id = m.id
                LEFT JOIN pedido_lineas pl ON pl.variante_id = v.id
                LEFT JOIN pedidos p ON p.id = pl.pedido_id AND {$joinFilter}
                GROUP BY m.id, m.sku, m.marca, m.nombre, m.categoria, m.precio_base, stock_agg.stock_actual
                ORDER BY ingresos_brutos DESC, unidades_vendidas DESC, m.nombre ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $report = [];
        foreach ($rows as $row) {
            $report[] = [
                'modelo_id'         => (int) $row['id'],
                'sku'               => (string) $row['sku'],
                'marca'             => (string) $row['marca'],
                'nombre'            => (string) $row['nombre'],
                'categoria'         => (string) $row['categoria'],
                'precio_base'       => (float) $row['precio_base'],
                'stock_actual'      => (int) $row['stock_actual'],
                'total_pedidos'     => (int) $row['total_pedidos'],
                'unidades_vendidas' => (int) $row['unidades_vendidas'],
                'ingresos_brutos'   => (float) $row['ingresos_brutos'],
            ];
        }

        return $report;
    }

    /** Informe 2 (JOIN + agregación): Actividad y facturación por cliente */
    public function getTopCustomersReport(int $limit = 10): array
    {
        $safeLimit = max(1, min($limit, 50));

        $sql = "SELECT u.id AS usuario_id,
                       (u.nombre || ' ' || u.apellidos) AS cliente,
                       u.email,
                       COUNT(p.id) FILTER (WHERE p.estado <> 'cancelado') AS pedidos_validos,
                       COUNT(p.id) FILTER (WHERE p.estado = 'cancelado') AS pedidos_cancelados,
                       COUNT(p.id) FILTER (WHERE p.estado <> 'cancelado' AND p.es_personalizado = TRUE) AS pedidos_personalizados,
                       COALESCE(SUM(p.importe_total) FILTER (WHERE p.estado <> 'cancelado'), 0)::NUMERIC(10,2) AS gasto_total,
                       COALESCE(AVG(p.importe_total) FILTER (WHERE p.estado <> 'cancelado'), 0)::NUMERIC(10,2) AS ticket_medio,
                       MAX(p.creado_en) AS ultimo_pedido
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                LEFT JOIN pedidos p ON p.usuario_id = u.id
                WHERE r.nombre = 'cliente'
                GROUP BY u.id, u.nombre, u.apellidos, u.email
                ORDER BY gasto_total DESC, pedidos_validos DESC, u.id ASC
                LIMIT :limite";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limite', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $report = [];
        foreach ($rows as $row) {
            $report[] = [
                'usuario_id'             => (int) $row['usuario_id'],
                'cliente'                => (string) $row['cliente'],
                'email'                  => (string) $row['email'],
                'pedidos_validos'        => (int) $row['pedidos_validos'],
                'pedidos_cancelados'     => (int) $row['pedidos_cancelados'],
                'pedidos_personalizados' => (int) $row['pedidos_personalizados'],
                'gasto_total'            => (float) $row['gasto_total'],
                'ticket_medio'           => (float) $row['ticket_medio'],
                'ultimo_pedido'          => $row['ultimo_pedido'] !== null ? (string) $row['ultimo_pedido'] : null,
            ];
        }

        return $report;
    }

    /** Consulta de pedidos para exportación real en CSV o JSON */
    public function getOrdersForExport(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $conditions[] = 'p.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }

        if (isset($filters['desde']) && $filters['desde'] !== '') {
            $conditions[] = 'p.creado_en >= :desde::date';
            $params[':desde'] = $filters['desde'];
        }

        if (isset($filters['hasta']) && $filters['hasta'] !== '') {
            $conditions[] = "p.creado_en < (:hasta::date + INTERVAL '1 day')";
            $params[':hasta'] = $filters['hasta'];
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT p.id,
                       p.codigo_pedido,
                       p.estado,
                       p.es_personalizado,
                       p.importe_total,
                       p.direccion_envio,
                       p.creado_en,
                       (u.nombre || ' ' || u.apellidos) AS cliente,
                       u.email AS cliente_email,
                       COUNT(pl.id) AS num_lineas,
                       COALESCE(SUM(pl.cantidad), 0) AS total_unidades
                FROM pedidos p
                INNER JOIN usuarios u ON u.id = p.usuario_id
                LEFT JOIN pedido_lineas pl ON pl.pedido_id = p.id
                {$whereClause}
                GROUP BY p.id, p.codigo_pedido, p.estado, p.es_personalizado,
                         p.importe_total, p.direccion_envio, p.creado_en,
                         u.nombre, u.apellidos, u.email
                ORDER BY p.creado_en DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $orders = [];
        foreach ($rows as $row) {
            $orders[] = [
                'id'               => (int) $row['id'],
                'codigo_pedido'    => (string) $row['codigo_pedido'],
                'fecha'            => (string) $row['creado_en'],
                'cliente'          => (string) $row['cliente'],
                'cliente_email'    => (string) $row['cliente_email'],
                'estado'           => (string) $row['estado'],
                'es_personalizado' => (bool) $row['es_personalizado'],
                'num_lineas'       => (int) $row['num_lineas'],
                'total_unidades'   => (int) $row['total_unidades'],
                'importe_total'    => (float) $row['importe_total'],
                'direccion_envio'  => (string) $row['direccion_envio'],
            ];
        }

        return $orders;
    }

    /** Localiza una variante por SKU + talla + color (bloqueando la fila para importación segura) */
    public function findVariantBySkuTallaColorForUpdate(string $sku, float $talla, string $color): ?array
    {
        $sql = 'SELECT v.id, v.stock, v.stock_minimo, m.sku
                FROM variantes_modelo v
                INNER JOIN modelos m ON m.id = v.modelo_id
                WHERE UPPER(m.sku) = UPPER(:sku)
                  AND v.talla = :talla
                  AND LOWER(v.color) = LOWER(:color)
                FOR UPDATE OF v';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':sku'   => $sku,
            ':talla' => $talla,
            ':color' => $color,
        ]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}