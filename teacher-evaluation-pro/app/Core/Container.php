<?php
/**
 * Service Container - Laravel-style dependency injection container
 */

declare(strict_types=1);

namespace TEP\Core;

use Closure;
use ReflectionClass;
use Exception;

class Container {
    
    /**
     * @var array<string, mixed>
     */
    private array $instances = [];
    
    /**
     * @var array<string, Closure|string>
     */
    private array $bindings = [];
    
    /**
     * @var array<string, mixed>
     */
    private array $singletons = [];
    
    /**
     * Bind a class or interface to a concrete implementation
     *
     * @param string $abstract
     * @param Closure|string|null $concrete
     * @return void
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): void {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = $concrete;
        unset($this->instances[$abstract]);
    }
    
    /**
     * Register a shared binding
     *
     * @param string $abstract
     * @param Closure|string|null $concrete
     * @return void
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void {
        $this->singletons[$abstract] = true;
        $this->bind($abstract, $concrete);
    }
    
    /**
     * Resolve a class or interface from the container
     *
     * @template T
     * @param class-string<T> $abstract
     * @param array<mixed> $parameters
     * @return T
     * @throws Exception
     */
    public function make(string $abstract, array $parameters = []): mixed {
        // Check if already resolved
        if (isset($this->instances[$abstract]) && isset($this->singletons[$abstract])) {
            return $this->instances[$abstract];
        }
        
        $concrete = $this->getConcrete($abstract);
        
        try {
            if ($concrete instanceof Closure) {
                $instance = $concrete($this, $parameters);
            } else {
                $instance = $this->build($concrete, $parameters);
            }
            
            if (isset($this->singletons[$abstract])) {
                $this->instances[$abstract] = $instance;
            }
            
            return $instance;
        } catch (Exception $e) {
            throw new Exception(
                sprintf('Target class [%s] is not instantiable: %s', $abstract, $e->getMessage()),
                0,
                $e
            );
        }
    }
    
    /**
     * Get the concrete implementation for an abstract type
     *
     * @param string $abstract
     * @return Closure|string
     */
    private function getConcrete(string $abstract): Closure|string {
        return $this->bindings[$abstract] ?? $abstract;
    }
    
    /**
     * Build a class instance with dependency injection
     *
     * @template T
     * @param class-string<T> $class
     * @param array<mixed> $parameters
     * @return T
     * @throws Exception
     */
    private function build(string $class, array $parameters = []): mixed {
        $reflector = new ReflectionClass($class);
        
        if (!$reflector->isInstantiable()) {
            throw new Exception("Target [$class] is not instantiable");
        }
        
        $constructor = $reflector->getConstructor();
        
        if ($constructor === null) {
            return new $class();
        }
        
        $dependencies = $constructor->getParameters();
        $resolvedDependencies = [];
        
        foreach ($dependencies as $dependency) {
            $type = $dependency->getType();
            
            if ($type === null || $type->isBuiltin()) {
                $resolvedDependencies[] = $parameters[$dependency->getName()] 
                    ?? ($dependency->isDefaultValueAvailable() ? $dependency->getDefaultValue() : null);
                continue;
            }
            
            $dependencyClass = $type->getName();
            $resolvedDependencies[] = $this->make($dependencyClass);
        }
        
        return $reflector->newInstanceArgs($resolvedDependencies);
    }
    
    /**
     * Set an instance in the container
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function set(string $abstract, mixed $instance): void {
        $this->instances[$abstract] = $instance;
    }
    
    /**
     * Check if a binding exists
     *
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
    
    /**
     * Remove a binding
     *
     * @param string $abstract
     * @return void
     */
    public function forget(string $abstract): void {
        unset($this->bindings[$abstract], $this->instances[$abstract], $this->singletons[$abstract]);
    }
    
    /**
     * Get all bindings
     *
     * @return array<string, Closure|string>
     */
    public function getBindings(): array {
        return $this->bindings;
    }
}
