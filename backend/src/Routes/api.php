<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

return static function (Router $router): void {
    $auth = new AuthMiddleware();
    $csrf = new CsrfMiddleware();

    // Salud del servicio (contrato con Despliegue)
    $router->get('/health', [HealthController::class, 'check']);
    $router->get('/api/health', [HealthController::class, 'check']);

    // Autenticación
    $router->post('/api/auth/register', [AuthController::class, 'register']);
    $router->post('/api/auth/login', [AuthController::class, 'login']);
    $router->post('/api/auth/logout', [AuthController::class, 'logout'], [$auth, $csrf]);
    $router->get('/api/auth/me', [AuthController::class, 'me'], [$auth]);

    // Perfil propio del usuario autenticado
    $router->get('/api/profile', [UserController::class, 'getProfile'], [$auth]);
    $router->put('/api/profile', [UserController::class, 'updateProfile'], [$auth, $csrf]);
};