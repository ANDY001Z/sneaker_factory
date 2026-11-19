<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(array $payload, int $statusCode = 200): void
    {
        self::sendSecurityHeaders();
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function success(mixed $data = null, int $statusCode = 200, array $meta = []): void
    {
        $payload = [
            'ok'   => true,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        self::json($payload, $statusCode);
    }

    public static function error(
        string $message,
        int $statusCode = 400,
        string $errorCode = 'ERROR',
        array $details = []
    ): void {
        $error = [
            'code'    => $errorCode,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        self::json([
            'ok'    => false,
            'error' => $error,
        ], $statusCode);
    }

    public static function html(string $htmlContent, int $statusCode = 200): void
    {
        self::sendSecurityHeaders();
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $htmlContent;
    }

    public static function csv(string $filename, array $headers, array $rows): void
    {
        self::sendSecurityHeaders();
        http_response_code(200);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');

        $output = fopen('php://output', 'wb');
        if ($output !== false) {
            fputcsv($output, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }
            fclose($output);
        }
    }

    private static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
    }
}