<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\HttpException;
use App\Core\Request;

final class RoleMiddleware
{
    /** @param array<int, string> $allowedRoles */
    public function __construct(
        private readonly array $allowedRoles
    ) {
    }

    public function handle(Request $request): void
    {
        $user = $request->getUser();

        if ($user === null) {
            throw new HttpException(401, 'Autenticación requerida.', 'UNAUTHORIZED');
        }

        if (!in_array($user['rol'], $this->allowedRoles, true)) {
            throw new HttpException(
                403,
                'No tienes permisos suficientes para realizar esta operación.',
                'FORBIDDEN'
            );
        }
    }
}