<?php
/**
 * Modern PHP Application Controller with PDO Database Support
 *
 * Refactored from CodeIgniter framework to use PDO for database operations
 * 
 * This content is released under the MIT License (MIT)
 */

/**
 * Application Controller Class with PDO Database Support
 *
 * This class provides a foundation for controllers with built-in PDO database connectivity
 *
 * @package     ModernPHP
 * @subpackage  Controllers
 * @category    Controllers
 */
class BaseController {

    /**
     * Reference to the controller singleton
     *
     * @var object
     */
    private static $instance;

    /**
     * PDO database connection
     *
     * @var PDO
     */
    protected $db;

    /**
     * Loader instance for handling includes and libraries
     *
     * @var object
     */
    protected $load;

    /**
     * Database configuration
     *
     * @var array
     */
    private $dbConfig = [
        'host' => 'localhost',
        'dbname' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ];

    /**
     * Class constructor
     *
     * @return void
     */
    public function __construct()
    {
        self::$instance = $this;
        
        // Initialize database connection
        $this->initializeDatabase();
        
        // Initialize loader
        $this->load = new SimpleLoader();
        
        $this->logMessage('info', 'Controller Class Initialized with PDO');
    }

    /**
     * Initialize PDO database connection
     *
     * @return void
     * @throws Exception
     */
    private function initializeDatabase()
    {
        try {
            $dsn = "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['dbname']};charset={$this->dbConfig['charset']}";
            
            $this->db = new PDO(
                $dsn,
                $this->dbConfig['username'],
                $this->dbConfig['password'],
                $this->dbConfig['options']
            );
            
            $this->logMessage('info', 'PDO Database connection established');
        } catch (PDOException $e) {
            $this->logMessage('error', 'Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the controller singleton
     *
     * @static
     * @return object
     */
    public static function &getInstance()
    {
        return self::$instance;
    }

    /**
     * Get PDO database connection
     *
     * @return PDO
     */
    public function getDatabase()
    {
        return $this->db;
    }

    /**
     * Execute a prepared statement with parameters
     *
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     * @throws Exception
     */
    protected function query($sql, $params = [])
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logMessage('error', 'Query failed: ' . $e->getMessage());
            throw new Exception('Query execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch single row from database
     *
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    protected function fetchRow($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch multiple rows from database
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert data into database
     *
     * @param string $table
     * @param array $data
     * @return string Last insert ID
     */
    protected function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->db->lastInsertId();
    }

    /**
     * Update data in database
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @return int Number of affected rows
     */
    protected function update($table, $data, $where)
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $set);

        $whereConditions = [];
        foreach ($where as $key => $value) {
            $whereConditions[] = "{$key} = :where_{$key}";
        }
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
        
        // Merge data and where parameters
        $params = $data;
        foreach ($where as $key => $value) {
            $params["where_{$key}"] = $value;
        }

        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete data from database
     *
     * @param string $table
     * @param array $where
     * @return int Number of affected rows
     */
    protected function delete($table, $where)
    {
        $whereConditions = [];
        foreach ($where as $key => $value) {
            $whereConditions[] = "{$key} = :{$key}";
        }
        $whereClause = implode(' AND ', $whereConditions);

        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        
        $stmt = $this->query($sql, $where);
        return $stmt->rowCount();
    }

    /**
     * Begin database transaction
     *
     * @return bool
     */
    protected function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     *
     * @return bool
     */
    protected function commit()
    {
        return $this->db->commit();
    }

    /**
     * Rollback database transaction
     *
     * @return bool
     */
    protected function rollback()
    {
        return $this->db->rollBack();
    }

    /**
     * Log message (simplified logging)
     *
     * @param string $level
     * @param string $message
     * @return void
     */
    protected function logMessage($level, $message)
    {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] {$level}: {$message}");
    }

    /**
     * Set database configuration
     *
     * @param array $config
     * @return void
     */
    public function setDatabaseConfig(array $config)
    {
        $this->dbConfig = array_merge($this->dbConfig, $config);
    }
}

/**
 * Simple Loader Class
 * 
 * Simplified version of CodeIgniter's loader
 */
class SimpleLoader {
    
    /**
     * Load a library or helper
     *
     * @param string $class
     * @param string $type
     * @return object|null
     */
    public function library($class, $type = 'libraries')
    {
        $filename = APPPATH . "{$type}/{$class}.php";
        
        if (file_exists($filename)) {
            require_once $filename;
            return new $class();
        }
        
        return null;
    }

    /**
     * Load a view file
     *
     * @param string $view
     * @param array $data
     * @param bool $return
     * @return string|void
     */
    public function view($view, $data = [], $return = false)
    {
        $viewFile = APPPATH . "views/{$view}.php";
        
        if (file_exists($viewFile)) {
            extract($data);
            
            if ($return) {
                ob_start();
                include $viewFile;
                return ob_get_clean();
            } else {
                include $viewFile;
            }
        }
    }

    /**
     * Initialize loader
     *
     * @return void
     */
    public function initialize()
    {
        // Initialization logic here
    }
}

// Example usage in a specific controller:
/*
class UserController extends BaseController {
    
    public function getUser($id) {
        $user = $this->fetchRow(
            "SELECT * FROM users WHERE id = :id", 
            ['id' => $id]
        );
        return $user;
    }
    
    public function createUser($userData) {
        try {
            $this->beginTransaction();
            
            $userId = $this->insert('users', $userData);
            
            $this->commit();
            return $userId;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    public function updateUser($id, $userData) {
        return $this->update('users', $userData, ['id' => $id]);
    }
    
    public function deleteUser($id) {
        return $this->delete('users', ['id' => $id]);
    }
}
*/
?>