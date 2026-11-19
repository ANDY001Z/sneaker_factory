<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\CatalogService;

final class AdminProductController
{
    private CatalogService $catalogService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->catalogService = new CatalogService();
        $this->auditService = new AuditService();
    }

    public function index(Request $request): void
    {
        $result = $this->catalogService->listCatalog($request->getQuery(), true);
        Response::success($result['items'], 200, $result['meta']);
    }

    public function createModel(Request $request): void
    {
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $created = $this->catalogService->createModel($request->getBody(), $actorId, $request->getClientIp());
        Response::success($created, 201);
    }

    public function updateModel(Request $request): void
    {
        $id = $this->parsePositiveId($request->getParam('id'), 'modelo');
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $updated = $this->catalogService->updateModel($id, $request->getBody(), $actorId, $request->getClientIp());
        Response::success($updated, 200);
    }

    public function deleteModel(Request $request): void
    {
        $id = $this->parsePositiveId($request->getParam('id'), 'modelo');
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $this->catalogService->deactivateModel($id, $actorId, $request->getClientIp());
        Response::success(['message' => 'Modelo desactivado correctamente.'], 200);
    }

    public function createVariant(Request $request): void
    {
        $modelId = $this->parsePositiveId($request->getParam('id'), 'modelo');
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $created = $this->catalogService->createVariant($modelId, $request->getBody(), $actorId, $request->getClientIp());
        Response::success($created, 201);
    }

    public function updateStock(Request $request): void
    {
        $variantId = $this->parsePositiveId($request->getParam('id'), 'variante');
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $updated = $this->catalogService->adjustStock($variantId, $request->getBody(), $actorId, $request->getClientIp());
        Response::success($updated, 200);
    }

    public function listAudit(Request $request): void
    {
        $limit = (int) ($request->getQuery('limit', 50));
        $entidad = $request->getQuery('entidad');
        $entidadStr = is_string($entidad) ? trim($entidad) : null;

        $events = $this->auditService->listRecent($limit, $entidadStr);
        Response::success($events, 200);
    }

    private function parsePositiveId(?string $raw, string $label): int
    {
        if ($raw === null || filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw <= 0) {
            throw new HttpException(400, "El identificador de {$label} debe ser un entero positivo.", 'INVALID_ID');
        }

        return (int) $raw;
    }
}