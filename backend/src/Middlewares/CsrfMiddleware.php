<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\HttpException;
use App\Core\Request;

final class CsrfMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): void
    {
        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $sessionToken = $_SESSION['csrf_token'] ?? null;
            $headerToken = $request->getHeader('x-csrf-token');

            if (!is_string($sessionToken) || !is_string($headerToken) || !hash_equals($sessionToken, $headerToken)) {
                throw new HttpException(
                    403,
                    'Token CSRF ausente o inválido.',
                    'CSRF_INVALID'
                );
            }
        }
    }
}