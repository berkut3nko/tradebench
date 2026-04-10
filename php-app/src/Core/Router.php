<?php

namespace App\Core;

/**
 * Simple HTTP Router for MVC architecture
 */
class Router {
    private array $routes = [];

    /**
     * Register a new route
     */
    public function add(string $method, string $path, array $handler): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * Match current request to a registered route and execute it
     */
    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as $route) {
            /* Check exact match or regex match for params (e.g. /users/1) */
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $route['path']);
            $pattern = "@^" . $pattern . "$@D";

            if ($route['method'] === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove the full match

                $controllerName = $route['handler'][0];
                $action = $route['handler'][1];

                $controller = new $controllerName();
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        Response::error("Endpoint not found", 404);
    }
}