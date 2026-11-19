<?php

declare(strict_types=1);

namespace App\Models;

final class Order
{
    /**
     * @param array<int, OrderLine> $lineas
     * @param array<int, array<string, mixed>> $historialEstados
     */
    public function __construct(
        public readonly int $id,
        public readonly string $codigoPedido,
        public readonly int $usuarioId,
        public readonly string $usuarioNombre,
        public readonly string $usuarioEmail,
        public readonly string $estado,
        public readonly bool $esPersonalizado,
        public readonly float $importeTotal,
        public readonly string $direccionEnvio,
        public readonly ?string $notas,
        public readonly string $creadoEn,
        public readonly string $actualizadoEn,
        public readonly array $lineas = [],
        public readonly array $historialEstados = []
    ) {
    }

    /**
     * @param array<int, OrderLine> $lineas
     * @param array<int, array<string, mixed>> $historialEstados
     */
    public static function fromRow(array $row, array $lineas = [], array $historialEstados = []): self
    {
        return new self(
            id: (int) $row['id'],
            codigoPedido: (string) $row['codigo_pedido'],
            usuarioId: (int) $row['usuario_id'],
            usuarioNombre: (string) $row['usuario_nombre'],
            usuarioEmail: (string) $row['usuario_email'],
            estado: (string) $row['estado'],
            esPersonalizado: (bool) $row['es_personalizado'],
            importeTotal: (float) $row['importe_total'],
            direccionEnvio: (string) $row['direccion_envio'],
            notas: $row['notas'] !== null ? (string) $row['notas'] : null,
            creadoEn: (string) $row['creado_en'],
            actualizadoEn: (string) $row['actualizado_en'],
            lineas: $lineas,
            historialEstados: $historialEstados
        );
    }

    public function toArray(bool $includeHistory = true): array
    {
        $lineasArray = [];
        foreach ($this->lineas as $linea) {
            $lineasArray[] = $linea->toArray();
        }

        $payload = [
            'id'               => $this->id,
            'codigo_pedido'    => $this->codigoPedido,
            'usuario_id'       => $this->usuarioId,
            'cliente'          => [
                'nombre' => $this->usuarioNombre,
                'email'  => $this->usuarioEmail,
            ],
            'estado'           => $this->estado,
            'es_personalizado' => $this->esPersonalizado,
            'importe_total'    => $this->importeTotal,
            'direccion_envio'  => $this->direccionEnvio,
            'notas'            => $this->notas,
            'creado_en'        => $this->creadoEn,
            'actualizado_en'   => $this->actualizadoEn,
            'lineas'           => $lineasArray,
        ];

        if ($includeHistory) {
            $payload['historial_estados'] = $this->historialEstados;
        }

        return $payload;
    }
}