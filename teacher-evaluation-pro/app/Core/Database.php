<?php
/**
 * Database Manager - WordPress database abstraction layer
 */

declare(strict_types=1);

namespace TEP\Core;

use wpdb;

class Database {
    
    /**
     * @var wpdb|null
     */
    private static ?wpdb $wpdb = null;
    
    /**
     * Get WordPress database instance
     *
     * @return wpdb
     */
    public static function getInstance(): wpdb {
        global $wpdb;
        return $wpdb;
    }
    
    /**
     * Get table name with prefix
     *
     * @param string $table
     * @return string
     */
    public static function table(string $table): string {
        $wpdb = self::getInstance();
        return $wpdb->prefix . 'tep_' . $table;
    }
    
    /**
     * Insert data into a table
     *
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string, string>|null $format
     * @return int|false
     */
    public static function insert(string $table, array $data, ?array $format = null): int|false {
        $wpdb = self::getInstance();
        $tableName = self::table($table);
        
        if ($format === null) {
            $format = self::getFormats($data);
        }
        
        $result = $wpdb->insert($tableName, $data, $format);
        
        if ($result === false) {
            error_log('TEP Database Insert Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update data in a table
     *
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @param array<string, string>|null $format
     * @param array<string, string>|null $whereFormat
     * @return int|false
     */
    public static function update(
        string $table,
        array $data,
        array $where,
        ?array $format = null,
        ?array $whereFormat = null
    ): int|false {
        $wpdb = self::getInstance();
        $tableName = self::table($table);
        
        if ($format === null) {
            $format = self::getFormats($data);
        }
        
        if ($whereFormat === null) {
            $whereFormat = self::getFormats($where);
        }
        
        $result = $wpdb->update($tableName, $data, $where, $format, $whereFormat);
        
        if ($result === false) {
            error_log('TEP Database Update Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    /**
     * Delete data from a table
     *
     * @param string $table
     * @param array<string, mixed> $where
     * @param array<string, string>|null $format
     * @return int|false
     */
    public static function delete(string $table, array $where, ?array $format = null): int|false {
        $wpdb = self::getInstance();
        $tableName = self::table($table);
        
        if ($format === null) {
            $format = self::getFormats($where);
        }
        
        $result = $wpdb->delete($tableName, $where, $format);
        
        if ($result === false) {
            error_log('TEP Database Delete Error: ' . $wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    /**
     * Get a single row from a table
     *
     * @param string $table
     * @param array<string, mixed> $where
     * @param string $output
     * @return object|array<string, mixed>|null
     */
    public static function getRow(string $table, array $where, string $output = OBJECT): object|array|null {
        $wpdb = self::getInstance();
        $tableName = self::table($table);
        
        $conditions = [];
        $values = [];
        
        foreach ($where as $column => $value) {
            $conditions[] = "$column = %s";
            $values[] = $value;
        }
        
        $sql = "SELECT * FROM {$tableName} WHERE " . implode(' AND ', $conditions);
        $prepared = $wpdb->prepare($sql, $values);
        
        return $wpdb->get_row($prepared, $output);
    }
    
    /**
     * Get multiple rows from a table
     *
     * @param string $table
     * @param array<string, mixed>|null $where
     * @param array<string, string>|null $orderBy
     * @param int|null $limit
     * @param int $offset
     * @return array<object|array<string, mixed>>
     */
    public static function getRows(
        string $table,
        ?array $where = null,
        ?array $orderBy = null,
        ?int $limit = null,
        int $offset = 0
    ): array {
        $wpdb = self::getInstance();
        $tableName = self::table($table);
        
        $conditions = [];
        $values = [];
        
        if ($where !== null) {
            foreach ($where as $column => $value) {
                if (is_array($value)) {
                    $operator = $value['operator'] ?? '=';
                    $val = $value['value'];
                    
                    if ($operator === 'IN') {
                        $placeholders = implode(',', array_fill(0, count($val), '%s'));
                        $conditions[] = "$column IN ($placeholders)";
                        $values = array_merge($values, $val);
                    } else {
                        $conditions[] = "$column $operator %s";
                        $values[] = $val;
                    }
                } else {
                    $conditions[] = "$column = %s";
                    $values[] = $value;
                }
            }
        }
        
        $sql = "SELECT * FROM {$tableName}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy !== null) {
            $orderParts = [];
            foreach ($orderBy as $column => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $orderParts[] = "$column $direction";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }
        
        if ($limit !== null) {
            $sql .= " LIMIT %d OFFSET %d";
            $values[] = $limit;
            $values[] = $offset;
        }
        
        $prepared = $wpdb->prepare($sql, $values);
        
        return $wpdb->get_results($prepared) ?? [];
    }
    
    /**
     * Count rows in a table
     *
     * @param string $table
     * @param array<string, mixed>|null $where
     * @return int
     */
    public static function count(string $table, ?array $where = null): int {
        $wpdb = self::getInstance();
        $tableName = self::table($table);
        
        $conditions = [];
        $values = [];
        
        if ($where !== null) {
            foreach ($where as $column => $value) {
                $conditions[] = "$column = %s";
                $values[] = $value;
            }
        }
        
        $sql = "SELECT COUNT(*) FROM {$tableName}";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $prepared = $wpdb->prepare($sql, $values);
        
        return (int) $wpdb->get_var($prepared);
    }
    
    /**
     * Execute a custom query
     *
     * @param string $sql
     * @param array<mixed> $params
     * @return array<object|array<string, mixed>>|int|false
     */
    public static function query(string $sql, array $params = []): array|int|false {
        $wpdb = self::getInstance();
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        // Determine query type
        $queryType = strtoupper(strtok(trim($sql), ' '));
        
        switch ($queryType) {
            case 'SELECT':
                return $wpdb->get_results($sql) ?? [];
            case 'INSERT':
                $result = $wpdb->query($sql);
                return $result !== false ? $wpdb->insert_id : false;
            case 'UPDATE':
            case 'DELETE':
                return $wpdb->query($sql);
            default:
                return $wpdb->query($sql);
        }
    }
    
    /**
     * Begin a transaction
     *
     * @return bool
     */
    public static function beginTransaction(): bool {
        $wpdb = self::getInstance();
        $wpdb->query('START TRANSACTION');
        return true;
    }
    
    /**
     * Commit a transaction
     *
     * @return bool
     */
    public static function commit(): bool {
        $wpdb = self::getInstance();
        $wpdb->query('COMMIT');
        return true;
    }
    
    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public static function rollback(): bool {
        $wpdb = self::getInstance();
        $wpdb->query('ROLLBACK');
        return true;
    }
    
    /**
     * Get column formats for wpdb operations
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function getFormats(array $data): array {
        $formats = [];
        
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $formats;
    }
    
    /**
     * Sanitize data for database insertion
     *
     * @param mixed $value
     * @return mixed
     */
    public static function sanitize(mixed $value): mixed {
        $wpdb = self::getInstance();
        
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        
        return $wpdb->_real_escape($value);
    }
}
