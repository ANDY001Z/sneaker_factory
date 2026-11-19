<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'pass',
        'secret',
        'token',
        'csrf_token',
        'authorization',
        'cookie',
    ];

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $entry = [
            'timestamp' => date(DATE_ATOM),
            'level'     => $level,
            'message'   => $message,
            'context'   => self::sanitize($context),
        ];

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents('php://stderr', ($json ?: '{"level":"ERROR","message":"Log encoding failed"}') . PHP_EOL);
    }

    private static function sanitize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}