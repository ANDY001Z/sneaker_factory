<?php

declare(strict_types=1);

use App\Controllers\HealthController;
use App\Core\Router;

return static function (Router $router): void {
    // Salud del servicio (contrato con Despliegue)
    $router->get('/health', [HealthController::class, 'check']);
    $router->get('/api/health', [HealthController::class, 'check']);
};