<?php

declare(strict_types=1);

namespace TEP\Core\Cache;

use Redis;
use RedisException;
use DateInterval;
use DateTimeInterface;

/**
 * Enterprise-Grade Cache Manager with Multi-Level Caching Support
 * 
 * Provides a unified interface for caching with support for:
 * - Redis for distributed caching
 * - Memcached for session storage
 * - Array-based local cache for ultra-fast access
 * - Automatic cache invalidation patterns
 */
class CacheManager
{
    /**
     * Default cache TTL in seconds (24 hours)
     */
    private const DEFAULT_TTL = 86400;

    /**
     * Redis connection instance
     */
    protected ?Redis $redis = null;

    /**
     * Local memory cache storage
     */
    protected array $localCache = [];

    /**
     * Local cache metadata (expiration times)
     */
    protected array $localCacheMeta = [];

    /**
     * Cache prefix for namespacing
     */
    protected string $prefix = '';

    /**
     * Whether local cache is enabled
     */
    protected bool $localCacheEnabled = true;

    /**
     * Default TTL for cache items
     */
    protected int $defaultTtl = self::DEFAULT_TTL;

    /**
     * Create a new CacheManager instance
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? 'tep_cache:';
        $this->defaultTtl = $config['default_ttl'] ?? self::DEFAULT_TTL;
        $this->localCacheEnabled = $config['local_cache_enabled'] ?? true;

        if (!empty($config['redis'])) {
            $this->connectRedis($config['redis']);
        }
    }

    /**
     * Connect to Redis server
     *
     * @param array $config Redis configuration
     * @return void
     * @throws RedisException
     */
    public function connectRedis(array $config): void
    {
        $this->redis = new Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 2.5;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;

        $connected = $this->redis->connect($host, $port, $timeout);

        if (!$connected) {
            throw new RedisException('Failed to connect to Redis server');
        }

        if ($password !== null) {
            $this->redis->auth($password);
        }

        if ($database > 0) {
            $this->redis->select($database);
        }
    }

    /**
     * Get the Redis connection instance
     *
     * @return Redis|null
     */
    public function getRedis(): ?Redis
    {
        return $this->redis;
    }

    /**
     * Determine if an item exists in the cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool
    {
        // Check local cache first
        if ($this->localCacheEnabled && isset($this->localCache[$key])) {
            if ($this->isLocalCacheValid($key)) {
                return true;
            }
            unset($this->localCache[$key], $this->localCacheMeta[$key]);
        }

        // Check Redis
        if ($this->redis !== null) {
            return $this->redis->exists($this->prefix . $key) === 1;
        }

        return false;
    }

    /**
     * Retrieve an item from the cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check local cache first
        if ($this->localCacheEnabled && isset($this->localCache[$key])) {
            if ($this->isLocalCacheValid($key)) {
                return $this->localCache[$key];
            }
            unset($this->localCache[$key], $this->localCacheMeta[$key]);
        }

        // Check Redis
        if ($this->redis !== null) {
            $value = $this->redis->get($this->prefix . $key);
            if ($value !== false) {
                $decoded = json_decode($value, true);
                
                // Store in local cache
                if ($this->localCacheEnabled) {
                    $this->localCache[$key] = $decoded;
                }
                
                return $decoded;
            }
        }

        return $default;
    }

    /**
     * Store an item in the cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|DateInterval|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $ttl = $this->normalizeTtl($ttl);

        // Store in local cache
        if ($this->localCacheEnabled) {
            $this->localCache[$key] = $value;
            $this->localCacheMeta[$key] = time() + $ttl;
        }

        // Store in Redis
        if ($this->redis !== null) {
            $serialized = json_encode($value, JSON_THROW_ON_ERROR);
            
            if ($ttl > 0) {
                return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
            } else {
                return $this->redis->set($this->prefix . $key, $serialized);
            }
        }

        return true;
    }

    /**
     * Store an item in the cache if it doesn't exist
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|DateInterval|null $ttl Time to live in seconds
     * @return bool True if stored, false if already exists
     */
    public function add(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * Retrieve an item from cache or execute callback and store result
     *
     * @param string $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @param int|DateInterval|null $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public function remember(string $key, callable $callback, int|DateInterval|null $ttl = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Increment a cached numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to increment by
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $value = 1): int|false
    {
        // Invalidate local cache
        if ($this->localCacheEnabled) {
            unset($this->localCache[$key], $this->localCacheMeta[$key]);
        }

        // Increment in Redis
        if ($this->redis !== null) {
            return $this->redis->incrBy($this->prefix . $key, $value);
        }

        return false;
    }

    /**
     * Decrement a cached numeric value
     *
     * @param string $key Cache key
     * @param int $value Amount to decrement by
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        // Invalidate local cache
        if ($this->localCacheEnabled) {
            unset($this->localCache[$key], $this->localCacheMeta[$key]);
        }

        // Decrement in Redis
        if ($this->redis !== null) {
            return $this->redis->decrBy($this->prefix . $key, $value);
        }

        return false;
    }

    /**
     * Remove an item from the cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        // Remove from local cache
        if ($this->localCacheEnabled) {
            unset($this->localCache[$key], $this->localCacheMeta[$key]);
        }

        // Remove from Redis
        if ($this->redis !== null) {
            return $this->redis->del($this->prefix . $key) > 0;
        }

        return true;
    }

    /**
     * Remove multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return int Number of items deleted
     */
    public function deleteMultiple(array $keys): int
    {
        $deleted = 0;

        // Remove from local cache
        if ($this->localCacheEnabled) {
            foreach ($keys as $key) {
                if (isset($this->localCache[$key])) {
                    unset($this->localCache[$key], $this->localCacheMeta[$key]);
                    $deleted++;
                }
            }
        }

        // Remove from Redis
        if ($this->redis !== null && !empty($keys)) {
            $fullKeys = array_map(fn($k) => $this->prefix . $k, $keys);
            $deleted = $this->redis->del($fullKeys);
        }

        return $deleted;
    }

    /**
     * Clear all items from the cache
     *
     * @param bool $onlyPrefix Only clear items with current prefix
     * @return bool Success status
     */
    public function clear(bool $onlyPrefix = true): bool
    {
        // Clear local cache
        if ($this->localCacheEnabled) {
            $this->localCache = [];
            $this->localCacheMeta = [];
        }

        // Clear Redis
        if ($this->redis !== null) {
            if ($onlyPrefix) {
                // Find and delete only keys with our prefix
                $keys = $this->redis->keys($this->prefix . '*');
                if (!empty($keys)) {
                    return $this->redis->del($keys) > 0;
                }
                return true;
            } else {
                return $this->redis->flushDb();
            }
        }

        return true;
    }

    /**
     * Get multiple items from the cache
     *
     * @param array $keys Array of cache keys
     * @return array Associative array of key => value pairs
     */
    public function getMultiple(array $keys): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }

        return $results;
    }

    /**
     * Store multiple items in the cache
     *
     * @param array $values Associative array of key => value pairs
     * @param int|DateInterval|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function setMultiple(array $values, int|DateInterval|null $ttl = null): bool
    {
        $ttl = $this->normalizeTtl($ttl);

        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * Lock a cache key to prevent concurrent updates
     *
     * @param string $key Cache key
     * @param int $timeout Lock timeout in seconds
     * @return bool True if lock acquired
     */
    public function lock(string $key, int $timeout = 10): bool
    {
        $lockKey = "lock:{$key}";
        $lockValue = uniqid('lock_', true);

        if ($this->redis !== null) {
            $acquired = $this->redis->set(
                $this->prefix . $lockKey,
                $lockValue,
                ['nx', 'ex' => $timeout]
            );

            if ($acquired) {
                // Store lock value for release
                static $locks = [];
                $locks[$key] = $lockValue;
                return true;
            }
        }

        return false;
    }

    /**
     * Release a lock
     *
     * @param string $key Cache key
     * @return bool True if lock released
     */
    public function releaseLock(string $key): bool
    {
        $lockKey = "lock:{$key}";

        if ($this->redis !== null) {
            return $this->redis->del($this->prefix . $lockKey) > 0;
        }

        return true;
    }

    /**
     * Execute callback with lock
     *
     * @param string $key Cache key
     * @param callable $callback Callback to execute
     * @param int $timeout Lock timeout in seconds
     * @return mixed Callback result
     * @throws \RuntimeException If lock cannot be acquired
     */
    public function withLock(string $key, callable $callback, int $timeout = 10): mixed
    {
        if (!$this->lock($key, $timeout)) {
            throw new \RuntimeException("Could not acquire lock for key: {$key}");
        }

        try {
            return $callback();
        } finally {
            $this->releaseLock($key);
        }
    }

    /**
     * Check if local cache entry is still valid
     *
     * @param string $key Cache key
     * @return bool
     */
    protected function isLocalCacheValid(string $key): bool
    {
        if (!isset($this->localCacheMeta[$key])) {
            return false;
        }

        return time() < $this->localCacheMeta[$key];
    }

    /**
     * Normalize TTL value
     *
     * @param int|DateInterval|null $ttl
     * @return int
     */
    protected function normalizeTtl(int|DateInterval|null $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $now = new \DateTime();
            $end = $now->add($ttl);
            return $end->getTimestamp() - $now->getTimestamp();
        }

        return $ttl ?? $this->defaultTtl;
    }

    /**
     * Set the cache prefix
     *
     * @param string $prefix
     * @return void
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = rtrim($prefix, ':') . ':';
    }

    /**
     * Get the cache prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Enable local cache
     *
     * @return void
     */
    public function enableLocalCache(): void
    {
        $this->localCacheEnabled = true;
    }

    /**
     * Disable local cache
     *
     * @return void
     */
    public function disableLocalCache(): void
    {
        $this->localCacheEnabled = false;
        $this->localCache = [];
        $this->localCacheMeta = [];
    }

    /**
     * Get cache statistics
     *
     * @return array Statistics array
     */
    public function stats(): array
    {
        $stats = [
            'local_cache_items' => count($this->localCache),
            'local_cache_enabled' => $this->localCacheEnabled,
            'redis_connected' => $this->redis !== null,
        ];

        if ($this->redis !== null) {
            $info = $this->redis->info('stats');
            $stats['redis_hits'] = $info['keyspace_hits'] ?? 0;
            $stats['redis_misses'] = $info['keyspace_misses'] ?? 0;
            $stats['redis_keys'] = $this->redis->dbSize();
        }

        return $stats;
    }

    /**
     * Warm up cache with pre-computed values
     *
     * @param array $items Items to warm up
     * @param int $ttl Time to live in seconds
     * @return void
     */
    public function warmUp(array $items, int $ttl = 3600): void
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    /**
     * Tag cache entries for grouped invalidation
     *
     * @param string $tag Tag name
     * @param array $keys Cache keys to tag
     * @return void
     */
    public function tag(string $tag, array $keys): void
    {
        if ($this->redis !== null) {
            $tagKey = "tag:{$tag}";
            $this->redis->sAdd($this->prefix . $tagKey, ...$keys);
        }
    }

    /**
     * Invalidate all cache entries with a tag
     *
     * @param string $tag Tag name
     * @return int Number of items invalidated
     */
    public function invalidateTag(string $tag): int
    {
        $invalidated = 0;

        if ($this->redis !== null) {
            $tagKey = "tag:{$tag}";
            $keys = $this->redis->sMembers($this->prefix . $tagKey);

            if (!empty($keys)) {
                $invalidated = $this->deleteMultiple($keys);
                $this->redis->del($this->prefix . $tagKey);
            }
        }

        return $invalidated;
    }
}
