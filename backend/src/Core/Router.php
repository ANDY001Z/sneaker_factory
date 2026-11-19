<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}, middlewares: array<int, object>}> */
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middlewares);
    }

    public function post(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middlewares);
    }

    public function put(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middlewares);
    }

    public function patch(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $pattern, $handler, $middlewares);
    }

    public function delete(string $pattern, array $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middlewares);
    }

    public function dispatch(Request $request): void
    {
        $matchedRoute = null;
        $matchedParams = [];
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $matches = [];
            $isPathMatch = preg_match($route['regex'], $request->getPath(), $matches) === 1;

            if ($isPathMatch) {
                if ($route['method'] === $request->getMethod() && $matchedRoute === null) {
                    $matchedRoute = $route;
                    $matchedParams = $this->extractNamedParams($matches);
                } else {
                    $allowedMethods[] = $route['method'];
                }
            }
        }

        if ($matchedRoute !== null) {
            $request->setRouteParams($matchedParams);

            foreach ($matchedRoute['middlewares'] as $middleware) {
                $middleware->handle($request);
            }

            [$controllerClass, $action] = $matchedRoute['handler'];
            $controller = new $controllerClass();
            $controller->$action($request);
        } elseif ($allowedMethods !== []) {
            $uniqueMethods = array_values(array_unique($allowedMethods));
            header('Allow: ' . implode(', ', $uniqueMethods));
            throw new HttpException(
                405,
                'Método HTTP no permitido para esta ruta.',
                'METHOD_NOT_ALLOWED',
                ['allowed_methods' => $uniqueMethods]
            );
        } else {
            throw new HttpException(404, 'Ruta no encontrada.', 'NOT_FOUND');
        }
    }

    private function addRoute(string $method, string $pattern, array $handler, array $middlewares): void
    {
        $normalized = rtrim($pattern, '/');
        $cleanPattern = $normalized === '' ? '/' : $normalized;

        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $cleanPattern);
        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $cleanPattern,
            'regex'       => '#^' . $regex . '$#',
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    private function extractNamedParams(array $matches): array
    {
        $params = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}