<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    public static function register(): void
    {
        $prefixes = [
            'App\\'    => dirname(__DIR__) . DIRECTORY_SEPARATOR,
            'Config\\' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR,
        ];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            $loaded = false;

            foreach ($prefixes as $prefix => $baseDir) {
                if (!$loaded && str_starts_with($class, $prefix)) {
                    $relativeClass = substr($class, strlen($prefix));
                    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                    if (is_file($file)) {
                        require_once $file;
                    }

                    $loaded = true;
                }
            }
        });
    }
}