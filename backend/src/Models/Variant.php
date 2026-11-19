<?php

declare(strict_types=1);

namespace App\Models;

final class Variant
{
    public function __construct(
        public readonly int $id,
        public readonly int $modeloId,
        public readonly float $talla,
        public readonly string $color,
        public readonly int $stock,
        public readonly int $stockMinimo
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            modeloId: (int) $row['modelo_id'],
            talla: (float) $row['talla'],
            color: (string) $row['color'],
            stock: (int) $row['stock'],
            stockMinimo: (int) $row['stock_minimo']
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'modelo_id'    => $this->modeloId,
            'talla'        => $this->talla,
            'color'        => $this->color,
            'stock'        => $this->stock,
            'stock_minimo' => $this->stockMinimo,
            'disponible'   => $this->stock > 0,
            'stock_bajo'   => $this->stock <= $this->stockMinimo,
        ];
    }
}