<?php
/**
 * Event Manager - Event-driven architecture implementation
 */

declare(strict_types=1);

namespace TEP\Core;

use Closure;
use Exception;

class EventManager {
    
    /**
     * @var array<string, array<Closure>>
     */
    private array $listeners = [];
    
    /**
     * @var array<string, mixed>
     */
    private array $wildcards = [];
    
    /**
     * @var bool
     */
    private bool $isDispatching = false;
    
    /**
     * Subscribe to an event
     *
     * @param string $event
     * @param Closure $listener
     * @param int $priority
     * @return void
     */
    public function listen(string $event, Closure $listener, int $priority = 10): void {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        // Store listener with priority
        $this->listeners[$event][] = [
            'listener' => $listener,
            'priority' => $priority,
        ];
        
        // Sort by priority
        usort($this->listeners[$event], function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    }
    
    /**
     * Subscribe to all events (wildcard)
     *
     * @param Closure $listener
     * @return void
     */
    public function listenAll(Closure $listener): void {
        $this->wildcards[] = $listener;
    }
    
    /**
     * Dispatch an event
     *
     * @param string $event
     * @param array<mixed> $payload
     * @param bool $halt
     * @return array<mixed>|mixed|null
     */
    public function dispatch(string $event, array $payload = [], bool $halt = false): array|mixed|null {
        $this->isDispatching = true;
        
        $responses = [];
        
        try {
            // Call wildcard listeners
            foreach ($this->wildcards as $listener) {
                $response = $listener($event, $payload);
                
                if ($halt && $response !== null) {
                    return $response;
                }
                
                if ($response !== null) {
                    $responses[] = $response;
                }
            }
            
            // Call specific event listeners
            if (isset($this->listeners[$event])) {
                foreach ($this->listeners[$event] as $listenerData) {
                    $response = $listenerData['listener'](...$payload);
                    
                    if ($halt && $response !== null) {
                        return $response;
                    }
                    
                    if ($response !== null) {
                        $responses[] = $response;
                    }
                }
            }
            
            // Call wildcard pattern listeners (e.g., evaluation.*)
            foreach ($this->listeners as $pattern => $listeners) {
                if ($this->matchesPattern($pattern, $event)) {
                    foreach ($listeners as $listenerData) {
                        $response = $listenerData['listener'](...$payload);
                        
                        if ($halt && $response !== null) {
                            return $response;
                        }
                        
                        if ($response !== null) {
                            $responses[] = $response;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('TEP Event Dispatch Error: ' . $e->getMessage());
            throw $e;
        } finally {
            $this->isDispatching = false;
        }
        
        return $halt ? null : $responses;
    }
    
    /**
     * Dispatch until first non-null response
     *
     * @param string $event
     * @param array<mixed> $payload
     * @return mixed|null
     */
    public function until(string $event, array $payload = []): mixed {
        return $this->dispatch($event, $payload, true);
    }
    
    /**
     * Check if an event has listeners
     *
     * @param string $event
     * @return bool
     */
    public function hasListeners(string $event): bool {
        return isset($this->listeners[$event]) && !empty($this->listeners[$event]);
    }
    
    /**
     * Remove a listener
     *
     * @param string $event
     * @param Closure $listener
     * @return void
     */
    public function forget(string $event, Closure $listener): void {
        if (!isset($this->listeners[$event])) {
            return;
        }
        
        $this->listeners[$event] = array_filter(
            $this->listeners[$event],
            fn($item) => $item['listener'] !== $listener
        );
    }
    
    /**
     * Remove all listeners for an event
     *
     * @param string $event
     * @return void
     */
    public function flush(string $event): void {
        unset($this->listeners[$event]);
    }
    
    /**
     * Get all listeners for an event
     *
     * @param string $event
     * @return array<Closure>
     */
    public function getListeners(string $event): array {
        if (!isset($this->listeners[$event])) {
            return [];
        }
        
        return array_map(fn($item) => $item['listener'], $this->listeners[$event]);
    }
    
    /**
     * Subscribe multiple events at once
     *
     * @param array<string, array<Closure>> $events
     * @return void
     */
    public function subscribe(array $events): void {
        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                $this->listen($event, $listener);
            }
        }
    }
    
    /**
     * Create and dispatch an event object
     *
     * @param object $eventObject
     * @return mixed
     */
    public function dispatchObject(object $eventObject): mixed {
        $eventName = get_class($eventObject);
        return $this->dispatch($eventName, [$eventObject]);
    }
    
    /**
     * Check if currently dispatching
     *
     * @return bool
     */
    public function isDispatching(): bool {
        return $this->isDispatching;
    }
    
    /**
     * Get all registered events
     *
     * @return array<string>
     */
    public function getEvents(): array {
        return array_keys($this->listeners);
    }
    
    /**
     * Clear all listeners
     *
     * @return void
     */
    public function clear(): void {
        $this->listeners = [];
        $this->wildcards = [];
    }
    
    /**
     * Check if event name matches pattern
     *
     * @param string $pattern
     * @param string $event
     * @return bool
     */
    private function matchesPattern(string $pattern, string $event): bool {
        // Convert pattern to regex
        $regex = str_replace(['\\*', '.'], ['.*', '\\.'], preg_quote($pattern, '/'));
        return (bool) preg_match('/^' . $regex . '$/', $event);
    }
}

// Helper function for global access
function tep_event(): EventManager {
    static $instance = null;
    
    if ($instance === null) {
        $instance = new EventManager();
    }
    
    return $instance;
}
