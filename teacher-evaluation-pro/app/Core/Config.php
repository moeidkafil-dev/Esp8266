<?php
/**
 * Configuration Manager - Environment-aware configuration system
 */

declare(strict_types=1);

namespace TEP\Core;

class Config {
    
    /**
     * @var array<string, mixed>
     */
    private static array $config = [];
    
    /**
     * @var string
     */
    private string $environment = 'production';
    
    /**
     * Initialize configuration
     */
    public function __construct() {
        $this->loadEnvironment();
        $this->loadDefaults();
    }
    
    /**
     * Load environment from .env file or WordPress options
     */
    private function loadEnvironment(): void {
        // Try to load from WordPress options first
        $env = get_option('tep_environment', 'production');
        $this->environment = $env;
        
        // Load .env file if exists
        $envFile = TEP_PLUGIN_DIR . '.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value, '"\'');
                }
            }
        }
    }
    
    /**
     * Load default configuration values
     */
    private function loadDefaults(): void {
        self::$config = [
            'app' => [
                'name' => 'Teacher Evaluation Pro',
                'version' => TEP_VERSION,
                'url' => get_site_url(),
                'timezone' => wp_timezone_string(),
            ],
            'database' => [
                'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
                'name' => defined('DB_NAME') ? DB_NAME : 'wordpress',
                'user' => defined('DB_USER') ? DB_USER : 'root',
                'password' => defined('DB_PASSWORD') ? DB_PASSWORD : '',
                'prefix' => global $wpdb; return $wpdb->prefix ?? 'wp_',
            ],
            'cache' => [
                'driver' => $this->getEnv('TEP_CACHE_DRIVER', 'wordpress'),
                'redis_host' => $this->getEnv('TEP_REDIS_HOST', '127.0.0.1'),
                'redis_port' => (int) $this->getEnv('TEP_REDIS_PORT', '6379'),
                'redis_password' => $this->getEnv('TEP_REDIS_PASSWORD', null),
                'prefix' => 'tep_',
                'ttl' => 3600,
            ],
            'ai' => [
                'enabled' => true,
                'provider' => $this->getEnv('TEP_AI_PROVIDER', 'openai'),
                'api_key' => $this->getEnv('TEP_AI_API_KEY', ''),
                'model' => $this->getEnv('TEP_AI_MODEL', 'gpt-4'),
                'temperature' => (float) $this->getEnv('TEP_AI_TEMPERATURE', '0.7'),
                'max_tokens' => (int) $this->getEnv('TEP_AI_MAX_TOKENS', '2048'),
                'timeout' => (int) $this->getEnv('TEP_AI_TIMEOUT', '30'),
            ],
            'elasticsearch' => [
                'enabled' => (bool) $this->getEnv('TEP_ELASTIC_ENABLED', 'false'),
                'hosts' => explode(',', $this->getEnv('TEP_ELASTIC_HOSTS', 'localhost:9200')),
                'index_prefix' => $this->getEnv('TEP_ELASTIC_INDEX_PREFIX', 'tep_'),
            ],
            'queue' => [
                'driver' => $this->getEnv('TEP_QUEUE_DRIVER', 'wordpress'),
                'redis_queue' => $this->getEnv('TEP_QUEUE_NAME', 'default'),
            ],
            'mail' => [
                'driver' => $this->getEnv('TEP_MAIL_DRIVER', 'smtp'),
                'host' => $this->getEnv('TEP_MAIL_HOST', 'smtp.mailtrap.io'),
                'port' => (int) $this->getEnv('TEP_MAIL_PORT', '587'),
                'username' => $this->getEnv('TEP_MAIL_USERNAME', ''),
                'password' => $this->getEnv('TEP_MAIL_PASSWORD', ''),
                'encryption' => $this->getEnv('TEP_MAIL_ENCRYPTION', 'tls'),
                'from_address' => $this->getEnv('TEP_MAIL_FROM_ADDRESS', 'noreply@teacherevaluationpro.com'),
                'from_name' => $this->getEnv('TEP_MAIL_FROM_NAME', 'Teacher Evaluation Pro'),
            ],
            'security' => [
                'encryption_key' => $this->getEnv('TEP_ENCRYPTION_KEY', wp_salt()),
                'hash_cost' => (int) $this->getEnv('TEP_HASH_COST', '12'),
                'session_timeout' => (int) $this->getEnv('TEP_SESSION_TIMEOUT', '3600'),
                'max_login_attempts' => (int) $this->getEnv('TEP_MAX_LOGIN_ATTEMPTS', '5'),
                'lockout_time' => (int) $this->getEnv('TEP_LOCKOUT_TIME', '300'),
            ],
            'features' => [
                'evaluations' => (bool) $this->getEnv('TEP_FEATURE_EVALUATIONS', 'true'),
                'ai_agents' => (bool) $this->getEnv('TEP_FEATURE_AI_AGENTS', 'true'),
                'workflows' => (bool) $this->getEnv('TEP_FEATURE_WORKFLOWS', 'true'),
                'analytics' => (bool) $this->getEnv('TEP_FEATURE_ANALYTICS', 'true'),
                'reports' => (bool) $this->getEnv('TEP_FEATURE_REPORTS', 'true'),
                'notifications' => (bool) $this->getEnv('TEP_FEATURE_NOTIFICATIONS', 'true'),
                'file_management' => (bool) $this->getEnv('TEP_FEATURE_FILE_MANAGEMENT', 'true'),
                'multi_tenant' => (bool) $this->getEnv('TEP_FEATURE_MULTI_TENANT', 'false'),
            ],
            'api' => [
                'rate_limit' => (int) $this->getEnv('TEP_API_RATE_LIMIT', '100'),
                'rate_period' => (int) $this->getEnv('TEP_API_RATE_PERIOD', '60'),
                'enable_graphql' => (bool) $this->getEnv('TEP_API_GRAPHQL', 'true'),
                'enable_websocket' => (bool) $this->getEnv('TEP_API_WEBSOCKET', 'true'),
            ],
            'storage' => [
                'driver' => $this->getEnv('TEP_STORAGE_DRIVER', 'local'),
                's3_key' => $this->getEnv('TEP_S3_KEY', ''),
                's3_secret' => $this->getEnv('TEP_S3_SECRET', ''),
                's3_region' => $this->getEnv('TEP_S3_REGION', 'us-east-1'),
                's3_bucket' => $this->getEnv('TEP_S3_BUCKET', ''),
                's3_url' => $this->getEnv('TEP_S3_URL', ''),
                'max_file_size' => (int) $this->getEnv('TEP_MAX_FILE_SIZE', '10485760'), // 10MB
                'allowed_extensions' => explode(',', $this->getEnv('TEP_ALLOWED_EXTENSIONS', 'pdf,docx,xlsx,pptx,jpg,png,mp4,mp3')),
            ],
        ];
    }
    
    /**
     * Get an environment variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function getEnv(string $key, mixed $default = null): mixed {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Get a configuration value using dot notation
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed {
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set a configuration value using dot notation
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void {
        $keys = explode('.', $key);
        $config = &self::$config;
        
        while (count($keys) > 1) {
            $k = array_shift($keys);
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config[array_shift($keys)] = $value;
    }
    
    /**
     * Check if a configuration key exists
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool {
        return self::get($key) !== null;
    }
    
    /**
     * Get all configuration values
     *
     * @return array<string, mixed>
     */
    public static function all(): array {
        return self::$config;
    }
    
    /**
     * Get the current environment
     *
     * @return string
     */
    public function getEnvironment(): string {
        return $this->environment;
    }
    
    /**
     * Check if in production environment
     *
     * @return bool
     */
    public function isProduction(): bool {
        return $this->environment === 'production';
    }
    
    /**
     * Check if in development environment
     *
     * @return bool
     */
    public function isDevelopment(): bool {
        return $this->environment === 'development';
    }
    
    /**
     * Check if in staging environment
     *
     * @return bool
     */
    public function isStaging(): bool {
        return $this->environment === 'staging';
    }
}
