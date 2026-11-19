<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;

final class CatalogController
{
    private CatalogService $catalogService;

    public function __construct()
    {
        $this->catalogService = new CatalogService();
    }

    public function index(Request $request): void
    {
        $result = $this->catalogService->listCatalog($request->getQuery(), false);
        Response::success($result['items'], 200, $result['meta']);
    }

    public function show(Request $request): void
    {
        $idParam = $request->getParam('id');
        if ($idParam === null || filter_var($idParam, FILTER_VALIDATE_INT) === false || (int) $idParam <= 0) {
            throw new HttpException(400, 'El identificador del modelo debe ser un entero positivo.', 'INVALID_ID');
        }

        $model = $this->catalogService->getModelDetail((int) $idParam, true);
        Response::success($model, 200);
    }
}