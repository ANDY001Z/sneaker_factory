<?php

declare(strict_types=1);

namespace Config;

use RuntimeException;

final class Config
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);

        if ($value === null) {
            throw new RuntimeException("Variable de entorno obligatoria no definida: {$key}");
        }

        return $value;
    }

    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'production') === 'production';
    }
}