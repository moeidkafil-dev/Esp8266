<?php

declare(strict_types=1);

namespace TEP\Core\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Exception;

/**
 * PSR-11 Compliant Dependency Injection Container
 * 
 * Implements a powerful service container with automatic dependency resolution,
 * singleton management, and contextual binding capabilities.
 */
class Container implements \Psr\Container\ContainerInterface
{
    /**
     * @var array<string, mixed> The stack of services currently being resolved
     */
    protected array $buildStack = [];

    /**
     * @var array<string, mixed> Registered shared instances (singletons)
     */
    protected array $instances = [];

    /**
     * @var array<string, array> Registered aliases for abstract types
     */
    protected array $aliases = [];

    /**
     * @var array<string, bool> Resolved instance tracking
     */
    protected array $resolved = [];

    /**
     * @var array<string, Closure|string> Registered bindings
     */
    protected array $bindings = [];

    /**
     * @var array<string, array> Contextual bindings for specific implementations
     */
    protected array $contextual = [];

    /**
     * @var array<string, Closure> Registered extenders for modifying resolved instances
     */
    protected array $extenders = [];

    /**
     * @var array<string, string> Tagged services for grouped resolution
     */
    protected array $tags = [];

    /**
     * @var array<string, mixed> Rebound method handlers
     */
    protected array $reboundCallbacks = [];

    /**
     * Global resolution callback
     * 
     * @var array<Closure>
     */
    protected array $globalResolvingCallbacks = [];

    /**
     * Type-specific resolution callbacks
     * 
     * @var array<string, array<Closure>>
     */
    protected array $resolvingCallbacks = [];

    /**
     * Global after-resolution callbacks
     * 
     * @var array<Closure>
     */
    protected array $globalAfterResolvingCallbacks = [];

    /**
     * Type-specific after-resolution callbacks
     * 
     * @var array<string, array<Closure>>
     */
    protected array $afterResolvingCallbacks = [];

    /**
     * Determine if the given abstract type has been bound
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
               isset($this->instances[$abstract]) ||
               $this->isAlias($abstract);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Determine if the given abstract type has been resolved
     *
     * @param string $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        return isset($this->resolved[$abstract]);
    }

    /**
     * Determine if a given string is an alias
     *
     * @param string $name
     * @return bool
     */
    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register a binding with the container
     *
     * @param string|Closure $abstract
     * @param Closure|string|null $concrete
     * @param bool $shared
     * @return void
     */
    public function bind(string|Closure $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances();

        // If no concrete is provided, we'll assume the abstract is the concrete
        if ($concrete === null) {
            $concrete = $abstract;
        }

        // Normalize the abstract to a string key
        if ($abstract instanceof Closure) {
            $abstract = $this->getClosureIdentifier($abstract);
        }

        // If the concrete is not a closure, we need to normalize it
        if (!$concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        // Remove any existing binding
        unset($this->aliases[$abstract]);

        // Store the binding
        $this->bindings[$abstract] = compact('concrete', 'shared');

        // Fire rebound callbacks if the abstract was already resolved
        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * Register a shared binding in the container
     *
     * @param string|Closure $abstract
     * @param Closure|string|null $concrete
     * @return void
     */
    public function singleton(string|Closure $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register a class as a singleton
     *
     * @param string $abstract
     * @param string|null $concrete
     * @return void
     */
    public function singletonIf(string $abstract, ?string $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    /**
     * Extend an abstract type with additional functionality
     *
     * @param string $abstract
     * @param Closure $closure
     * @return void
     */
    public function extend(string $abstract, Closure $closure): void
    {
        if (!isset($this->instances[$abstract])) {
            throw new Exception("Cannot extend non-existent binding: {$abstract}");
        }

        $this->extenders[$abstract][] = $closure;

        if ($this->resolved($abstract)) {
            $this->instances[$abstract] = $this->resolveInstance($abstract);
        }
    }

    /**
     * Register an existing instance as shared in the container
     *
     * @param string $abstract
     * @param mixed $instance
     * @return mixed
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->removeAbstractAlias($abstract);

        $isBound = $this->bound($abstract);

        unset($this->aliases[$abstract], $this->bindings[$abstract]);

        $this->instances[$abstract] = $instance;

        $this->resolved[$abstract] = true;

        if ($isBound) {
            $this->rebound($abstract);
        }

        return $instance;
    }

    /**
     * Assign a set of tags to their respective bindings
     *
     * @param array|string $abstracts
     * @param array|mixed ...$tags
     * @return void
     */
    public function tag(array|string $abstracts, array ...$tags): void
    {
        foreach ($tags as $tag) {
            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve all bindings for a given tag
     *
     * @param string $tag
     * @return array
     */
    public function tagged(string $tag): array
    {
        $results = [];

        foreach ($this->tags[$tag] ?? [] as $abstract) {
            $results[] = $this->make($abstract);
        }

        return $results;
    }

    /**
     * Alias a type to a different name
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new Exception("Cannot alias [$abstract] to itself.");
        }

        $this->aliases[$alias] = $abstract;
    }

    /**
     * Bind a new contextual binding into the container
     *
     * @param string $concrete
     * @return ContextualBindingBuilder
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * Resolve the given type from the container
     *
     * @param string|Closure $abstract
     * @param array $parameters
     * @return mixed
     */
    public function make(string|Closure $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Resolve the given type from the container
     *
     * @param string|Closure $abstract
     * @param array $parameters
     * @return mixed
     * @throws Exception
     */
    protected function resolve(string|Closure $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($this->getConcrete($abstract));

        $needsContextualBuild = !empty($parameters) || 
                               !is_null($this->getContextualConcrete($abstract));

        // If an instance is already registered, use it
        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        // Check for circular dependencies
        $this->buildStack[] = $abstract;

        try {
            $concrete = $this->getConcrete($abstract);

            // If we have a binding, resolve it
            if ($this->isBindable($concrete)) {
                $object = $this->resolveFromBinding(
                    $this->bindings[$abstract]['concrete'],
                    $parameters
                );
            } else {
                $object = $this->resolveFromClass($concrete, $parameters);
            }

            // Apply extenders
            $object = $this->applyExtenders($abstract, $object);

            // Fire resolving callbacks
            $this->fireResolvingCallbacks($abstract, $object);

            // Mark as resolved
            $this->resolved[$abstract] = true;

            // Store shared instance if applicable
            if (isset($this->bindings[$abstract]['shared']) && 
                $this->bindings[$abstract]['shared']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        } finally {
            array_pop($this->buildStack);
        }
    }

    /**
     * Get the concrete type for a given abstract
     *
     * @param string|Closure $abstract
     * @return string|Closure
     */
    protected function getConcrete(string|Closure $abstract): string|Closure
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Get the contextual concrete for a given abstract
     *
     * @param string $abstract
     * @return string|Closure|null
     */
    protected function getContextualConcrete(string $abstract): ?string|?Closure
    {
        if (!is_null($binding = $this->findInContext($abstract))) {
            return $binding;
        }

        return null;
    }

    /**
     * Find the contextual binding for a given abstract
     *
     * @param string $abstract
     * @return Closure|null
     */
    protected function findInContext(string $abstract): ?Closure
    {
        foreach (array_reverse($this->buildStack) as $concrete) {
            if (isset($this->contextual[$concrete][$abstract])) {
                return $this->contextual[$concrete][$abstract];
            }
        }

        return null;
    }

    /**
     * Resolve a dependency from a binding
     *
     * @param Closure $binding
     * @param array $parameters
     * @return mixed
     */
    protected function resolveFromBinding(Closure $binding, array $parameters): mixed
    {
        return $binding($this, $parameters);
    }

    /**
     * Resolve a dependency from a class name
     *
     * @param string $class
     * @param array $parameters
     * @return object
     * @throws ReflectionException
     * @throws Exception
     */
    protected function resolveFromClass(string $class, array $parameters): object
    {
        if (!class_exists($class)) {
            throw new Exception("Target class [{$class}] does not exist.");
        }

        $reflector = new ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Target [{$class}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $class;
        }

        $dependencies = $constructor->getParameters();

        try {
            $instances = $this->resolveDependencies($dependencies, $parameters);
            return $reflector->newInstanceArgs($instances);
        } catch (Exception $e) {
            throw new Exception("Unresolvable dependency [{$e->getMessage()}] in class [{$class}]");
        }
    }

    /**
     * Resolve all dependencies for a constructor
     *
     * @param array $dependencies
     * @param array $parameters
     * @return array
     */
    protected function resolveDependencies(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            // Use provided parameter if available
            if (array_key_exists($dependency->getName(), $parameters)) {
                $results[] = $parameters[$dependency->getName()];
                continue;
            }

            // Try to resolve from context or container
            $result = $this->resolveDependency($dependency);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Resolve a single dependency
     *
     * @param ReflectionParameter $dependency
     * @return mixed
     */
    protected function resolveDependency(ReflectionParameter $dependency): mixed
    {
        if ($dependency->isVariadic()) {
            return [];
        }

        // Check for contextual binding
        $contextual = $this->getContextualConcrete($dependency->getType()?->getName() ?? '');
        if ($contextual !== null) {
            return $this->resolve($contextual);
        }

        // Try to resolve from container
        $type = $dependency->getType();
        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if ($this->bound($typeName)) {
                return $this->make($typeName);
            }
        }

        // Use default value if available
        if ($dependency->isDefaultValueAvailable()) {
            return $dependency->getDefaultValue();
        }

        // Allow nullable types
        if ($dependency->allowsNull()) {
            return null;
        }

        throw new Exception("Unresolvable dependency: {$dependency->getName()}");
    }

    /**
     * Apply extender closures to a resolved instance
     *
     * @param string $abstract
     * @param mixed $object
     * @return mixed
     */
    protected function applyExtenders(string $abstract, mixed $object): mixed
    {
        if (!isset($this->extenders[$abstract])) {
            return $object;
        }

        foreach ($this->extenders[$abstract] as $extender) {
            $object = $extender($object, $this);
        }

        return $object;
    }

    /**
     * Fire the resolving callbacks for a given type
     *
     * @param string $abstract
     * @param mixed $object
     * @return void
     */
    protected function fireResolvingCallbacks(string $abstract, mixed $object): void
    {
        // Fire global callbacks
        foreach ($this->globalResolvingCallbacks as $callback) {
            $callback($object, $this);
        }

        // Fire type-specific callbacks
        foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($object, $this);
        }

        // Fire global after-resolving callbacks
        foreach ($this->globalAfterResolvingCallbacks as $callback) {
            $callback($object, $this);
        }

        // Fire type-specific after-resolving callbacks
        foreach ($this->afterResolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($object, $this);
        }
    }

    /**
     * Register a resolving callback
     *
     * @param string $abstract
     * @param Closure $callback
     * @return void
     */
    public function resolving(string $abstract, Closure $callback): void
    {
        $this->resolvingCallbacks[$this->getAlias($abstract)][] = $callback;
    }

    /**
     * Register an after-resolving callback
     *
     * @param string $abstract
     * @param Closure $callback
     * @return void
     */
    public function afterResolving(string $abstract, Closure $callback): void
    {
        $this->afterResolvingCallbacks[$this->getAlias($abstract)][] = $callback;
    }

    /**
     * Register a global resolving callback
     *
     * @param Closure $callback
     * @return void
     */
    public function globalResolvingCallback(Closure $callback): void
    {
        $this->globalResolvingCallbacks[] = $callback;
    }

    /**
     * Register a global after-resolving callback
     *
     * @param Closure $callback
     * @return void
     */
    public function globalAfterResolvingCallback(Closure $callback): void
    {
        $this->globalAfterResolvingCallbacks[] = $callback;
    }

    /**
     * Get the alias for an abstract type
     *
     * @param string $abstract
     * @return string
     */
    protected function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Remove an alias for an abstract type
     *
     * @param string $abstract
     * @return void
     */
    protected function removeAbstractAlias(string $abstract): void
    {
        foreach ($this->aliases as $alias => $value) {
            if ($value === $abstract) {
                unset($this->aliases[$alias]);
            }
        }
    }

    /**
     * Drop stale instances from the container
     *
     * @return void
     */
    protected function dropStaleInstances(): void
    {
        // Implementation for cleaning up stale instances
    }

    /**
     * Get a closure identifier
     *
     * @param Closure $closure
     * @return string
     */
    protected function getClosureIdentifier(Closure $closure): string
    {
        return spl_object_hash($closure);
    }

    /**
     * Get a closure for a concrete type
     *
     * @param string $abstract
     * @param string $concrete
     * @return Closure
     */
    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return fn () => $this->resolveFromClass($concrete, []);
    }

    /**
     * Determine if a concrete is bindable
     *
     * @param string|Closure $concrete
     * @return bool
     */
    protected function isBindable(string|Closure $concrete): bool
    {
        return is_string($concrete) && isset($this->bindings[$concrete]);
    }

    /**
     * Fire the rebound callbacks for a given abstract
     *
     * @param string $abstract
     * @return void
     */
    protected function rebound(string $abstract): void
    {
        $instance = $this->make($abstract);

        foreach ($this->reboundCallbacks[$abstract] ?? [] as $callback) {
            $callback($this, $instance);
        }
    }

    /**
     * Flush the container of all bindings and resolved instances
     *
     * @return void
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->resolved = [];
        $this->bindings = [];
        $this->instances = [];
        $this->extenders = [];
        $this->buildStack = [];
        $this->tags = [];
        $this->contextual = [];
        $this->reboundCallbacks = [];
        $this->resolvingCallbacks = [];
        $this->afterResolvingCallbacks = [];
        $this->globalResolvingCallbacks = [];
        $this->globalAfterResolvingCallbacks = [];
    }

    /**
     * Get the container's current state as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'bindings' => array_keys($this->bindings),
            'instances' => array_keys($this->instances),
            'aliases' => $this->aliases,
            'resolved' => array_keys($this->resolved),
            'tags' => $this->tags,
        ];
    }
}
