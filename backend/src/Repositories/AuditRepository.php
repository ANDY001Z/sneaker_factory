<?php

declare(strict_types=1);

namespace App\Repositories;

use Config\Database;
use PDO;

final class AuditRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
    }

    public function insert(
        ?int $usuarioId,
        string $tipoEvento,
        string $entidad,
        ?int $entidadId,
        string $resultado,
        array $detalles,
        ?string $ipOrigen
    ): void {
        $sql = 'INSERT INTO auditoria_eventos
                    (usuario_id, tipo_evento, entidad, entidad_id, resultado, detalles, ip_origen)
                VALUES
                    (:usuario_id, :tipo_evento, :entidad, :entidad_id, :resultado, :detalles::jsonb, :ip_origen)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':usuario_id', $usuarioId, $usuarioId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':tipo_evento', $tipoEvento, PDO::PARAM_STR);
        $stmt->bindValue(':entidad', $entidad, PDO::PARAM_STR);
        $stmt->bindValue(':entidad_id', $entidadId, $entidadId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':resultado', $resultado, PDO::PARAM_STR);
        $stmt->bindValue(
            ':detalles',
            json_encode($detalles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            PDO::PARAM_STR
        );
        $stmt->bindValue(':ip_origen', $ipOrigen, $ipOrigen !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function findRecent(int $limit = 50, ?string $entidad = null): array
    {
        $safeLimit = max(1, min($limit, 200));

        if ($entidad !== null && $entidad !== '') {
            $sql = 'SELECT a.id, a.usuario_id, u.email AS usuario_email, a.tipo_evento,
                           a.entidad, a.entidad_id, a.resultado, a.detalles, a.ip_origen, a.creado_en
                    FROM auditoria_eventos a
                    LEFT JOIN usuarios u ON u.id = a.usuario_id
                    WHERE a.entidad = :entidad
                    ORDER BY a.creado_en DESC, a.id DESC
                    LIMIT :limite';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':entidad', $entidad, PDO::PARAM_STR);
            $stmt->bindValue(':limite', $safeLimit, PDO::PARAM_INT);
        } else {
            $sql = 'SELECT a.id, a.usuario_id, u.email AS usuario_email, a.tipo_evento,
                           a.entidad, a.entidad_id, a.resultado, a.detalles, a.ip_origen, a.creado_en
                    FROM auditoria_eventos a
                    LEFT JOIN usuarios u ON u.id = a.usuario_id
                    ORDER BY a.creado_en DESC, a.id DESC
                    LIMIT :limite';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limite', $safeLimit, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll();

        $events = [];
        foreach ($rows as $row) {
            $decodedDetails = is_string($row['detalles']) ? json_decode($row['detalles'], true) : [];
            $events[] = [
                'id'            => (int) $row['id'],
                'usuario_id'    => $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null,
                'usuario_email' => $row['usuario_email'],
                'tipo_evento'   => (string) $row['tipo_evento'],
                'entidad'       => (string) $row['entidad'],
                'entidad_id'    => $row['entidad_id'] !== null ? (int) $row['entidad_id'] : null,
                'resultado'     => (string) $row['resultado'],
                'detalles'      => is_array($decodedDetails) ? $decodedDetails : [],
                'ip_origen'     => $row['ip_origen'],
                'creado_en'     => (string) $row['creado_en'],
            ];
        }

        return $events;
    }
}