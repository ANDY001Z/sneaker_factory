<?php

declare(strict_types=1);

namespace App\Models;

final class OrderLine
{
    public function __construct(
        public readonly int $id,
        public readonly int $pedidoId,
        public readonly int $varianteId,
        public readonly int $modeloId,
        public readonly string $modeloSku,
        public readonly string $modeloNombre,
        public readonly float $talla,
        public readonly string $color,
        public readonly int $cantidad,
        public readonly float $precioUnitario,
        public readonly float $subtotal,
        public readonly ?array $opcionesPersonalizacion
    ) {
    }

    public static function fromRow(array $row): self
    {
        $rawOptions = $row['opciones_personalizacion'] ?? null;
        $options = null;

        if (is_string($rawOptions) && $rawOptions !== '') {
            $decoded = json_decode($rawOptions, true);
            $options = is_array($decoded) ? $decoded : null;
        } elseif (is_array($rawOptions)) {
            $options = $rawOptions;
        }

        return new self(
            id: (int) $row['id'],
            pedidoId: (int) $row['pedido_id'],
            varianteId: (int) $row['variante_id'],
            modeloId: (int) $row['modelo_id'],
            modeloSku: (string) $row['modelo_sku'],
            modeloNombre: (string) $row['modelo_nombre'],
            talla: (float) $row['talla'],
            color: (string) $row['color'],
            cantidad: (int) $row['cantidad'],
            precioUnitario: (float) $row['precio_unitario'],
            subtotal: (float) $row['subtotal'],
            opcionesPersonalizacion: $options
        );
    }

    public function toArray(): array
    {
        return [
            'id'                       => $this->id,
            'variante_id'              => $this->varianteId,
            'modelo_id'                => $this->modeloId,
            'modelo_sku'               => $this->modeloSku,
            'modelo_nombre'            => $this->modeloNombre,
            'talla'                    => $this->talla,
            'color'                    => $this->color,
            'cantidad'                 => $this->cantidad,
            'precio_unitario'          => $this->precioUnitario,
            'subtotal'                 => $this->subtotal,
            'opciones_personalizacion' => $this->opcionesPersonalizacion,
        ];
    }
}