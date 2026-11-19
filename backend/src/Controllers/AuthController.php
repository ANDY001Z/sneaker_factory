<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function register(Request $request): void
    {
        $result = $this->authService->register($request->getBody(), $request->getClientIp());
        Response::success($result, 201);
    }

    public function login(Request $request): void
    {
        $result = $this->authService->login($request->getBody(), $request->getClientIp());
        Response::success($result, 200);
    }

    public function logout(Request $request): void
    {
        $user = $request->getUser();
        $userId = $user !== null ? (int) $user['id'] : 0;

        $this->authService->logout($userId, $request->getClientIp());
        Response::success(['message' => 'Sesión cerrada correctamente.'], 200);
    }

    public function me(Request $request): void
    {
        $user = $request->getUser();
        $result = $this->authService->getCurrentSessionData($user ?? []);
        Response::success($result, 200);
    }
}