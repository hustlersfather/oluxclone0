<?php
/**
 * PDO Framework Hooks Class
 *
 * Converted from CodeIgniter to use PDO for database operations
 * 
 * This content is released under the MIT License (MIT)
 *
 * @package     PDO_Framework
 * @author      Converted from CodeIgniter
 * @license     MIT License
 */

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Hooks Class
 *
 * Provides a mechanism to extend the base system without hacking
 * with optional database logging and dynamic hook management
 *
 * @package     PDO_Framework
 * @subpackage  Libraries
 * @category    Libraries
 */
class PDO_Hooks {

    /**
     * Determines whether hooks are enabled
     *
     * @var bool
     */
    public $enabled = FALSE;

    /**
     * List of all hooks set in config/hooks.php
     *
     * @var array
     */
    public $hooks = [];

    /**
     * Array with class objects to use hooks methods
     *
     * @var array
     */
    protected $_objects = [];

    /**
     * In progress flag - prevents infinite loops
     *
     * @var bool
     */
    protected $_in_progress = FALSE;

    /**
     * PDO database connection for logging
     *
     * @var PDO
     */
    private $db;

    /**
     * Hook execution logging enabled
     *
     * @var bool
     */
    private $log_execution = FALSE;

    /**
     * Class constructor
     *
     * @param PDO $pdo Optional PDO connection for database operations
     */
    public function __construct(PDO $pdo = null)
    {
        $this->db = $pdo;
        
        log_message('info', 'PDO Hooks Class Initialized');

        // Check if hooks are enabled
        if (config_item('enable_hooks') === FALSE) {
            return;
        }

        // Enable hook execution logging if configured
        $this->log_execution = config_item('log_hook_execution') ?? false;

        // Create hooks table if database is available
        if ($this->db instanceof PDO) {
            $this->createHooksTable();
        }

        // Load hooks configuration
        $this->loadHooksConfig();
        
        // Load dynamic hooks from database
        if ($this->db instanceof PDO) {
            $this->loadDynamicHooks();
        }

        $this->enabled = TRUE;
    }

    /**
     * Create hooks table for dynamic hook management and logging
     *
     * @return bool
     */
    private function createHooksTable()
    {
        try {
            // Table for hook execution logs
            $sql_logs = "CREATE TABLE IF NOT EXISTS hook_execution_logs (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            hook_name VARCHAR(100) NOT NULL,
                            hook_type ENUM('file', 'callable', 'dynamic') NOT NULL,
                            execution_time DECIMAL(10,6) NOT NULL,
                            success BOOLEAN NOT NULL DEFAULT TRUE,
                            error_message TEXT NULL,
                            memory_usage INT NULL,
                            request_id VARCHAR(32) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_hook_name (hook_name),
                            INDEX idx_created_at (created_at)
                        )";

            // Table for dynamic hooks stored in database
            $sql_hooks = "CREATE TABLE IF NOT EXISTS dynamic_hooks (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            hook_point VARCHAR(100) NOT NULL,
                            hook_name VARCHAR(100) NOT NULL,
                            class_name VARCHAR(100) NULL,
                            method_name VARCHAR(100) NOT NULL,
                            file_path VARCHAR(500) NULL,
                            parameters TEXT NULL,
                            priority INT DEFAULT 0,
                            enabled BOOLEAN DEFAULT TRUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            INDEX idx_hook_point (hook_point),
                            INDEX idx_enabled (enabled),
                            INDEX idx_priority (priority)
                        )";

            $this->db->exec($sql_logs);
            $this->db->exec($sql_hooks);
            
            return true;
        } catch (PDOException $e) {
            log_message('error', 'Failed to create hooks tables: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load hooks configuration from files
     *
     * @return void
     */
    private function loadHooksConfig()
    {
        $hook = [];

        // Load main hooks file
        if (file_exists(APPPATH . 'config/hooks.php')) {
            include(APPPATH . 'config/hooks.php');
        }

        // Load environment-specific hooks
        $env_hooks = APPPATH . 'config/' . ENVIRONMENT . '/hooks.php';
        if (file_exists($env_hooks)) {
            include($env_hooks);
        }

        if (isset($hook) && is_array($hook)) {
            $this->hooks = $hook;
        }
    }

    /**
     * Load dynamic hooks from database
     *
     * @return void
     */
    private function loadDynamicHooks()
    {
        try {
            $sql = "SELECT * FROM dynamic_hooks WHERE enabled = 1 ORDER BY hook_point, priority ASC";
            $stmt = $this->db->query($sql);
            $dynamic_hooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($dynamic_hooks as $hook) {
                $hook_config = [
                    'class' => $hook['class_name'],
                    'function' => $hook['method_name'],
                    'filename' => $hook['file_path'],
                    'params' => $hook['parameters'] ? json_decode($hook['parameters'], true) : []
                ];

                // Add to existing hooks or create new hook point
                if (isset($this->hooks[$hook['hook_point']])) {
                    if (isset($this->hooks[$hook['hook_point']]['function'])) {
                        // Convert single hook to array of hooks
                        $existing = $this->hooks[$hook['hook_point']];
                        $this->hooks[$hook['hook_point']] = [$existing, $hook_config];
                    } else {
                        // Add to existing array of hooks
                        $this->hooks[$hook['hook_point']][] = $hook_config;
                    }
                } else {
                    $this->hooks[$hook['hook_point']] = $hook_config;
                }
            }
        } catch (PDOException $e) {
            log_message('error', 'Failed to load dynamic hooks: ' . $e->getMessage());
        }
    }

    /**
     * Call Hook
     *
     * Calls a particular hook with execution logging
     *
     * @param string $which Hook name
     * @return bool TRUE on success or FALSE on failure
     */
    public function call_hook($which = '')
    {
        if (!$this->enabled || !isset($this->hooks[$which])) {
            return FALSE;
        }

        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        $request_id = $this->getRequestId();
        $success = true;
        $error_message = null;

        try {
            if (is_array($this->hooks[$which]) && !isset($this->hooks[$which]['function'])) {
                // Multiple hooks for this point
                foreach ($this->hooks[$which] as $val) {
                    $this->_run_hook($val);
                }
            } else {
                // Single hook
                $this->_run_hook($this->hooks[$which]);
            }
        } catch (Exception $e) {
            $success = false;
            $error_message = $e->getMessage();
            log_message('error', "Hook '{$which}' failed: " . $error_message);
        }

        // Log execution if enabled
        if ($this->log_execution && $this->db instanceof PDO) {
            $this->logHookExecution($which, $start_time, $start_memory, $success, $error_message, $request_id);
        }

        return $success;
    }

    /**
     * Run Hook
     *
     * Runs a particular hook
     *
     * @param array $data Hook details
     * @return bool TRUE on success or FALSE on failure
     */
    protected function _run_hook($data)
    {
        // Handle closures and callable arrays
        if (is_callable($data)) {
            if (is_array($data)) {
                return $data[0]->{$data[1]}();
            } else {
                return $data();
            }
        }

        if (!is_array($data)) {
            return FALSE;
        }

        // Prevent infinite loops
        if ($this->_in_progress === TRUE) {
            return FALSE;
        }

        // Validate required data
        if (!isset($data['function'])) {
            return FALSE;
        }

        // Set file path if specified
        $filepath = null;
        if (isset($data['filepath'], $data['filename'])) {
            $filepath = APPPATH . $data['filepath'] . '/' . $data['filename'];
            if (!file_exists($filepath)) {
                return FALSE;
            }
        }

        $class = $data['class'] ?? null;
        $function = $data['function'];
        $params = $data['params'] ?? [];

        // Set in progress flag
        $this->_in_progress = TRUE;

        try {
            if ($class !== null) {
                // Class method call
                if (isset($this->_objects[$class])) {
                    // Use existing object instance
                    if (method_exists($this->_objects[$class], $function)) {
                        $this->_objects[$class]->$function($params);
                    } else {
                        throw new Exception("Method {$function} not found in class {$class}");
                    }
                } else {
                    // Create new object instance
                    if ($filepath) {
                        require_once($filepath);
                    }

                    if (!class_exists($class, FALSE)) {
                        throw new Exception("Class {$class} not found");
                    }

                    if (!method_exists($class, $function)) {
                        throw new Exception("Method {$function} not found in class {$class}");
                    }

                    // Store object and execute method
                    $this->_objects[$class] = new $class($this->db); // Pass PDO connection
                    $this->_objects[$class]->$function($params);
                }
            } else {
                // Function call
                if ($filepath) {
                    require_once($filepath);
                }

                if (!function_exists($function)) {
                    throw new Exception("Function {$function} not found");
                }

                $function($params);
            }

            $this->_in_progress = FALSE;
            return TRUE;

        } catch (Exception $e) {
            $this->_in_progress = FALSE;
            log_message('error', 'Hook execution failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Log hook execution to database
     *
     * @param string $hook_name
     * @param float $start_time
     * @param int $start_memory
     * @param bool $success
     * @param string|null $error_message
     * @param string $request_id
     * @return void
     */
    private function logHookExecution($hook_name, $start_time, $start_memory, $success, $error_message, $request_id)
    {
        try {
            $execution_time = microtime(true) - $start_time;
            $memory_usage = memory_get_usage() - $start_memory;

            $sql = "INSERT INTO hook_execution_logs 
                    (hook_name, hook_type, execution_time, success, error_message, memory_usage, request_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $hook_name,
                'file', // Default type, could be enhanced
                $execution_time,
                $success,
                $error_message,
                $memory_usage,
                $request_id
            ]);
        } catch (PDOException $e) {
            log_message('error', 'Failed to log hook execution: ' . $e->getMessage());
        }
    }

    /**
     * Add dynamic hook to database
     *
     * @param string $hook_point Hook point name
     * @param string $hook_name Hook identifier
     * @param string|null $class_name Class name (optional)
     * @param string $method_name Method or function name
     * @param string|null $file_path File path (optional)
     * @param array $parameters Parameters to pass
     * @param int $priority Execution priority
     * @return bool
     */
    public function addDynamicHook($hook_point, $hook_name, $class_name, $method_name, $file_path = null, $parameters = [], $priority = 0)
    {
        if (!$this->db instanceof PDO) {
            return false;
        }

        try {
            $sql = "INSERT INTO dynamic_hooks 
                    (hook_point, hook_name, class_name, method_name, file_path, parameters, priority) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $hook_point,
                $hook_name,
                $class_name,
                $method_name,
                $file_path,
                json_encode($parameters),
                $priority
            ]);
        } catch (PDOException $e) {
            log_message('error', 'Failed to add dynamic hook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove dynamic hook from database
     *
     * @param int $hook_id Hook ID
     * @return bool
     */
    public function removeDynamicHook($hook_id)
    {
        if (!$this->db instanceof PDO) {
            return false;
        }

        try {
            $sql = "DELETE FROM dynamic_hooks WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$hook_id]);
        } catch (PDOException $e) {
            log_message('error', 'Failed to remove dynamic hook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable/disable dynamic hook
     *
     * @param int $hook_id Hook ID
     * @param bool $enabled Enabled status
     * @return bool
     */
    public function toggleDynamicHook($hook_id, $enabled)
    {
        if (!$this->db instanceof PDO) {
            return false;
        }

        try {
            $sql = "UPDATE dynamic_hooks SET enabled = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$enabled ? 1 : 0, $hook_id]);
        } catch (PDOException $e) {
            log_message('error', 'Failed to toggle dynamic hook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all dynamic hooks
     *
     * @param string|null $hook_point Filter by hook point
     * @return array
     */
    public function getDynamicHooks($hook_point = null)
    {
        if (!$this->db instanceof PDO) {
            return [];
        }

        try {
            if ($hook_point) {
                $sql = "SELECT * FROM dynamic_hooks WHERE hook_point = ? ORDER BY priority ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$hook_point]);
            } else {
                $sql = "SELECT * FROM dynamic_hooks ORDER BY hook_point, priority ASC";
                $stmt = $this->db->query($sql);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_message('error', 'Failed to get dynamic hooks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get hook execution statistics
     *
     * @param int $limit Number of records to return
     * @param string|null $hook_name Filter by hook name
     * @return array
     */
    public function getHookStats($limit = 100, $hook_name = null)
    {
        if (!$this->db instanceof PDO) {
            return [];
        }

        try {
            if ($hook_name) {
                $sql = "SELECT hook_name, 
                               COUNT(*) as execution_count,
                               AVG(execution_time) as avg_execution_time,
                               MAX(execution_time) as max_execution_time,
                               MIN(execution_time) as min_execution_time,
                               SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count,
                               SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as error_count
                        FROM hook_execution_logs 
                        WHERE hook_name = ?
                        GROUP BY hook_name 
                        ORDER BY execution_count DESC 
                        LIMIT ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$hook_name, $limit]);
            } else {
                $sql = "SELECT hook_name, 
                               COUNT(*) as execution_count,
                               AVG(execution_time) as avg_execution_time,
                               MAX(execution_time) as max_execution_time,
                               MIN(execution_time) as min_execution_time,
                               SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count,
                               SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as error_count
                        FROM hook_execution_logs 
                        GROUP BY hook_name 
                        ORDER BY execution_count DESC 
                        LIMIT ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$limit]);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_message('error', 'Failed to get hook stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear old hook execution logs
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public function clearOldLogs($days = 30)
    {
        if (!$this->db instanceof PDO) {
            return 0;
        }

        try {
            $sql = "DELETE FROM hook_execution_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$days]);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            log_message('error', 'Failed to clear old hook logs: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get unique request ID for tracking
     *
     * @return string
     */
    private function getRequestId()
    {
        static $request_id;
        
        if ($request_id === null) {
            $request_id = uniqid('req_', true);
        }
        
        return $request_id;
    }

    /**
     * Register a callable as a hook
     *
     * @param string $hook_point Hook point name
     * @param callable $callback Callback function
     * @return void
     */
    public function registerCallable($hook_point, $callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Callback must be callable');
        }

        if (!isset($this->hooks[$hook_point])) {
            $this->hooks[$hook_point] = [];
        }

        if (isset($this->hooks[$hook_point]['function'])) {
            // Convert single hook to array
            $existing = $this->hooks[$hook_point];
            $this->hooks[$hook_point] = [$existing, $callback];
        } elseif (is_array($this->hooks[$hook_point])) {
            $this->hooks[$hook_point][] = $callback;
        } else {
            $this->hooks[$hook_point] = $callback;
        }
    }
}
?>