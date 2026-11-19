<?php

declare(strict_types=1);

use App\Controllers\AdminOrderController;
use App\Controllers\AdminProductController;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\HealthController;
use App\Controllers\OrderController;
use App\Controllers\ReportController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\RoleMiddleware;

return static function (Router $router): void {
    $auth       = new AuthMiddleware();
    $csrf       = new CsrfMiddleware();
    $onlyAdmin  = new RoleMiddleware(['admin']);
    $staffRoles = new RoleMiddleware(['admin', 'almacen']);

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

    // Catálogo público
    $router->get('/api/modelos', [CatalogController::class, 'index']);
    $router->get('/api/modelos/{id}', [CatalogController::class, 'show']);

    // Pedidos propios del cliente autenticado
    $router->get('/api/pedidos', [OrderController::class, 'index'], [$auth]);
    $router->post('/api/pedidos', [OrderController::class, 'create'], [$auth, $csrf]);
    $router->get('/api/pedidos/{id}', [OrderController::class, 'show'], [$auth]);
    $router->patch('/api/pedidos/{id}/cancelar', [OrderController::class, 'cancel'], [$auth, $csrf]);

    // Panel interno: gestión de productos, variantes, stock y auditoría
    $router->get('/api/admin/modelos', [AdminProductController::class, 'index'], [$auth, $staffRoles]);
    $router->post('/api/admin/modelos', [AdminProductController::class, 'createModel'], [$auth, $onlyAdmin, $csrf]);
    $router->put('/api/admin/modelos/{id}', [AdminProductController::class, 'updateModel'], [$auth, $staffRoles, $csrf]);
    $router->delete('/api/admin/modelos/{id}', [AdminProductController::class, 'deleteModel'], [$auth, $onlyAdmin, $csrf]);
    $router->post('/api/admin/modelos/{id}/variantes', [AdminProductController::class, 'createVariant'], [$auth, $staffRoles, $csrf]);
    $router->patch('/api/admin/variantes/{id}/stock', [AdminProductController::class, 'updateStock'], [$auth, $staffRoles, $csrf]);
    $router->get('/api/admin/auditoria', [AdminProductController::class, 'listAudit'], [$auth, $onlyAdmin]);

    // Panel interno: exportación, gestión global de pedidos y estados
    $router->get('/api/admin/pedidos/exportar', [ReportController::class, 'exportOrders'], [$auth, $staffRoles]);
    $router->get('/api/admin/pedidos', [AdminOrderController::class, 'index'], [$auth, $staffRoles]);
    $router->get('/api/admin/pedidos/{id}', [AdminOrderController::class, 'show'], [$auth, $staffRoles]);
    $router->patch('/api/admin/pedidos/{id}/estado', [AdminOrderController::class, 'updateStatus'], [$auth, $staffRoles, $csrf]);

    // Panel interno: KPIs, informes de explotación e importación validada
    $router->get('/api/admin/dashboard', [ReportController::class, 'dashboard'], [$auth, $staffRoles]);
    $router->get('/api/admin/informes/ventas-modelos', [ReportController::class, 'salesByModel'], [$auth, $staffRoles]);
    $router->get('/api/admin/informes/clientes', [ReportController::class, 'topCustomers'], [$auth, $onlyAdmin]);
    $router->post('/api/admin/stock/importar', [ReportController::class, 'importStock'], [$auth, $staffRoles, $csrf]);
};