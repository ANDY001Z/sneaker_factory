<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Config\Database;
use PDO;

final class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function findById(int $id): ?User
    {
        $sql = 'SELECT u.id, u.rol_id, r.nombre AS rol_nombre, u.nombre, u.apellidos,
                       u.email, u.password_hash, u.telefono, u.direccion, u.activo, u.creado_en
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE u.id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $sql = 'SELECT u.id, u.rol_id, r.nombre AS rol_nombre, u.nombre, u.apellidos,
                       u.email, u.password_hash, u.telefono, u.direccion, u.activo, u.creado_en
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE LOWER(u.email) = LOWER(:email)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row !== false ? User::fromRow($row) : null;
    }

    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        if ($excludeUserId !== null) {
            $sql = 'SELECT 1 FROM usuarios WHERE LOWER(email) = LOWER(:email) AND id <> :exclude_id LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':email'      => $email,
                ':exclude_id' => $excludeUserId,
            ]);
        } else {
            $sql = 'SELECT 1 FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
        }

        return $stmt->fetchColumn() !== false;
    }

    public function createClient(array $data): User
    {
        $sql = "INSERT INTO usuarios (rol_id, nombre, apellidos, email, password_hash, telefono, direccion)
                VALUES (
                    (SELECT id FROM roles WHERE nombre = 'cliente'),
                    :nombre, :apellidos, :email, :password_hash, :telefono, :direccion
                )
                RETURNING id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre'        => $data['nombre'],
            ':apellidos'     => $data['apellidos'],
            ':email'         => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':telefono'      => $data['telefono'],
            ':direccion'     => $data['direccion'],
        ]);

        $newId = (int) $stmt->fetchColumn();
        $created = $this->findById($newId);

        if ($created === null) {
            throw new \RuntimeException('No se pudo recuperar el usuario recién registrado.');
        }

        return $created;
    }

    public function updateProfile(int $userId, array $data): User
    {
        $sql = 'UPDATE usuarios
                SET nombre = :nombre,
                    apellidos = :apellidos,
                    email = :email,
                    telefono = :telefono,
                    direccion = :direccion,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre'    => $data['nombre'],
            ':apellidos' => $data['apellidos'],
            ':email'     => $data['email'],
            ':telefono'  => $data['telefono'],
            ':direccion' => $data['direccion'],
            ':id'        => $userId,
        ]);

        $updated = $this->findById($userId);
        if ($updated === null) {
            throw new \RuntimeException('No se pudo recuperar el perfil actualizado.');
        }

        return $updated;
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $sql = 'UPDATE usuarios
                SET password_hash = :password_hash,
                    actualizado_en = NOW()
                WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':password_hash' => $passwordHash,
            ':id'            => $userId,
        ]);
    }
}