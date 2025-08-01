<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * Dependency Injection Container
 * 
 * @author 2Brands Media GmbH
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];

    /**
     * Bindet eine Abstraktion an eine Implementierung
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Bindet eine Abstraktion als Singleton
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Löst eine Abstraktion aus dem Container auf
     */
    public function get(string $abstract)
    {
        // Prüfen ob bereits eine Instanz existiert
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Prüfen ob ein Binding existiert
        if (!isset($this->bindings[$abstract])) {
            // Versuchen die Klasse automatisch aufzulösen
            if (class_exists($abstract)) {
                return $this->build($abstract);
            }
            
            throw new Exception("Binding für '{$abstract}' nicht gefunden");
        }

        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        // Closure auflösen
        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } else {
            $object = $this->build($concrete);
        }

        // Als Singleton speichern wenn gewünscht
        if ($binding['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Erstellt eine Instanz einer Klasse mit automatischer Dependency Injection
     */
    private function build(string $concrete)
    {
        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Klasse '{$concrete}' ist nicht instanziierbar");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Löst die Abhängigkeiten eines Konstruktors auf
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            
            if ($dependency !== null) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * Löst eine einzelne Abhängigkeit auf
     */
    private function resolveDependency(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            
            throw new Exception("Kann Abhängigkeit '{$parameter->getName()}' nicht auflösen");
        }

        if (!$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new Exception("Kann primitive Abhängigkeit '{$parameter->getName()}' nicht auflösen");
    }

    /**
     * Prüft ob ein Binding existiert
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Setzt eine konkrete Instanz
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Ruft eine Methode mit automatischer Dependency Injection auf
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && strpos($callback, '@') !== false) {
            [$class, $method] = explode('@', $callback);
            $callback = [$this->get($class), $method];
        }

        if (is_array($callback)) {
            $reflector = new \ReflectionMethod($callback[0], $callback[1]);
            $dependencies = $this->resolveDependencies($reflector->getParameters());
            return call_user_func_array($callback, array_merge($dependencies, $parameters));
        }

        return call_user_func_array($callback, $parameters);
    }
}