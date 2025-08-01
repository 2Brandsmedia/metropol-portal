<?php

declare(strict_types=1);

/**
 * Simple Router
 * 
 * URL-Routing ohne Framework
 * 
 * @author 2Brands Media GmbH
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private ?string $notFoundHandler = null;
    private string $basePath = '';
    
    /**
     * Setzt den Basis-Pfad
     */
    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }
    
    /**
     * Fügt eine GET-Route hinzu
     */
    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }
    
    /**
     * Fügt eine POST-Route hinzu
     */
    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }
    
    /**
     * Fügt eine PUT-Route hinzu
     */
    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }
    
    /**
     * Fügt eine DELETE-Route hinzu
     */
    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }
    
    /**
     * Fügt eine Route für alle Methoden hinzu
     */
    public function any(string $path, $handler, array $middlewares = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $this->addRoute($method, $path, $handler, $middlewares);
        }
    }
    
    /**
     * Fügt eine Route hinzu
     */
    private function addRoute(string $method, string $path, $handler, array $middlewares): void
    {
        $path = $this->basePath . '/' . ltrim($path, '/');
        $pattern = $this->convertPathToRegex($path);
        
        $this->routes[$method][$pattern] = [
            'handler' => $handler,
            'middlewares' => $middlewares,
            'path' => $path
        ];
    }
    
    /**
     * Konvertiert einen Pfad zu einem Regex-Pattern
     */
    private function convertPathToRegex(string $path): string
    {
        // Escape slashes
        $pattern = preg_quote($path, '#');
        
        // Convert parameters like {id} to regex groups
        $pattern = preg_replace('/\\\{(\w+)\\\}/', '(?P<$1>[^/]+)', $pattern);
        
        // Allow optional trailing slash
        $pattern = '#^' . $pattern . '/?$#';
        
        return $pattern;
    }
    
    /**
     * Fügt eine globale Middleware hinzu
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }
    
    /**
     * Setzt den 404-Handler
     */
    public function setNotFoundHandler($handler): void
    {
        $this->notFoundHandler = $handler;
    }
    
    /**
     * Führt das Routing aus
     */
    public function dispatch(string $method = null, string $uri = null): void
    {
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        $uri = $uri ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Request und Response erstellen
        $request = $this->createRequest($method, $uri);
        $response = $this->createResponse();
        
        // Globale Middlewares ausführen
        foreach ($this->middlewares as $middleware) {
            $result = $middleware($request, $response);
            if ($result === false) {
                return;
            }
        }
        
        // Route finden
        $route = $this->findRoute($method, $uri);
        
        if ($route === null) {
            $this->handleNotFound($request, $response);
            return;
        }
        
        // Route-spezifische Middlewares ausführen
        foreach ($route['middlewares'] as $middleware) {
            $result = $this->callMiddleware($middleware, $request, $response);
            if ($result === false) {
                return;
            }
        }
        
        // Handler ausführen
        $this->callHandler($route['handler'], $request, $response, $route['params']);
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
            if (preg_match($pattern, $uri, $matches)) {
                // Extrahiere benannte Parameter
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                
                return array_merge($route, ['params' => $params]);
            }
        }
        
        return null;
    }
    
    /**
     * Erstellt ein Request-Objekt
     */
    private function createRequest(string $method, string $uri): object
    {
        return (object) [
            'method' => $method,
            'uri' => $uri,
            'params' => $_GET,
            'body' => $_POST,
            'files' => $_FILES,
            'cookies' => $_COOKIE,
            'headers' => getallheaders() ?: [],
            'input' => file_get_contents('php://input')
        ];
    }
    
    /**
     * Erstellt ein Response-Objekt
     */
    private function createResponse(): object
    {
        return new class {
            private int $statusCode = 200;
            private array $headers = [];
            private string $body = '';
            
            public function setStatusCode(int $code): self
            {
                $this->statusCode = $code;
                http_response_code($code);
                return $this;
            }
            
            public function setHeader(string $name, string $value): self
            {
                $this->headers[$name] = $value;
                header("$name: $value");
                return $this;
            }
            
            public function json(array $data, int $statusCode = 200): void
            {
                $this->setStatusCode($statusCode);
                $this->setHeader('Content-Type', 'application/json');
                echo json_encode($data);
            }
            
            public function html(string $html, int $statusCode = 200): void
            {
                $this->setStatusCode($statusCode);
                $this->setHeader('Content-Type', 'text/html; charset=utf-8');
                echo $html;
            }
            
            public function redirect(string $url, int $statusCode = 302): void
            {
                $this->setStatusCode($statusCode);
                $this->setHeader('Location', $url);
                exit;
            }
            
            public function send(string $content = ''): void
            {
                echo $content;
            }
        };
    }
    
    /**
     * Ruft eine Middleware auf
     */
    private function callMiddleware($middleware, $request, $response): bool
    {
        if (is_callable($middleware)) {
            return $middleware($request, $response) !== false;
        }
        
        if (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                return $instance->handle($request, $response) !== false;
            }
        }
        
        return true;
    }
    
    /**
     * Ruft einen Handler auf
     */
    private function callHandler($handler, $request, $response, array $params): void
    {
        // Füge Route-Parameter zum Request hinzu
        $request->routeParams = $params;
        
        if (is_callable($handler)) {
            $handler($request, $response);
            return;
        }
        
        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controller, $method) = explode('@', $handler);
            
            if (class_exists($controller)) {
                $instance = new $controller();
                if (method_exists($instance, $method)) {
                    $instance->$method($request, $response);
                    return;
                }
            }
        }
        
        throw new Exception('Invalid route handler');
    }
    
    /**
     * Behandelt 404-Fehler
     */
    private function handleNotFound($request, $response): void
    {
        if ($this->notFoundHandler !== null) {
            $this->callHandler($this->notFoundHandler, $request, $response, []);
        } else {
            $response->setStatusCode(404);
            $response->html('<h1>404 - Seite nicht gefunden</h1>');
        }
    }
}