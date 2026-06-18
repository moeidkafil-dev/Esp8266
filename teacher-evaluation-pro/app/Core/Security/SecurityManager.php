<?php

declare(strict_types=1);

namespace TEP\Core\Security;

use RuntimeException;

/**
 * Enterprise-Grade Security Manager
 * 
 * Provides comprehensive security features including encryption,
 * hashing, token generation, and secure data handling.
 */
class SecurityManager
{
    /**
     * Encryption cipher to use
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * Hash algorithm for password hashing
     */
    private const HASH_ALGORITHM = PASSWORD_ARGON2ID;

    /**
     * Hash cost options
     */
    private const HASH_OPTIONS = [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 2          // 2 threads
    ];

    /**
     * Encryption key (should be loaded from environment)
     */
    protected ?string $encryptionKey = null;

    /**
     * HMAC key for authentication
     */
    protected ?string $hmacKey = null;

    /**
     * Create a new SecurityManager instance
     *
     * @param string|null $encryptionKey 32-byte encryption key
     * @param string|null $hmacKey HMAC key for authentication
     */
    public function __construct(?string $encryptionKey = null, ?string $hmacKey = null)
    {
        $this->encryptionKey = $encryptionKey ?? getenv('TEP_ENCRYPTION_KEY') ?: null;
        $this->hmacKey = $hmacKey ?? getenv('TEP_HMAC_KEY') ?: null;
    }

    /**
     * Set the encryption key
     *
     * @param string $key
     * @return void
     */
    public function setEncryptionKey(string $key): void
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('Encryption key must be 32 bytes long');
        }
        $this->encryptionKey = $key;
    }

    /**
     * Set the HMAC key
     *
     * @param string $key
     * @return void
     */
    public function setHmacKey(string $key): void
    {
        $this->hmacKey = $key;
    }

    /**
     * Encrypt data using AES-256-GCM
     *
     * @param mixed $data Data to encrypt (will be JSON encoded)
     * @param string|null $aad Additional authenticated data
     * @return string Base64-encoded encrypted payload with IV and tag
     * @throws RuntimeException
     */
    public function encrypt(mixed $data, ?string $aad = null): string
    {
        $this->ensureEncryptionKey();

        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $tag = '';
        $encrypted = openssl_encrypt(
            $payload,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad ?? ''
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Combine IV, tag, and ciphertext into single payload
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt data
     *
     * @param string $payload Base64-encoded encrypted payload
     * @param string|null $aad Additional authenticated data used during encryption
     * @return mixed Decrypted data (JSON decoded)
     * @throws RuntimeException
     */
    public function decrypt(string $payload, ?string $aad = null): mixed
    {
        $this->ensureEncryptionKey();

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 encoding');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $tagLength = 16; // GCM tag is always 16 bytes

        if (strlen($decoded) < $ivLength + $tagLength) {
            throw new RuntimeException('Invalid payload length');
        }

        $iv = substr($decoded, 0, $ivLength);
        $tag = substr($decoded, $ivLength, $tagLength);
        $ciphertext = substr($decoded, $ivLength + $tagLength);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad ?? ''
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed or authentication failed');
        }

        return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Hash a password securely
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public function hashPassword(string $password): string
    {
        $hash = password_hash($password, self::HASH_ALGORITHM, self::HASH_OPTIONS);

        if ($hash === false) {
            // Fallback to bcrypt if Argon2 is not available
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return $hash;
    }

    /**
     * Verify a password against a hash
     *
     * @param string $password Plain text password
     * @param string $hash Stored hash
     * @return bool True if password matches
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a password hash needs rehashing
     *
     * @param string $hash Current hash
     * @return bool True if rehash is needed
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::HASH_ALGORITHM, self::HASH_OPTIONS);
    }

    /**
     * Generate a cryptographically secure random token
     *
     * @param int $length Token length in bytes
     * @return string Hex-encoded token
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate a secure random string
     *
     * @param int $length Length of the string
     * @param bool $specialChars Include special characters
     * @return string Random string
     */
    public function generateRandomString(int $length = 32, bool $specialChars = false): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($specialChars) {
            $chars .= '!@#$%^&*()-_=+[]{}|;:,.<>?';
        }

        $result = '';
        $charLength = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $charLength);
            $result .= $chars[$index];
        }

        return $result;
    }

    /**
     * Generate a UUID v4
     *
     * @return string UUID v4 string
     */
    public function generateUuid(): string
    {
        $data = random_bytes(16);

        // Set version to 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);

        // Set variant to RFC 4122
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Create HMAC signature
     *
     * @param string $data Data to sign
     * @param string|null $key HMAC key (uses default if null)
     * @param string $algorithm Hash algorithm
     * @return string Base64-encoded HMAC
     */
    public function hmac(string $data, ?string $key = null, string $algorithm = 'sha256'): string
    {
        $key = $key ?? $this->hmacKey;

        if (empty($key)) {
            throw new RuntimeException('HMAC key not configured');
        }

        return base64_encode(hash_hmac($algorithm, $data, $key, true));
    }

    /**
     * Verify HMAC signature
     *
     * @param string $data Original data
     * @param string $signature Signature to verify
     * @param string|null $key HMAC key
     * @param string $algorithm Hash algorithm
     * @return bool True if signature is valid
     */
    public function verifyHmac(string $data, string $signature, ?string $key = null, string $algorithm = 'sha256'): bool
    {
        $expected = $this->hmac($data, $key, $algorithm);
        return hash_equals($expected, $signature);
    }

    /**
     * Sanitize input to prevent XSS
     *
     * @param string $input Input to sanitize
     * @param int $flags Additional flags for htmlspecialchars
     * @return string Sanitized output
     */
    public function sanitizeInput(string $input, int $flags = ENT_QUOTES | ENT_HTML5): string
    {
        return htmlspecialchars($input, $flags, 'UTF-8', true);
    }

    /**
     * Clean HTML content while preserving safe tags
     *
     * @param string $html HTML content to clean
     * @param array $allowedTags Allowed HTML tags
     * @return string Cleaned HTML
     */
    public function cleanHtml(string $html, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'a', 'img'];
        }

        // Strip all tags except allowed ones
        $tagList = '<' . implode('><', $allowedTags) . '>';
        $cleaned = strip_tags($html, $tagList);

        // Remove dangerous attributes
        $cleaned = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $cleaned);
        $cleaned = preg_replace('/\s*javascript\s*:/i', '', $cleaned);

        return $cleaned;
    }

    /**
     * Mask sensitive data for display
     *
     * @param string $data Sensitive data
     * @param int $visibleStart Number of characters to show at start
     * @param int $visibleEnd Number of characters to show at end
     * @return string Masked data
     */
    public function maskData(string $data, int $visibleStart = 2, int $visibleEnd = 2): string
    {
        $length = strlen($data);

        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat('*', $length);
        }

        $start = substr($data, 0, $visibleStart);
        $end = substr($data, -$visibleEnd);
        $maskLength = $length - $visibleStart - $visibleEnd;

        return $start . str_repeat('*', $maskLength) . $end;
    }

    /**
     * Securely compare two strings (timing-safe)
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return bool True if strings are equal
     */
    public function secureCompare(string $str1, string $str2): bool
    {
        return hash_equals($str1, $str2);
    }

    /**
     * Validate CSRF token
     *
     * @param string $token Submitted token
     * @param string $storedToken Stored token
     * @return bool True if valid
     */
    public function validateCsrfToken(string $token, string $storedToken): bool
    {
        return $this->secureCompare($token, $storedToken);
    }

    /**
     * Generate CSRF token
     *
     * @return string CSRF token
     */
    public function generateCsrfToken(): string
    {
        return $this->generateToken(32);
    }

    /**
     * Ensure encryption key is set
     *
     * @return void
     * @throws RuntimeException
     */
    protected function ensureEncryptionKey(): void
    {
        if (empty($this->encryptionKey)) {
            throw new RuntimeException('Encryption key not configured');
        }
    }

    /**
     * Get supported ciphers
     *
     * @return array List of supported ciphers
     */
    public static function getSupportedCiphers(): array
    {
        return openssl_get_cipher_methods();
    }

    /**
     * Get supported hash algorithms
     *
     * @return array List of supported hash algorithms
     */
    public static function getSupportedHashAlgorithms(): array
    {
        return hash_algos();
    }
}
