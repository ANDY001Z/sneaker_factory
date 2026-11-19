<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SneakerModel;
use Config\Database;
use PDO;

final class ModelRepository
{
    private PDO $pdo;
    private VariantRepository $variants;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->variants = new VariantRepository();
    }

    /** @return array{items: array<int, SneakerModel>, total: int} */
    public function search(array $filters, int $page, int $limit, bool $includeInactive = false): array
    {
        $conditions = [];
        $params = [];

        if (!$includeInactive) {
            $conditions[] = 'm.activo = TRUE';
        }

        if (isset($filters['q']) && $filters['q'] !== '') {
            $conditions[] = '(m.nombre ILIKE :q OR m.marca ILIKE :q OR m.descripcion ILIKE :q OR m.sku ILIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        if (isset($filters['categoria']) && $filters['categoria'] !== '') {
            $conditions[] = 'm.categoria = :categoria';
            $params[':categoria'] = $filters['categoria'];
        }

        if (isset($filters['marca']) && $filters['marca'] !== '') {
            $conditions[] = 'm.marca ILIKE :marca';
            $params[':marca'] = $filters['marca'];
        }

        if (isset($filters['personalizable']) && is_bool($filters['personalizable'])) {
            $conditions[] = 'm.personalizable = :personalizable';
            $params[':personalizable'] = $filters['personalizable'];
        }

        if (isset($filters['min_precio'])) {
            $conditions[] = 'm.precio_base >= :min_precio';
            $params[':min_precio'] = $filters['min_precio'];
        }

        if (isset($filters['max_precio'])) {
            $conditions[] = 'm.precio_base <= :max_precio';
            $params[':max_precio'] = $filters['max_precio'];
        }

        $variantSubConditions = ['v.modelo_id = m.id'];
        $hasVariantFilter = false;

        if (isset($filters['talla'])) {
            $variantSubConditions[] = 'v.talla = :talla';
            $params[':talla'] = $filters['talla'];
            $hasVariantFilter = true;
        }

        if (isset($filters['color']) && $filters['color'] !== '') {
            $variantSubConditions[] = 'v.color ILIKE :color';
            $params[':color'] = '%' . $filters['color'] . '%';
            $hasVariantFilter = true;
        }

        if (isset($filters['solo_con_stock']) && $filters['solo_con_stock'] === true) {
            $variantSubConditions[] = 'v.stock > 0';
            $hasVariantFilter = true;
        }

        if ($hasVariantFilter) {
            $conditions[] = 'EXISTS (SELECT 1 FROM variantes_modelo v WHERE ' . implode(' AND ', $variantSubConditions) . ')';
        }

        $whereClause = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $orderMap = [
            'precio_asc'  => 'm.precio_base ASC, m.id ASC',
            'precio_desc' => 'm.precio_base DESC, m.id ASC',
            'nombre_asc'  => 'm.nombre ASC, m.id ASC',
            'recientes'   => 'm.creado_en DESC, m.id DESC',
        ];
        $sortKey = (string) ($filters['orden'] ?? 'recientes');
        $orderBy = $orderMap[$sortKey] ?? $orderMap['recientes'];

        // 1. Total de resultados para la paginación
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM modelos m {$whereClause}");
        $this->bindParams($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        // 2. Página actual de modelos
        $offset = ($page - 1) * $limit;
        $dataSql = "SELECT m.id, m.sku, m.marca, m.nombre, m.descripcion, m.categoria,
                           m.precio_base, m.imagen_url, m.personalizable, m.activo, m.creado_en
                    FROM modelos m
                    {$whereClause}
                    ORDER BY {$orderBy}
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $this->pdo->prepare($dataSql);
        $this->bindParams($dataStmt, $params);
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $rows = $dataStmt->fetchAll();

        // 3. Cargar variantes de todos los modelos de la página en 1 sola consulta
        $modelIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $variantsByModel = $this->variants->findGroupedByModelIds($modelIds);

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $items[] = SneakerModel::fromRow($row, $variantsByModel[$id] ?? []);
        }

        return ['items' => $items, 'total' => $total];
    }

    public function findById(int $id, bool $onlyActive = false): ?SneakerModel
    {
        $sql = 'SELECT id, sku, marca, nombre, descripcion, categoria,
                       precio_base, imagen_url, personalizable, activo, creado_en
                FROM modelos
                WHERE id = :id' . ($onlyActive ? ' AND activo = TRUE' : '');

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $variantsByModel = $this->variants->findGroupedByModelIds([$id]);
        return SneakerModel::fromRow($row, $variantsByModel[$id] ?? []);
    }

    public function skuExists(string $sku, ?int $excludeModelId = null): bool
    {
        if ($excludeModelId !== null) {
            $sql = 'SELECT 1 FROM modelos WHERE UPPER(sku) = UPPER(:sku) AND id <> :exclude_id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':sku' => $sku, ':exclude_id' => $excludeModelId]);
        } else {
            $sql = 'SELECT 1 FROM modelos WHERE UPPER(sku) = UPPER(:sku) LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':sku' => $sku]);
        }

        return $stmt->fetchColumn() !== false;
    }

    public function create(array $data): SneakerModel
    {
        $sql = 'INSERT INTO modelos (sku, marca, nombre, descripcion, categoria, precio_base, imagen_url, personalizable)
                VALUES (:sku, :marca, :nombre, :descripcion, :categoria, :precio_base, :imagen_url, :personalizable)
                RETURNING id, sku, marca, nombre, descripcion, categoria, precio_base, imagen_url, personalizable, activo, creado_en';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sku', $data['sku'], PDO::PARAM_STR);
        $stmt->bindValue(':marca', $data['marca'], PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $data['descripcion'], PDO::PARAM_STR);
        $stmt->bindValue(':categoria', $data['categoria'], PDO::PARAM_STR);
        $stmt->bindValue(':precio_base', $data['precio_base']);
        $stmt->bindValue(':imagen_url', $data['imagen_url'], PDO::PARAM_STR);
        $stmt->bindValue(':personalizable', (bool) $data['personalizable'], PDO::PARAM_BOOL);
        $stmt->execute();

        return SneakerModel::fromRow($stmt->fetch(), []);
    }

    public function update(int $id, array $data): SneakerModel
    {
        $sql = 'UPDATE modelos
                SET sku = :sku,
                    marca = :marca,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    categoria = :categoria,
                    precio_base = :precio_base,
                    imagen_url = :imagen_url,
                    personalizable = :personalizable,
                    activo = :activo
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':sku', $data['sku'], PDO::PARAM_STR);
        $stmt->bindValue(':marca', $data['marca'], PDO::PARAM_STR);
        $stmt->bindValue(':nombre', $data['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $data['descripcion'], PDO::PARAM_STR);
        $stmt->bindValue(':categoria', $data['categoria'], PDO::PARAM_STR);
        $stmt->bindValue(':precio_base', $data['precio_base']);
        $stmt->bindValue(':imagen_url', $data['imagen_url'], PDO::PARAM_STR);
        $stmt->bindValue(':personalizable', (bool) $data['personalizable'], PDO::PARAM_BOOL);
        $stmt->bindValue(':activo', (bool) $data['activo'], PDO::PARAM_BOOL);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $updated = $this->findById($id, false);
        if ($updated === null) {
            throw new \RuntimeException('No se pudo recuperar el modelo actualizado.');
        }

        return $updated;
    }

    public function deactivate(int $id): void
    {
        $sql = 'UPDATE modelos SET activo = FALSE WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
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