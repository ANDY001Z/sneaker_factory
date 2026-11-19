<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use Config\Database;

final class HealthController
{
    public function check(Request $request): void
    {
        $dbUp = Database::ping();
        $statusCode = $dbUp ? 200 : 503;

        Response::json([
            'status'    => $dbUp ? 'ok' : 'degraded',
            'timestamp' => date(DATE_ATOM),
            'services'  => [
                'app'      => 'up',
                'database' => $dbUp ? 'up' : 'down',
            ],
        ], $statusCode);
    }
}