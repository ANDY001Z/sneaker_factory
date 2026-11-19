<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Router;
use Config\Config;

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/src/Core/Autoloader.php';
Autoloader::register();

date_default_timezone_set(Config::get('APP_TIMEZONE', 'Europe/Madrid') ?? 'Europe/Madrid');

ErrorHandler::register();

// Configuración segura de sesión (cookies HttpOnly y SameSite)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$router = new Router();
$registerRoutes = require dirname(__DIR__) . '/src/Routes/api.php';
$registerRoutes($router);

$request = new Request();
$router->dispatch($request);