<?php
/**
 * Simple PDO-based Loader Class
 * Converted from CodeIgniter Loader to use direct PDO
 */

class PDO_Loader {
    
    /**
     * PDO Database Connection
     * @var PDO
     */
    protected $db;
    
    /**
     * Loaded models
     * @var array
     */
    protected $models = array();
    
    /**
     * Loaded libraries
     * @var array
     */
    protected $libraries = array();
    
    /**
     * Cached variables for views
     * @var array
     */
    protected $cached_vars = array();
    
    /**
     * View paths
     * @var array
     */
    protected $view_paths = array();
    
    /**
     * Library paths
     * @var array
     */
    protected $library_paths = array();
    
    /**
     * Model paths
     * @var array
     */
    protected $model_paths = array();
    
    /**
     * Constructor
     * 
     * @param PDO $database PDO database connection
     * @param array $config Configuration array
     */
    public function __construct(PDO $database = null, $config = array()) {
        $this->db = $database;
        
        // Set default paths
        $this->view_paths = isset($config['view_path']) ? array($config['view_path']) : array('./views/');
        $this->library_paths = isset($config['library_path']) ? array($config['library_path']) : array('./libraries/');
        $this->model_paths = isset($config['model_path']) ? array($config['model_path']) : array('./models/');
    }
    
    /**
     * Set/Get PDO Database Connection
     * 
     * @param PDO $database
     * @return PDO|$this
     */
    public function database(PDO $database = null) {
        if ($database !== null) {
            $this->db = $database;
            return $this;
        }
        
        return $this->db;
    }
    
    /**
     * Load Model
     * 
     * @param string $model Model name
     * @param string $name Optional object name
     * @return $this
     */
    public function model($model, $name = '') {
        if (empty($model)) {
            return $this;
        }
        
        if (is_array($model)) {
            foreach ($model as $key => $value) {
                is_int($key) ? $this->model($value) : $this->model($key, $value);
            }
            return $this;
        }
        
        $path = '';
        
        // Check for subdirectory
        if (($last_slash = strrpos($model, '/')) !== FALSE) {
            $path = substr($model, 0, ++$last_slash);
            $model = substr($model, $last_slash);
        }
        
        if (empty($name)) {
            $name = $model;
        }
        
        // Check if already loaded
        if (in_array($name, $this->models, TRUE)) {
            return $this;
        }
        
        $model = ucfirst($model);
        
        // Look for the model file
        $found = FALSE;
        foreach ($this->model_paths as $model_path) {
            $file_path = $model_path . 'models/' . $path . $model . '.php';
            
            if (file_exists($file_path)) {
                require_once($file_path);
                $found = TRUE;
                break;
            }
        }
        
        if (!$found) {
            throw new RuntimeException('Unable to locate the model: ' . $model);
        }
        
        if (!class_exists($model, FALSE)) {
            throw new RuntimeException('Model file exists but class not found: ' . $model);
        }
        
        // Instantiate the model with PDO connection
        $model_instance = new $model($this->db);
        
        // Store reference
        $this->models[] = $name;
        $this->$name = $model_instance;
        
        return $this;
    }
    
    /**
     * Load Library
     * 
     * @param string $library Library name
     * @param array $params Optional parameters
     * @param string $object_name Optional object name
     * @return $this
     */
    public function library($library, $params = NULL, $object_name = NULL) {
        if (empty($library)) {
            return $this;
        }
        
        if (is_array($library)) {
            foreach ($library as $key => $value) {
                if (is_int($key)) {
                    $this->library($value, $params);
                } else {
                    $this->library($key, $params, $value);
                }
            }
            return $this;
        }
        
        $library = str_replace('.php', '', trim($library, '/'));
        
        // Check for subdirectory
        $subdir = '';
        if (($last_slash = strrpos($library, '/')) !== FALSE) {
            $subdir = substr($library, 0, ++$last_slash);
            $library = substr($library, $last_slash);
        }
        
        $library = ucfirst($library);
        
        // Set object name
        if (empty($object_name)) {
            $object_name = strtolower($library);
        }
        
        // Check if already loaded
        if (isset($this->libraries[$object_name])) {
            return $this;
        }
        
        // Look for the library file
        $found = FALSE;
        foreach ($this->library_paths as $library_path) {
            $file_path = $library_path . 'libraries/' . $subdir . $library . '.php';
            
            if (file_exists($file_path)) {
                require_once($file_path);
                $found = TRUE;
                break;
            }
        }
        
        if (!$found) {
            throw new RuntimeException('Unable to locate the library: ' . $library);
        }
        
        if (!class_exists($library, FALSE)) {
            throw new RuntimeException('Library file exists but class not found: ' . $library);
        }
        
        // Instantiate the library
        $library_instance = ($params !== NULL) ? new $library($params) : new $library();
        
        // If library needs database access, inject PDO
        if (method_exists($library_instance, 'set_database')) {
            $library_instance->set_database($this->db);
        }
        
        // Store reference
        $this->libraries[$object_name] = $library;
        $this->$object_name = $library_instance;
        
        return $this;
    }
    
    /**
     * Load View
     * 
     * @param string $view View name
     * @param array $vars Data variables
     * @param bool $return Return output instead of displaying
     * @return string|$this
     */
    public function view($view, $vars = array(), $return = FALSE) {
        return $this->_load_view($view, $vars, $return);
    }
    
    /**
     * Set Variables for Views
     * 
     * @param mixed $vars Variables to set
     * @param string $val Value if $vars is string
     * @return $this
     */
    public function vars($vars, $val = '') {
        if (is_string($vars)) {
            $vars = array($vars => $val);
        }
        
        if (is_array($vars) || is_object($vars)) {
            foreach ($vars as $key => $value) {
                $this->cached_vars[$key] = $value;
            }
        }
        
        return $this;
    }
    
    /**
     * Get Variable
     * 
     * @param string $key Variable name
     * @return mixed
     */
    public function get_var($key) {
        return isset($this->cached_vars[$key]) ? $this->cached_vars[$key] : NULL;
    }
    
    /**
     * Clear Cached Variables
     * 
     * @return $this
     */
    public function clear_vars() {
        $this->cached_vars = array();
        return $this;
    }
    
    /**
     * Add Package Path
     * 
     * @param string $path Path to add
     * @return $this
     */
    public function add_package_path($path) {
        $path = rtrim($path, '/') . '/';
        
        array_unshift($this->library_paths, $path);
        array_unshift($this->model_paths, $path);
        array_unshift($this->view_paths, $path . 'views/');
        
        return $this;
    }
    
    /**
     * Execute Database Query
     * 
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return PDOStatement
     */
    public function query($sql, $params = array()) {
        if (!$this->db) {
            throw new RuntimeException('No database connection available');
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get Database Connection
     * 
     * @return PDO
     */
    public function get_db() {
        return $this->db;
    }
    
    /**
     * Internal View Loader
     * 
     * @param string $view View name
     * @param array $vars Variables
     * @param bool $return Return output
     * @return string|$this
     */
    protected function _load_view($view, $vars = array(), $return = FALSE) {
        // Merge with cached vars
        $vars = array_merge($this->cached_vars, $vars);
        
        // Find view file
        $view_file = '';
        $ext = pathinfo($view, PATHINFO_EXTENSION);
        $file = ($ext === '') ? $view . '.php' : $view;
        
        foreach ($this->view_paths as $path) {
            if (file_exists($path . $file)) {
                $view_file = $path . $file;
                break;
            }
        }
        
        if (empty($view_file)) {
            throw new RuntimeException('Unable to load the requested view: ' . $view);
        }
        
        // Extract variables
        extract($vars);
        
        // Capture output
        ob_start();
        include($view_file);
        $output = ob_get_clean();
        
        if ($return) {
            return $output;
        } else {
            echo $output;
            return $this;
        }
    }
}

/**
 * Base Model Class for PDO
 */
class PDO_Model {
    
    /**
     * PDO Database Connection
     * @var PDO
     */
    protected $db;
    
    /**
     * Table name
     * @var string
     */
    protected $table = '';
    
    /**
     * Primary key
     * @var string
     */
    protected $primary_key = 'id';
    
    /**
     * Constructor
     * 
     * @param PDO $database
     */
    public function __construct(PDO $database = null) {
        $this->db = $database;
    }
    
    /**
     * Set Database Connection
     * 
     * @param PDO $database
     * @return $this
     */
    public function set_database(PDO $database) {
        $this->db = $database;
        return $this;
    }
    
    /**
     * Execute Query
     * 
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function query($sql, $params = array()) {
        if (!$this->db) {
            throw new RuntimeException('No database connection available');
        }
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException('Database query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Find record by ID
     * 
     * @param mixed $id
     * @return array|false
     */
    public function find($id) {
        if (empty($this->table)) {
            throw new RuntimeException('Table name not set');
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primary_key} = :id LIMIT 1";
        $stmt = $this->query($sql, array(':id' => $id));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all records
     * 
     * @param array $where Where conditions
     * @param string $order Order by
     * @param int $limit Limit
     * @return array
     */
    public function get_all($where = array(), $order = '', $limit = 0) {
        if (empty($this->table)) {
            throw new RuntimeException('Table name not set');
        }
        
        $sql = "SELECT * FROM {$this->table}";
        $params = array();
        
        // Add WHERE conditions
        if (!empty($where)) {
            $conditions = array();
            foreach ($where as $key => $value) {
                $conditions[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Add ORDER BY
        if (!empty($order)) {
            $sql .= " ORDER BY {$order}";
        }
        
        // Add LIMIT
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Insert record
     * 
     * @param array $data
     * @return string Last insert ID
     */
    public function insert($data) {
        if (empty($this->table)) {
            throw new RuntimeException('Table name not set');
        }
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":{$col}"; }, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $params = array();
        foreach ($data as $key => $value) {
            $params[":{$key}"] = $value;
        }
        
        $this->query($sql, $params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Update record
     * 
     * @param mixed $id
     * @param array $data
     * @return int Affected rows
     */
    public function update($id, $data) {
        if (empty($this->table)) {
            throw new RuntimeException('Table name not set');
        }
        
        $sets = array();
        $params = array(':id' => $id);
        
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primary_key} = :id";
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete record
     * 
     * @param mixed $id
     * @return int Affected rows
     */
    public function delete($id) {
        if (empty($this->table)) {
            throw new RuntimeException('Table name not set');
        }
        
        $sql = "DELETE FROM {$this->table} WHERE {$this->primary_key} = :id";
        $stmt = $this->query($sql, array(':id' => $id));
        return $stmt->rowCount();
    }
}

/**
 * Usage Example:
 * 
 * // Create PDO connection
 * $pdo = new PDO('mysql:host=localhost;dbname=test', $username, $password, [
 *     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 *     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
 * ]);
 * 
 * // Create loader
 * $loader = new PDO_Loader($pdo, [
 *     'view_path' => './views/',
 *     'model_path' => './models/',
 *     'library_path' => './libraries/'
 * ]);
 * 
 * // Load model
 * $loader->model('user_model');
 * $users = $loader->user_model->get_all();
 * 
 * // Load view with data
 * $loader->view('user_list', ['users' => $users]);
 * 
 * // Direct database queries
 * $stmt = $loader->query("SELECT * FROM users WHERE status = :status", [':status' => 'active']);
 * $active_users = $stmt->fetchAll();
 */
?>