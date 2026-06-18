<?php

declare(strict_types=1);

namespace TEP\Core\Queue;

use Redis;
use RedisException;
use JsonException;
use DateTime;
use InvalidArgumentException;

/**
 * Enterprise-Grade Queue Manager with Redis Backend
 * 
 * Provides robust job queue management with support for:
 * - Priority queues
 * - Delayed jobs
 * - Job retries with exponential backoff
 * - Dead letter queue for failed jobs
 * - Rate limiting and throttling
 * - Batch processing
 */
class QueueManager
{
    /**
     * Default queue name
     */
    private const DEFAULT_QUEUE = 'default';

    /**
     * Maximum retry attempts before moving to dead letter queue
     */
    private const MAX_RETRIES = 3;

    /**
     * Base delay between retries in seconds
     */
    private const RETRY_DELAY_BASE = 60;

    /**
     * Default job timeout in seconds
     */
    private const DEFAULT_TIMEOUT = 300;

    /**
     * Redis connection instance
     */
    protected ?Redis $redis = null;

    /**
     * Queue prefix for namespacing
     */
    protected string $prefix = '';

    /**
     * Connected worker processes
     */
    protected array $workers = [];

    /**
     * Queue statistics
     */
    protected array $stats = [];

    /**
     * Create a new QueueManager instance
     *
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->prefix = $config['prefix'] ?? 'tep_queue:';
        
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
     * Push a job onto the queue
     *
     * @param string $queue Queue name
     * @param array $job Job payload
     * @param int $priority Priority (higher = more urgent)
     * @param int $delay Delay in seconds before job becomes available
     * @return string Job ID
     * @throws JsonException
     */
    public function push(
        string $queue = self::DEFAULT_QUEUE,
        array $job = [],
        int $priority = 0,
        int $delay = 0
    ): string {
        $jobId = $this->generateJobId();
        $timestamp = time();

        $jobData = [
            'id' => $jobId,
            'queue' => $queue,
            'payload' => $job,
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => self::MAX_RETRIES,
            'created_at' => $timestamp,
            'available_at' => $timestamp + $delay,
            'timeout' => self::DEFAULT_TIMEOUT,
            'status' => 'pending',
        ];

        $serialized = json_encode($jobData, JSON_THROW_ON_ERROR);

        if ($delay > 0) {
            // Add to delayed queue
            $this->redis->zAdd($this->key('delayed'), $timestamp + $delay, $serialized);
        } else {
            // Add to priority queue
            $this->redis->zAdd($this->key("queue:{$queue}"), -$priority, $serialized);
        }

        $this->incrementStat('pushed');

        return $jobId;
    }

    /**
     * Pop a job from the queue
     *
     * @param string $queue Queue name
     * @param int $timeout Block timeout in seconds
     * @return array|null Job data or null if no job available
     */
    public function pop(string $queue = self::DEFAULT_QUEUE, int $timeout = 1): ?array
    {
        // First, move any ready delayed jobs to their queues
        $this->processDelayedJobs();

        // Try to get a job from the queue
        $result = $this->redis->zPopMin($this->key("queue:{$queue}"));

        if ($result === false || empty($result)) {
            // Use blocking pop with timeout
            $result = $this->redis->blPop($this->key("queue:{$queue}"), $timeout);
            
            if ($result === false || empty($result)) {
                return null;
            }
        }

        $jobData = json_decode($result[1] ?? '', true);

        if ($jobData === null) {
            return null;
        }

        // Mark job as processing
        $jobData['status'] = 'processing';
        $jobData['started_at'] = time();
        $jobData['worker'] = gethostname() . ':' . getmypid();

        $this->redis->setex(
            $this->key("job:{$jobData['id']}"),
            $jobData['timeout'],
            json_encode($jobData, JSON_THROW_ON_ERROR)
        );

        $this->incrementStat('processed');

        return $jobData;
    }

    /**
     * Acknowledge successful job completion
     *
     * @param string $jobId Job ID
     * @return bool Success status
     */
    public function acknowledge(string $jobId): bool
    {
        $jobKey = $this->key("job:{$jobId}");
        $jobData = $this->redis->get($jobKey);

        if ($jobData === false) {
            return false;
        }

        $job = json_decode($jobData, true);
        
        // Remove job tracking
        $this->redis->del($jobKey);

        // Archive completed job
        $job['status'] = 'completed';
        $job['completed_at'] = time();
        
        $this->redis->lPush(
            $this->key('archive:completed'),
            json_encode($job, JSON_THROW_ON_ERROR)
        );
        $this->redis->lTrim($this->key('archive:completed'), 0, 9999);

        $this->incrementStat('acknowledged');

        return true;
    }

    /**
     * Release a job back to the queue (for retry)
     *
     * @param string $jobId Job ID
     * @param int $delay Delay before retry in seconds
     * @param bool $incrementAttempt Whether to increment attempt counter
     * @return bool Success status
     */
    public function release(string $jobId, int $delay = 0, bool $incrementAttempt = true): bool
    {
        $jobKey = $this->key("job:{$jobId}");
        $jobData = $this->redis->get($jobKey);

        if ($jobData === false) {
            return false;
        }

        $job = json_decode($jobData, true);

        if ($incrementAttempt) {
            $job['attempts']++;
        }

        // Check if max attempts exceeded
        if ($job['attempts'] >= $job['max_attempts']) {
            return $this->moveToDeadLetter($job);
        }

        // Calculate retry delay with exponential backoff
        $retryDelay = $delay > 0 ? $delay : (self::RETRY_DELAY_BASE * pow(2, $job['attempts'] - 1));

        $job['status'] = 'pending';
        $job['available_at'] = time() + $retryDelay;
        unset($job['started_at'], $job['worker']);

        // Add back to queue
        $serialized = json_encode($job, JSON_THROW_ON_ERROR);
        
        if ($retryDelay > 0) {
            $this->redis->zAdd($this->key('delayed'), time() + $retryDelay, $serialized);
        } else {
            $this->redis->zAdd(
                $this->key("queue:{$job['queue']}"),
                -$job['priority'],
                $serialized
            );
        }

        $this->redis->del($jobKey);
        $this->incrementStat('released');

        return true;
    }

    /**
     * Move a job to the dead letter queue
     *
     * @param array $job Job data
     * @return bool Success status
     */
    protected function moveToDeadLetter(array $job): bool
    {
        $job['status'] = 'dead';
        $job['dead_at'] = time();
        $job['failure_reason'] = 'Max retry attempts exceeded';

        $this->redis->lPush(
            $this->key('dead'),
            json_encode($job, JSON_THROW_ON_ERROR)
        );
        $this->redis->lTrim($this->key('dead'), 0, 9999);

        $this->incrementStat('dead');

        return true;
    }

    /**
     * Process delayed jobs that are now ready
     *
     * @return int Number of jobs processed
     */
    public function processDelayedJobs(): int
    {
        $now = time();
        $processed = 0;

        // Get all ready delayed jobs
        $readyJobs = $this->redis->zRangeByScore($this->key('delayed'), 0, $now, ['limit' => [0, 100]]);

        foreach ($readyJobs as $jobData) {
            $job = json_decode($jobData, true);
            
            if ($job !== null) {
                $this->redis->zAdd(
                    $this->key("queue:{$job['queue']}"),
                    -$job['priority'],
                    $jobData
                );
                $this->redis->zRem($this->key('delayed'), $jobData);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Get queue statistics
     *
     * @param string|null $queue Specific queue or null for all
     * @return array Statistics
     */
    public function stats(?string $queue = null): array
    {
        $stats = [
            'queues' => [],
            'total_pending' => 0,
            'total_processing' => 0,
            'total_delayed' => 0,
            'total_dead' => 0,
            'lifetime' => $this->stats,
        ];

        if ($queue !== null) {
            $queues = [$queue];
        } else {
            // Get all queue names
            $keys = $this->redis->keys($this->key('queue:*'));
            $queues = array_map(fn($k) => str_replace($this->key('queue:'), '', $k), $keys);
        }

        foreach ($queues as $q) {
            $pending = $this->redis->zCard($this->key("queue:{$q}"));
            $stats['queues'][$q] = [
                'pending' => $pending,
            ];
            $stats['total_pending'] += $pending;
        }

        $stats['total_delayed'] = $this->redis->zCard($this->key('delayed'));
        $stats['total_dead'] = $this->redis->lLen($this->key('dead'));

        return $stats;
    }

    /**
     * Retry all jobs in the dead letter queue
     *
     * @param string|null $queue Specific queue or null for all
     * @return int Number of jobs retried
     */
    public function retryDead(?string $queue = null): int
    {
        $retried = 0;
        $deadQueue = $this->key('dead');

        while (($jobData = $this->redis->rPop($deadQueue)) !== false) {
            $job = json_decode($jobData, true);

            if ($queue === null || $job['queue'] === $queue) {
                $job['attempts'] = 0;
                $job['status'] = 'pending';
                $job['available_at'] = time();
                unset($job['dead_at'], $job['failure_reason']);

                $this->redis->zAdd(
                    $this->key("queue:{$job['queue']}"),
                    -$job['priority'],
                    json_encode($job, JSON_THROW_ON_ERROR)
                );
                $retried++;
            } else {
                // Put it back
                $this->redis->lPush($deadQueue, $jobData);
                break;
            }
        }

        return $retried;
    }

    /**
     * Purge all jobs from a queue
     *
     * @param string $queue Queue name
     * @return int Number of jobs purged
     */
    public function purge(string $queue = self::DEFAULT_QUEUE): int
    {
        $count = $this->redis->zCard($this->key("queue:{$queue}"));
        $this->redis->del($this->key("queue:{$queue}"));
        
        return $count;
    }

    /**
     * Clear the entire queue system
     *
     * @return void
     */
    public function clear(): void
    {
        $keys = $this->redis->keys($this->prefix . '*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }

    /**
     * Generate a unique job ID
     *
     * @return string
     */
    protected function generateJobId(): string
    {
        return uniqid('job_', true) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Build a namespaced key
     *
     * @param string $key Key name
     * @return string
     */
    protected function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Increment a statistic counter
     *
     * @param string $stat Stat name
     * @return int New value
     */
    protected function incrementStat(string $stat): int
    {
        $this->stats[$stat] = ($this->stats[$stat] ?? 0) + 1;
        return $this->stats[$stat];
    }

    /**
     * Get jobs from dead letter queue
     *
     * @param int $limit Maximum number of jobs to retrieve
     * @return array Jobs
     */
    public function getDeadJobs(int $limit = 100): array
    {
        $jobs = $this->redis->lRange($this->key('dead'), 0, $limit - 1);
        
        return array_map(fn($j) => json_decode($j, true), $jobs);
    }

    /**
     * Delete a specific job from dead letter queue
     *
     * @param string $jobId Job ID
     * @return bool Success status
     */
    public function deleteDeadJob(string $jobId): bool
    {
        $deadJobs = $this->getDeadJobs(10000);
        
        foreach ($deadJobs as $index => $job) {
            if ($job['id'] === $jobId) {
                $this->redis->lRem($this->key('dead'), 1, json_encode($job, JSON_THROW_ON_ERROR));
                return true;
            }
        }

        return false;
    }

    /**
     * Schedule a job for future execution
     *
     * @param DateTime $runAt When to run the job
     * @param string $queue Queue name
     * @param array $job Job payload
     * @param int $priority Priority
     * @return string Job ID
     */
    public function schedule(DateTime $runAt, string $queue = self::DEFAULT_QUEUE, array $job = [], int $priority = 0): string
    {
        $delay = max(0, $runAt->getTimestamp() - time());
        return $this->push($queue, $job, $priority, $delay);
    }

    /**
     * Add a batch of jobs to the queue
     *
     * @param array $jobs Array of job payloads
     * @param string $queue Queue name
     * @param int $priority Priority
     * @return array Job IDs
     */
    public function batch(array $jobs, string $queue = self::DEFAULT_QUEUE, int $priority = 0): array
    {
        $jobIds = [];

        foreach ($jobs as $job) {
            $jobIds[] = $this->push($queue, $job, $priority);
        }

        return $jobIds;
    }

    /**
     * Get the size of a queue
     *
     * @param string $queue Queue name
     * @return int Queue size
     */
    public function size(string $queue = self::DEFAULT_QUEUE): int
    {
        return $this->redis->zCard($this->key("queue:{$queue}"));
    }

    /**
     * Check if a job exists
     *
     * @param string $jobId Job ID
     * @return bool
     */
    public function hasJob(string $jobId): bool
    {
        return $this->redis->exists($this->key("job:{$jobId}")) === 1;
    }

    /**
     * Get job details by ID
     *
     * @param string $jobId Job ID
     * @return array|null Job data or null
     */
    public function getJob(string $jobId): ?array
    {
        $jobData = $this->redis->get($this->key("job:{$jobId}"));
        
        if ($jobData === false) {
            return null;
        }

        return json_decode($jobData, true);
    }
}
