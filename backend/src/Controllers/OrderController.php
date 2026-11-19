<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;

final class OrderController
{
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function index(Request $request): void
    {
        $user = $request->getUser();
        $userId = $user !== null ? (int) $user['id'] : 0;

        $result = $this->orderService->listOwnOrders($userId, $request->getQuery());
        Response::success($result['items'], 200, $result['meta']);
    }

    public function show(Request $request): void
    {
        $orderId = $this->parsePositiveId($request->getParam('id'));
        $user = $request->getUser() ?? [];

        $order = $this->orderService->getOrderDetail($orderId, $user, $request->getClientIp());
        Response::success($order, 200);
    }

    public function create(Request $request): void
    {
        $user = $request->getUser();
        $userId = $user !== null ? (int) $user['id'] : 0;

        $created = $this->orderService->createOrder($userId, $request->getBody(), $request->getClientIp());
        Response::success($created, 201);
    }

    public function cancel(Request $request): void
    {
        $orderId = $this->parsePositiveId($request->getParam('id'));
        $user = $request->getUser() ?? [];

        $cancelled = $this->orderService->cancelOwnOrder($orderId, $user, $request->getBody(), $request->getClientIp());
        Response::success($cancelled, 200);
    }

    private function parsePositiveId(?string $raw): int
    {
        if ($raw === null || filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw <= 0) {
            throw new HttpException(400, 'El identificador del pedido debe ser un entero positivo.', 'INVALID_ID');
        }

        return (int) $raw;
    }
}