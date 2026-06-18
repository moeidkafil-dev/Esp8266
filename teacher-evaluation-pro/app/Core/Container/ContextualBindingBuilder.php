<?php

declare(strict_types=1);

namespace TEP\Core\Container;

use Closure;

/**
 * Contextual Binding Builder for Dependency Injection Container
 * 
 * Provides a fluent interface for defining contextual bindings,
 * allowing different implementations to be injected based on the consuming class.
 */
class ContextualBindingBuilder
{
    /**
     * The underlying container instance
     * 
     * @var Container
     */
    protected Container $container;

    /**
     * The concrete class or service being configured
     * 
     * @var string
     */
    protected string $concrete;

    /**
     * Create a new contextual binding builder
     *
     * @param Container $container
     * @param string $concrete
     */
    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Define the abstract dependency to be resolved contextually
     *
     * @param string $abstract
     * @return self
     */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;
        return $this;
    }

    /**
     * Give the specified implementation for the contextual dependency
     *
     * @param Closure|string $implementation
     * @return void
     */
    public function give(Closure|string $implementation): void
    {
        if ($implementation instanceof Closure) {
            $this->container->contextual[$this->concrete][$this->abstract] = $implementation;
        } else {
            $this->container->contextual[$this->concrete][$this->abstract] = fn () => $this->container->make($implementation);
        }
    }

    /**
     * Give the specified tag's implementations for the contextual dependency
     *
     * @param string $tag
     * @return void
     */
    public function giveTagged(string $tag): void
    {
        $this->give(fn () => $this->container->tagged($tag));
    }

    /**
     * Give a service from the container by its ID
     *
     * @param string $id
     * @return void
     */
    public function giveService(string $id): void
    {
        $this->give($id);
    }

    /**
     * Give a value instance for the contextual dependency
     *
     * @param mixed $value
     * @return void
     */
    public function giveInstance(mixed $value): void
    {
        $this->give(fn () => $value);
    }

    /**
     * Give a lazy-loaded implementation
     *
     * @param Closure $factory
     * @return void
     */
    public function giveLazy(Closure $factory): void
    {
        $this->give($factory);
    }
}
