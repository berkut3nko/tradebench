<?php

namespace App\Core;

/**
 * @brief Simple HTTP Router implementing the Front Controller pattern for MVC architecture.
 */
class Router {
    /** @var array Holds all registered application routes. */
    private array $routes = [];

    /**
     * @brief Registers a new route in the application.
     * @param string $method The HTTP method (GET, POST, DELETE, etc.).
     * @param string $path The URL endpoint (supports regex tokens like {id}).
     * @param array $handler Callable array referencing [Controller::class, 'methodName'].
     */
    public function add(string $method, string $path, array $handler): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * @brief Matches the incoming request to a registered route and dispatches it.
     * @param string $method The requested HTTP method.
     * @param string $uri The requested URI path.
     */
    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as $route) {
            // Convert wildcard tokens (e.g., {id}) into executable regex capture groups
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $route['path']);
            $pattern = "@^" . $pattern . "$@D";

            if ($route['method'] === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove the full match from the array

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