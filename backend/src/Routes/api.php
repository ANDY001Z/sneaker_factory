<?php

declare(strict_types=1);

use App\Controllers\AdminProductController;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\HealthController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\RoleMiddleware;

return static function (Router $router): void {
    $auth        = new AuthMiddleware();
    $csrf        = new CsrfMiddleware();
    $onlyAdmin   = new RoleMiddleware(['admin']);
    $staffRoles  = new RoleMiddleware(['admin', 'almacen']);

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

    // Catálogo público (búsqueda, filtros combinables, paginación y detalle)
    $router->get('/api/modelos', [CatalogController::class, 'index']);
    $router->get('/api/modelos/{id}', [CatalogController::class, 'show']);

    // Panel interno: gestión de productos, variantes, stock y auditoría
    $router->get('/api/admin/modelos', [AdminProductController::class, 'index'], [$auth, $staffRoles]);
    $router->post('/api/admin/modelos', [AdminProductController::class, 'createModel'], [$auth, $onlyAdmin, $csrf]);
    $router->put('/api/admin/modelos/{id}', [AdminProductController::class, 'updateModel'], [$auth, $staffRoles, $csrf]);
    $router->delete('/api/admin/modelos/{id}', [AdminProductController::class, 'deleteModel'], [$auth, $onlyAdmin, $csrf]);
    $router->post('/api/admin/modelos/{id}/variantes', [AdminProductController::class, 'createVariant'], [$auth, $staffRoles, $csrf]);
    $router->patch('/api/admin/variantes/{id}/stock', [AdminProductController::class, 'updateStock'], [$auth, $staffRoles, $csrf]);
    $router->get('/api/admin/auditoria', [AdminProductController::class, 'listAudit'], [$auth, $onlyAdmin]);
};