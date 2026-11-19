<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) !== 0) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }

        return false;
    }

    public static function handleException(Throwable $exception): void
    {
        $expectsJson = self::requestExpectsJson();

        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();

            if (!$expectsJson && $status === 404) {
                Response::html(self::render404Html(), 404);
            } else {
                Response::error(
                    $exception->getMessage(),
                    $status,
                    $exception->getErrorCode(),
                    $exception->getDetails()
                );
            }
        } else {
            Logger::error('Excepción no controlada en el servidor', [
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
                'file'      => basename($exception->getFile()),
                'line'      => $exception->getLine(),
                'uri'       => $_SERVER['REQUEST_URI'] ?? '',
                'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
            ]);

            if ($expectsJson) {
                Response::error(
                    'Error interno del servidor. Inténtelo de nuevo más tarde.',
                    500,
                    'INTERNAL_SERVER_ERROR'
                );
            } else {
                Response::html(self::render500Html(), 500);
            }
        }
    }

    private static function requestExpectsJson(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_starts_with($uri, '/api')
            || $uri === '/health'
            || str_contains($accept, 'application/json');
    }

    private static function render404Html(): string
    {
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Recurso no encontrado | Sneaker Factory</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #111; color: #f5f5f5; display: grid; place-items: center; min-height: 100vh; margin: 0; }
        main { text-align: center; padding: 2rem; max-width: 480px; }
        h1 { font-size: 4rem; margin: 0; color: #e63946; }
        p { color: #ccc; line-height: 1.5; }
        a { color: #fff; background: #e63946; padding: 0.6rem 1.2rem; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 1rem; }
    </style>
</head>
<body>
    <main>
        <h1>404</h1>
        <p>La página o recurso que buscas no existe en Sneaker Factory.</p>
        <a href="/">Volver al inicio</a>
    </main>
</body>
</html>';
    }

    private static function render500Html(): string
    {
        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>500 - Error interno | Sneaker Factory</title>
</head>
<body>
    <h1>500 - Error interno del servidor</h1>
    <p>Se ha producido un problema inesperado. Inténtalo de nuevo más tarde.</p>
</body>
</html>';
    }
}