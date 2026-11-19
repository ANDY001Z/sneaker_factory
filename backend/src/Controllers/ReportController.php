<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;

final class ReportController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    public function dashboard(Request $request): void
    {
        $summary = $this->reportService->getDashboardKpis($request->getQuery());
        Response::success($summary, 200);
    }

    public function salesByModel(Request $request): void
    {
        $report = $this->reportService->getSalesByModel($request->getQuery());
        Response::success($report, 200);
    }

    public function topCustomers(Request $request): void
    {
        $report = $this->reportService->getTopCustomers($request->getQuery());
        Response::success($report, 200);
    }

    public function exportOrders(Request $request): void
    {
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $result = $this->reportService->exportOrders($request->getQuery(), $actorId, $request->getClientIp());

        if ($result['formato'] === 'csv') {
            Response::csv($result['filename'], $result['headers'], $result['rows']);
        } else {
            header('Content-Disposition: attachment; filename="' . basename($result['filename']) . '"');
            Response::success($result['payload'], 200);
        }
    }

    public function importStock(Request $request): void
    {
        $user = $request->getUser();
        $actorId = $user !== null ? (int) $user['id'] : 0;

        $result = $this->reportService->importStock($request->getBody(), $actorId, $request->getClientIp());
        Response::success($result, 200);
    }
}