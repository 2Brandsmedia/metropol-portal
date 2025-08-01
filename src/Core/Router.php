<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

/**
 * Router-Klasse für Request-Routing
 * 
 * @author 2Brands Media GmbH
 */
class Router
{
    private Container $container;
    private array $routes = [];
    private array $patterns = [
        ':any' => '([^/]+)',
        ':num' => '([0-9]+)',
        ':all' => '(.*)',
        ':string' => '([a-zA-Z]+)',
        ':alphanum' => '([a-zA-Z0-9]+)',
        ':slug' => '([a-z0-9-]+)'
    ];
    private array $middlewareGroups = [];
    private array $currentGroupMiddleware = [];
    private string $currentGroupPrefix = '';
    private $notFoundHandler = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Registriert eine GET-Route
     */
    public function get(string $pattern, $callback, array $middleware = []): self
    {
        return $this->addRoute('GET', $pattern, $callback, $middleware);
    }

    /**
     * Registriert eine POST-Route
     */
    public function post(string $pattern, $callback, array $middleware = []): self
    {
        return $this->addRoute('POST', $pattern, $callback, $middleware);
    }

    /**
     * Registriert eine PUT-Route
     */
    public function put(string $pattern, $callback, array $middleware = []): self
    {
        return $this->addRoute('PUT', $pattern, $callback, $middleware);
    }

    /**
     * Registriert eine DELETE-Route
     */
    public function delete(string $pattern, $callback, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $pattern, $callback, $middleware);
    }

    /**
     * Registriert eine PATCH-Route
     */
    public function patch(string $pattern, $callback, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $pattern, $callback, $middleware);
    }

    /**
     * Registriert eine OPTIONS-Route
     */
    public function options(string $pattern, $callback, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $pattern, $callback, $middleware);
    }

    /**
     * Registriert eine Route für alle HTTP-Methoden
     */
    public function any(string $pattern, $callback, array $middleware = []): self
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        
        foreach ($methods as $method) {
            $this->addRoute($method, $pattern, $callback, $middleware);
        }
        
        return $this;
    }

    /**
     * Erstellt eine Routen-Gruppe
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $previousGroupPrefix = $this->currentGroupPrefix;
        $previousGroupMiddleware = $this->currentGroupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->currentGroupPrefix = $previousGroupPrefix . '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $this->currentGroupMiddleware = array_merge(
                $previousGroupMiddleware,
                (array) $attributes['middleware']
            );
        }

        $callback($this);

        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentGroupMiddleware = $previousGroupMiddleware;
    }

    /**
     * Fügt eine Route hinzu
     */
    private function addRoute(string $method, string $pattern, $callback, array $middleware = []): self
    {
        $pattern = $this->currentGroupPrefix . '/' . trim($pattern, '/');
        $pattern = rtrim($pattern, '/') ?: '/';

        $middleware = array_merge($this->currentGroupMiddleware, $middleware);

        $this->routes[$method][$pattern] = [
            'callback' => $callback,
            'middleware' => $middleware
        ];

        return $this;
    }

    /**
     * Verarbeitet einen Request und gibt eine Response zurück
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        // OPTIONS-Request für CORS
        if ($method === 'OPTIONS') {
            return new Response('', 204);
        }

        // Route finden
        $route = $this->findRoute($method, $uri);

        if ($route === null) {
            return $this->createNotFoundResponse();
        }

        // Middleware ausführen
        $response = $this->runMiddleware(
            $route['middleware'],
            $request,
            function ($request) use ($route) {
                return $this->callRouteCallback($route['callback'], $route['params']);
            }
        );

        return $response;
    }

    /**
     * Sucht eine passende Route
     */
    private function findRoute(string $method, string $uri): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $pattern => $route) {
            $regex = $this->compilePattern($pattern);
            
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                
                return [
                    'callback' => $route['callback'],
                    'middleware' => $route['middleware'],
                    'params' => $matches
                ];
            }
        }

        return null;
    }

    /**
     * Kompiliert ein Routen-Pattern zu einem regulären Ausdruck
     */
    private function compilePattern(string $pattern): string
    {
        $pattern = str_replace('/', '\/', $pattern);
        
        foreach ($this->patterns as $placeholder => $regex) {
            $pattern = str_replace($placeholder, $regex, $pattern);
        }

        // Named parameters (:parameter)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^\/]+)', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Führt Middleware aus
     */
    private function runMiddleware(array $middleware, Request $request, \Closure $next): Response
    {
        if (empty($middleware)) {
            return $next($request);
        }

        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function ($request) use ($middleware, $next) {
                    $instance = $this->resolveMiddleware($middleware);
                    return $instance->handle($request, $next);
                };
            },
            $next
        );

        return $pipeline($request);
    }

    /**
     * Löst eine Middleware-Klasse auf
     */
    private function resolveMiddleware($middleware)
    {
        if (is_string($middleware)) {
            return $this->container->get($middleware);
        }

        return $middleware;
    }

    /**
     * Ruft den Route-Callback auf
     */
    private function callRouteCallback($callback, array $params = []): Response
    {
        if (is_string($callback) && strpos($callback, '@') !== false) {
            [$controller, $method] = explode('@', $callback);
            $controller = $this->container->get($controller);
            $result = $controller->$method(...$params);
        } elseif (is_callable($callback)) {
            $result = $callback(...$params);
        } else {
            throw new Exception('Ungültiger Route-Callback');
        }

        if ($result instanceof Response) {
            return $result;
        }

        $response = new Response();
        
        if (is_array($result) || is_object($result)) {
            $response->json($result);
        } else {
            $response->setContent((string) $result);
        }

        return $response;
    }

    /**
     * Setzt einen benutzerdefinierten 404-Handler
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Erstellt eine 404-Response
     */
    private function createNotFoundResponse(): Response
    {
        if ($this->notFoundHandler !== null) {
            $result = call_user_func($this->notFoundHandler);
            if ($result instanceof Response) {
                return $result;
            }
        }
        
        $response = new Response();
        $response->setStatusCode(404);
        $response->json([
            'error' => true,
            'message' => 'Route nicht gefunden'
        ]);
        
        return $response;
    }
}