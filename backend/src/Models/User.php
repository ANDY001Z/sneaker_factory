<?php

declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly int $rolId,
        public readonly string $rolNombre,
        public readonly string $nombre,
        public readonly string $apellidos,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $telefono,
        public readonly ?string $direccion,
        public readonly bool $activo,
        public readonly string $creadoEn
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            rolId: (int) $row['rol_id'],
            rolNombre: (string) $row['rol_nombre'],
            nombre: (string) $row['nombre'],
            apellidos: (string) $row['apellidos'],
            email: (string) $row['email'],
            passwordHash: (string) $row['password_hash'],
            telefono: $row['telefono'] !== null ? (string) $row['telefono'] : null,
            direccion: $row['direccion'] !== null ? (string) $row['direccion'] : null,
            activo: (bool) $row['activo'],
            creadoEn: (string) $row['creado_en']
        );
    }

    public function toPublicArray(): array
    {
        return [
            'id'        => $this->id,
            'rol'       => $this->rolNombre,
            'nombre'    => $this->nombre,
            'apellidos' => $this->apellidos,
            'email'     => $this->email,
            'telefono'  => $this->telefono,
            'direccion' => $this->direccion,
            'activo'    => $this->activo,
            'creado_en' => $this->creadoEn,
        ];
    }
}