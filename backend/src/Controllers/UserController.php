<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class UserController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function getProfile(Request $request): void
    {
        Response::success($request->getUser(), 200);
    }

    public function updateProfile(Request $request): void
    {
        $user = $request->getUser();
        $userId = $user !== null ? (int) $user['id'] : 0;

        $updated = $this->authService->updateOwnProfile($userId, $request->getBody());
        Response::success($updated, 200);
    }
}