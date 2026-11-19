<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private const MAX_BODY_BYTES = 1048576; // 1 MB

    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private array $body;
    private array $routeParams = [];
    private ?array $user = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
        $parsedPath = parse_url($rawUri, PHP_URL_PATH);
        $normalizedPath = is_string($parsedPath) ? rtrim($parsedPath, '/') : '';
        $this->path = $normalizedPath === '' ? '/' : $normalizedPath;

        $this->query = $_GET;
        $this->headers = $this->extractHeaders();
        $this->body = $this->parseJsonBody();
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function getBody(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? $default;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function getParam(string $key, ?string $default = null): ?string
    {
        return isset($this->routeParams[$key]) ? (string) $this->routeParams[$key] : $default;
    }

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function getClientIp(): string
    {
        $forwarded = $this->getHeader('x-forwarded-for');
        if ($forwarded !== null && $forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function expectsJson(): bool
    {
        $accept = strtolower($this->getHeader('accept', '') ?? '');
        return str_starts_with($this->path, '/api')
            || $this->path === '/health'
            || str_contains($accept, 'application/json');
    }

    private function extractHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string) $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = (string) $value;
            }
        }

        return $headers;
    }

    private function parseJsonBody(): array
    {
        $methodsWithBody = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($this->method, $methodsWithBody, true)) {
            return [];
        }

        $contentType = strtolower($this->getHeader('content-type', '') ?? '');
        if (!str_contains($contentType, 'application/json')) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        if (strlen($raw) > self::MAX_BODY_BYTES) {
            throw new HttpException(413, 'El tamaño de la petición supera el límite permitido.', 'PAYLOAD_TOO_LARGE');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException(400, 'El cuerpo de la petición no es un JSON válido.', 'INVALID_JSON');
        }

        return $decoded;
    }
}