<?php

declare(strict_types=1);

namespace App\Models;

final class SneakerModel
{
    /** @param array<int, Variant> $variantes */
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $marca,
        public readonly string $nombre,
        public readonly string $descripcion,
        public readonly string $categoria,
        public readonly float $precioBase,
        public readonly string $imagenUrl,
        public readonly bool $personalizable,
        public readonly bool $activo,
        public readonly string $creadoEn,
        public readonly array $variantes = []
    ) {
    }

    /** @param array<int, Variant> $variantes */
    public static function fromRow(array $row, array $variantes = []): self
    {
        return new self(
            id: (int) $row['id'],
            sku: (string) $row['sku'],
            marca: (string) $row['marca'],
            nombre: (string) $row['nombre'],
            descripcion: (string) $row['descripcion'],
            categoria: (string) $row['categoria'],
            precioBase: (float) $row['precio_base'],
            imagenUrl: (string) $row['imagen_url'],
            personalizable: (bool) $row['personalizable'],
            activo: (bool) $row['activo'],
            creadoEn: (string) $row['creado_en'],
            variantes: $variantes
        );
    }

    public function toArray(): array
    {
        $stockTotal = 0;
        $variantesArray = [];

        foreach ($this->variantes as $variant) {
            $stockTotal += $variant->stock;
            $variantesArray[] = $variant->toArray();
        }

        return [
            'id'             => $this->id,
            'sku'            => $this->sku,
            'marca'          => $this->marca,
            'nombre'         => $this->nombre,
            'descripcion'    => $this->descripcion,
            'categoria'      => $this->categoria,
            'precio_base'    => $this->precioBase,
            'imagen_url'     => $this->imagenUrl,
            'personalizable' => $this->personalizable,
            'activo'         => $this->activo,
            'stock_total'    => $stockTotal,
            'creado_en'      => $this->creadoEn,
            'variantes'      => $variantesArray,
        ];
    }
}