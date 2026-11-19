<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Variant;
use Config\Database;
use PDO;

final class VariantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findById(int $id): ?Variant
    {
        $sql = 'SELECT id, modelo_id, talla, color, stock, stock_minimo
                FROM variantes_modelo
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? Variant::fromRow($row) : null;
    }

    /** Bloquea la fila de la variante dentro de una transacción activa (evita sobreventa concurrente) */
    public function findByIdForUpdate(int $id): ?array
    {
        $sql = 'SELECT v.id, v.modelo_id, v.talla, v.color, v.stock, v.stock_minimo,
                       m.nombre AS modelo_nombre, m.precio_base, m.personalizable, m.activo AS modelo_activo
                FROM variantes_modelo v
                INNER JOIN modelos m ON m.id = v.modelo_id
                WHERE v.id = :id
                FOR UPDATE OF v';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<int, int> $modelIds @return array<int, array<int, Variant>> */
    public function findGroupedByModelIds(array $modelIds): array
    {
        if ($modelIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach (array_values($modelIds) as $index => $modelId) {
            $key = ':m' . $index;
            $placeholders[] = $key;
            $params[$key] = $modelId;
        }

        $sql = 'SELECT id, modelo_id, talla, color, stock, stock_minimo
                FROM variantes_modelo
                WHERE modelo_id IN (' . implode(', ', $placeholders) . ')
                ORDER BY modelo_id ASC, talla ASC, color ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $variant = Variant::fromRow($row);
            $grouped[$variant->modeloId][] = $variant;
        }

        return $grouped;
    }

    public function combinationExists(int $modeloId, float $talla, string $color): bool
    {
        $sql = 'SELECT 1
                FROM variantes_modelo
                WHERE modelo_id = :modelo_id
                  AND talla = :talla
                  AND LOWER(color) = LOWER(:color)
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':modelo_id' => $modeloId,
            ':talla'     => $talla,
            ':color'     => $color,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function create(array $data): Variant
    {
        $sql = 'INSERT INTO variantes_modelo (modelo_id, talla, color, stock, stock_minimo)
                VALUES (:modelo_id, :talla, :color, :stock, :stock_minimo)
                RETURNING id, modelo_id, talla, color, stock, stock_minimo';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':modelo_id'    => $data['modelo_id'],
            ':talla'        => $data['talla'],
            ':color'        => $data['color'],
            ':stock'        => $data['stock'],
            ':stock_minimo' => $data['stock_minimo'],
        ]);

        return Variant::fromRow($stmt->fetch());
    }

    public function updateStock(int $variantId, int $newStock, ?int $newMinStock = null): Variant
    {
        if ($newMinStock !== null) {
            $sql = 'UPDATE variantes_modelo
                    SET stock = :stock, stock_minimo = :stock_minimo
                    WHERE id = :id
                    RETURNING id, modelo_id, talla, color, stock, stock_minimo';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':stock'        => $newStock,
                ':stock_minimo' => $newMinStock,
                ':id'           => $variantId,
            ]);
        } else {
            $sql = 'UPDATE variantes_modelo
                    SET stock = :stock
                    WHERE id = :id
                    RETURNING id, modelo_id, talla, color, stock, stock_minimo';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':stock' => $newStock,
                ':id'    => $variantId,
            ]);
        }

        return Variant::fromRow($stmt->fetch());
    }

    public function decrementStock(int $variantId, int $quantity): void
    {
        $sql = 'UPDATE variantes_modelo
                SET stock = stock - :cantidad
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cantidad' => $quantity,
            ':id'       => $variantId,
        ]);
    }

    public function incrementStock(int $variantId, int $quantity): void
    {
        $sql = 'UPDATE variantes_modelo
                SET stock = stock + :cantidad
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cantidad' => $quantity,
            ':id'       => $variantId,
        ]);
    }
}