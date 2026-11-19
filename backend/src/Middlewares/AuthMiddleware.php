<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\UserRepository;

final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId)) {
            throw new HttpException(401, 'Debes iniciar sesión para acceder a este recurso.', 'UNAUTHORIZED');
        }

        $repository = new UserRepository();
        $user = $repository->findById($userId);

        if ($user === null || !$user->activo) {
            $_SESSION = [];
            throw new HttpException(401, 'Sesión inválida o cuenta desactivada.', 'UNAUTHORIZED');
        }

        $request->setUser($user->toPublicArray());
    }
}