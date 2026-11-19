<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\AuditRepository;
use Throwable;

final class AuditService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'pass',
        'secret',
        'token',
        'csrf_token',
    ];

    private AuditRepository $repository;

    public function __construct()
    {
        $this->repository = new AuditRepository();
    }

    public function record(
        ?int $usuarioId,
        string $tipoEvento,
        string $entidad,
        ?int $entidadId,
        string $resultado,
        array $detalles = [],
        ?string $ipOrigen = null
    ): void {
        $cleanDetails = $this->sanitize($detalles);

        try {
            $this->repository->insert(
                $usuarioId,
                $tipoEvento,
                $entidad,
                $entidadId,
                $resultado,
                $cleanDetails,
                $ipOrigen
            );
        } catch (Throwable $e) {
            Logger::error('No se pudo registrar evento de auditoría en BBDD', [
                'tipo_evento' => $tipoEvento,
                'entidad'     => $entidad,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    public function listRecent(int $limit = 50, ?string $entidad = null): array
    {
        return $this->repository->findRecent($limit, $entidad);
    }

    private function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}