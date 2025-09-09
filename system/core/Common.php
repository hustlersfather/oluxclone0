<?php
/**
 * PDO Framework Common Functions
 *
 * Converted from CodeIgniter to use PDO for database operations
 * 
 * This content is released under the MIT License (MIT)
 *
 * @package     PDO_Framework
 * @author      Converted from CodeIgniter
 * @license     MIT License
 */

// Prevent direct access
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// ------------------------------------------------------------------------

if (!function_exists('is_php')) {
    /**
     * Determines if the current version of PHP is equal to or greater than the supplied value
     *
     * @param string $version
     * @return bool TRUE if the current version is $version or higher
     */
    function is_php($version)
    {
        static $_is_php = [];
        $version = (string) $version;

        if (!isset($_is_php[$version])) {
            $_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
        }

        return $_is_php[$version];
    }
}

// ------------------------------------------------------------------------

if (!function_exists('is_really_writable')) {
    /**
     * Tests for file writability
     *
     * @param string $file
     * @return bool
     */
    function is_really_writable($file)
    {
        // For Unix systems
        if (DIRECTORY_SEPARATOR === '/') {
            return is_writable($file);
        }

        // For Windows or when we need to be extra sure
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === FALSE) {
                return FALSE;
            }

            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        } elseif (!is_file($file) || ($fp = @fopen($file, 'ab')) === FALSE) {
            return FALSE;
        }

        fclose($fp);
        return TRUE;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('load_class')) {
    /**
     * Class registry and loader
     *
     * @param string $class Class name
     * @param string $directory Directory to load from
     * @param mixed $param Optional parameter for constructor
     * @return object
     */
    function &load_class($class, $directory = 'libraries', $param = NULL)
    {
        static $_classes = [];

        // Return if already loaded
        if (isset($_classes[$class])) {
            return $_classes[$class];
        }

        $name = FALSE;
        $class_file = $class . '.php';

        // Look in application directory first, then framework directory
        foreach ([APPPATH, BASEPATH] as $path) {
            $file_path = $path . $directory . '/' . $class_file;
            if (file_exists($file_path)) {
                $name = $class;
                
                if (!class_exists($name, FALSE)) {
                    require_once($file_path);
                }
                break;
            }
        }

        // Check for extended class
        $subclass_prefix = config_item('subclass_prefix') ?: 'MY_';
        $extended_class = APPPATH . $directory . '/' . $subclass_prefix . $class_file;
        
        if (file_exists($extended_class)) {
            $name = $subclass_prefix . $class;
            if (!class_exists($name, FALSE)) {
                require_once($extended_class);
            }
        }

        // Class not found
        if ($name === FALSE) {
            http_response_code(503);
            echo 'Unable to locate the specified class: ' . $class . '.php';
            exit(5);
        }

        // Track loaded classes
        is_loaded($class);

        // Instantiate class
        $_classes[$class] = isset($param) ? new $name($param) : new $name();
        
        return $_classes[$class];
    }
}

// ------------------------------------------------------------------------

if (!function_exists('is_loaded')) {
    /**
     * Keeps track of loaded libraries
     *
     * @param string $class
     * @return array
     */
    function &is_loaded($class = '')
    {
        static $_is_loaded = [];

        if ($class !== '') {
            $_is_loaded[strtolower($class)] = $class;
        }

        return $_is_loaded;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('get_config')) {
    /**
     * Loads configuration file
     *
     * @param array $replace
     * @return array
     */
    function &get_config(array $replace = [])
    {
        static $config;

        if (empty($config)) {
            $config = [];
            $found = FALSE;

            // Load main config file
            $file_path = APPPATH . 'config/config.php';
            if (file_exists($file_path)) {
                $found = TRUE;
                require($file_path);
            }

            // Load environment-specific config
            $env_config = APPPATH . 'config/' . ENVIRONMENT . '/config.php';
            if (file_exists($env_config)) {
                require($env_config);
            } elseif (!$found) {
                http_response_code(503);
                echo 'The configuration file does not exist.';
                exit(3);
            }

            // Validate config array
            if (!isset($config) || !is_array($config)) {
                http_response_code(503);
                echo 'Your config file does not appear to be formatted correctly.';
                exit(3);
            }
        }

        // Apply dynamic replacements
        foreach ($replace as $key => $val) {
            $config[$key] = $val;
        }

        return $config;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('config_item')) {
    /**
     * Returns specified config item
     *
     * @param string $item
     * @return mixed
     */
    function config_item($item)
    {
        static $_config;

        if (empty($_config)) {
            $_config[0] =& get_config();
        }

        return $_config[0][$item] ?? NULL;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('get_database_config')) {
    /**
     * Load database configuration
     *
     * @param string $group Database group name
     * @return array
     */
    function get_database_config($group = 'default')
    {
        static $db_config;

        if (empty($db_config)) {
            $db = [];

            // Load main database config
            $file_path = APPPATH . 'config/database.php';
            if (file_exists($file_path)) {
                require($file_path);
            }

            // Load environment-specific database config
            $env_db_config = APPPATH . 'config/' . ENVIRONMENT . '/database.php';
            if (file_exists($env_db_config)) {
                require($env_db_config);
            }

            $db_config = $db ?? [];
        }

        return $db_config[$group] ?? [];
    }
}

// ------------------------------------------------------------------------

if (!function_exists('get_pdo_connection')) {
    /**
     * Get PDO database connection
     *
     * @param string $group Database group
     * @return PDO
     */
    function get_pdo_connection($group = 'default')
    {
        static $connections = [];

        if (isset($connections[$group])) {
            return $connections[$group];
        }

        $db_config = get_database_config($group);
        
        if (empty($db_config)) {
            throw new Exception("Database configuration for group '{$group}' not found");
        }

        try {
            $dsn = "{$db_config['dbdriver']}:host={$db_config['hostname']};dbname={$db_config['database']};charset=" . ($db_config['char_set'] ?? 'utf8');
            
            if (!empty($db_config['port'])) {
                $dsn .= ";port={$db_config['port']}";
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => $db_config['pconnect'] ?? false
            ];

            $connections[$group] = new PDO(
                $dsn, 
                $db_config['username'], 
                $db_config['password'], 
                $options
            );

            return $connections[$group];

        } catch (PDOException $e) {
            log_message('error', 'Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
}

// ------------------------------------------------------------------------

if (!function_exists('get_mimes')) {
    /**
     * Returns MIME types array
     *
     * @return array
     */
    function &get_mimes()
    {
        static $_mimes;

        if (empty($_mimes)) {
            $_mimes = [];

            if (file_exists(APPPATH . 'config/mimes.php')) {
                $_mimes = include(APPPATH . 'config/mimes.php');
            }

            $env_mimes = APPPATH . 'config/' . ENVIRONMENT . '/mimes.php';
            if (file_exists($env_mimes)) {
                $_mimes = array_merge($_mimes, include($env_mimes));
            }
        }

        return $_mimes;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('is_https')) {
    /**
     * Determines if application is accessed via HTTPS
     *
     * @return bool
     */
    function is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return TRUE;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return TRUE;
        }

        if (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && 
            strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return TRUE;
        }

        return FALSE;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('is_cli')) {
    /**
     * Test if request was made from command line
     *
     * @return bool
     */
    function is_cli()
    {
        return PHP_SAPI === 'cli' || defined('STDIN');
    }
}

// ------------------------------------------------------------------------

if (!function_exists('show_error')) {
    /**
     * Error handler - displays error page
     *
     * @param string $message
     * @param int $status_code
     * @param string $heading
     * @return void
     */
    function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered')
    {
        $status_code = abs($status_code);
        
        if ($status_code < 100) {
            $exit_status = $status_code + 9;
            $status_code = 500;
        } else {
            $exit_status = 1;
        }

        http_response_code($status_code);
        
        // Try to load error template
        $error_template = APPPATH . 'views/errors/error_general.php';
        
        if (file_exists($error_template)) {
            include($error_template);
        } else {
            echo "<h1>{$heading}</h1>";
            echo "<p>{$message}</p>";
            echo "<p>Status Code: {$status_code}</p>";
        }
        
        exit($exit_status);
    }
}

// ------------------------------------------------------------------------

if (!function_exists('show_404')) {
    /**
     * 404 Page handler
     *
     * @param string $page
     * @param bool $log_error
     * @return void
     */
    function show_404($page = '', $log_error = TRUE)
    {
        if ($log_error) {
            log_message('error', '404 Page Not Found: ' . $page);
        }

        http_response_code(404);
        
        $error_404_template = APPPATH . 'views/errors/error_404.php';
        
        if (file_exists($error_404_template)) {
            include($error_404_template);
        } else {
            echo "<h1>404 Page Not Found</h1>";
            echo "<p>The page you requested was not found: {$page}</p>";
        }
        
        exit(4);
    }
}

// ------------------------------------------------------------------------

if (!function_exists('log_message')) {
    /**
     * Error logging interface
     *
     * @param string $level
     * @param string $message
     * @return void
     */
    function log_message($level, $message)
    {
        static $log_path;
        
        if ($log_path === null) {
            $log_path = config_item('log_path') ?: APPPATH . 'logs/';
            
            if (!is_dir($log_path)) {
                mkdir($log_path, 0755, true);
            }
        }

        $filename = $log_path . 'log-' . date('Y-m-d') . '.log';
        $log_entry = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents($filename, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// ------------------------------------------------------------------------

if (!function_exists('set_status_header')) {
    /**
     * Set HTTP status header
     *
     * @param int $code
     * @param string $text
     * @return void
     */
    function set_status_header($code = 200, $text = '')
    {
        if (is_cli()) {
            return;
        }

        if (empty($code) || !is_numeric($code)) {
            show_error('Status codes must be numeric', 500);
        }

        if (empty($text)) {
            $stati = [
                // 1xx Informational
                100 => 'Continue',
                101 => 'Switching Protocols',
                
                // 2xx Success
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                
                // 3xx Redirection
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',
                308 => 'Permanent Redirect',
                
                // 4xx Client Error
                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Payload Too Large',
                414 => 'URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Range Not Satisfiable',
                417 => 'Expectation Failed',
                422 => 'Unprocessable Entity',
                426 => 'Upgrade Required',
                428 => 'Precondition Required',
                429 => 'Too Many Requests',
                431 => 'Request Header Fields Too Large',
                
                // 5xx Server Error
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported',
                511 => 'Network Authentication Required',
            ];

            $text = $stati[$code] ?? 'Unknown Status';
        }

        http_response_code($code);
        
        if (strpos(PHP_SAPI, 'cgi') === 0) {
            header("Status: {$code} {$text}", TRUE);
        } else {
            $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
            header("{$protocol} {$code} {$text}", TRUE, $code);
        }
    }
}

// ------------------------------------------------------------------------

if (!function_exists('remove_invisible_characters')) {
    /**
     * Remove invisible characters
     *
     * @param string $str
     * @param bool $url_encoded
     * @return string
     */
    function remove_invisible_characters($str, $url_encoded = TRUE)
    {
        $non_displayables = [];

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/i';
            $non_displayables[] = '/%1[0-9a-f]/i';
            $non_displayables[] = '/%7f/i';
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('html_escape')) {
    /**
     * Returns HTML escaped variable
     *
     * @param mixed $var
     * @param bool $double_encode
     * @return mixed
     */
    function html_escape($var, $double_encode = TRUE)
    {
        if (empty($var)) {
            return $var;
        }

        if (is_array($var)) {
            foreach (array_keys($var) as $key) {
                $var[$key] = html_escape($var[$key], $double_encode);
            }
            return $var;
        }

        $charset = config_item('charset') ?: 'UTF-8';
        return htmlspecialchars($var, ENT_QUOTES, $charset, $double_encode);
    }
}

// ------------------------------------------------------------------------

if (!function_exists('stringify_attributes')) {
    /**
     * Stringify attributes for HTML tags
     *
     * @param mixed $attributes
     * @param bool $js
     * @return string
     */
    function stringify_attributes($attributes, $js = FALSE)
    {
        if (empty($attributes)) {
            return '';
        }

        if (is_string($attributes)) {
            return ' ' . $attributes;
        }

        $attributes = (array) $attributes;
        $atts = '';

        foreach ($attributes as $key => $val) {
            $atts .= $js ? "{$key}={$val}," : " {$key}=\"{$val}\"";
        }

        return rtrim($atts, ',');
    }
}

// ------------------------------------------------------------------------

if (!function_exists('function_usable')) {
    /**
     * Function usable - checks if function exists and is not disabled
     *
     * @param string $function_name
     * @return bool
     */
    function function_usable($function_name)
    {
        static $_disabled_functions;

        if (function_exists($function_name)) {
            if (!isset($_disabled_functions)) {
                $_disabled_functions = array_map('trim', explode(',', ini_get('disable_functions')));
            }

            return !in_array($function_name, $_disabled_functions, TRUE);
        }

        return FALSE;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('db_query')) {
    /**
     * Execute PDO query with optional parameters
     *
     * @param string $sql
     * @param array $params
     * @param string $group
     * @return PDOStatement
     */
    function db_query($sql, $params = [], $group = 'default')
    {
        try {
            $pdo = get_pdo_connection($group);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            log_message('error', 'Database query failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

// ------------------------------------------------------------------------

if (!function_exists('db_fetch')) {
    /**
     * Fetch single row from database
     *
     * @param string $sql
     * @param array $params
     * @param string $group
     * @return array|false
     */
    function db_fetch($sql, $params = [], $group = 'default')
    {
        $stmt = db_query($sql, $params, $group);
        return $stmt->fetch();
    }
}

// ------------------------------------------------------------------------

if (!function_exists('db_fetch_all')) {
    /**
     * Fetch all rows from database
     *
     * @param string $sql
     * @param array $params
     * @param string $group
     * @return array
     */
    function db_fetch_all($sql, $params = [], $group = 'default')
    {
        $stmt = db_query($sql, $params, $group);
        return $stmt->fetchAll();
    }
}

// ------------------------------------------------------------------------

if (!function_exists('db_insert')) {
    /**
     * Insert data into database
     *
     * @param string $table
     * @param array $data
     * @param string $group
     * @return bool
     */
    function db_insert($table, $data, $group = 'default')
    {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = db_query($sql, $data, $group);
        return $stmt->rowCount() > 0;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('db_update')) {
    /**
     * Update data in database
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @param string $group
     * @return bool
     */
    function db_update($table, $data, $where, $group = 'default')
    {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "{$key} = :{$key}";
        }
        $set_clause = implode(', ', $set);
        
        $where_conditions = [];
        foreach (array_keys($where) as $key) {
            $where_conditions[] = "{$key} = :where_{$key}";
        }
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "UPDATE {$table} SET {$set_clause} WHERE {$where_clause}";
        
        // Merge data and where parameters
        $params = $data;
        foreach ($where as $key => $value) {
            $params["where_{$key}"] = $value;
        }
        
        $stmt = db_query($sql, $params, $group);
        return $stmt->rowCount() > 0;
    }
}

// ------------------------------------------------------------------------

if (!function_exists('db_delete')) {
    /**
     * Delete data from database
     *
     * @param string $table
     * @param array $where
     * @param string $group
     * @return bool
     */
    function db_delete($table, $where, $group = 'default')
    {
        $where_conditions = [];
        foreach (array_keys($where) as $key) {
            $where_conditions[] = "{$key} = :{$key}";
        }
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "DELETE FROM {$table} WHERE {$where_clause}";
        $stmt = db_query($sql, $where, $group);
        return $stmt->rowCount() > 0;
    }
}

/***
Here’s how to use the PDO framework components we’ve converted:

## Basic Setup

### 1. Directory Structure

```
project/
├── application/
│   ├── config/
│   │   ├── config.php
│   │   ├── database.php
│   │   └── development/
│   ├── controllers/
│   ├── views/
│   ├── logs/
│   └── core/
├── system/
│   └── core/
│       ├── Common.php
│       ├── Benchmark.php
│       └── Controller.php
└── index.php
```

### 2. Configuration Files

**application/config/database.php:**

```php
<?php
$db['default'] = [
    'dbdriver' => 'mysql',
    'hostname' => 'localhost',
    'username' => 'your_username',
    'password' => 'your_password',
    'database' => 'your_database',
    'char_set' => 'utf8mb4',
    'port' => 3306,
    'pconnect' => false,
    'stricton' => true
];
```

**application/config/config.php:**

```php
<?php
$config['base_url'] = 'http://localhost/';
$config['charset'] = 'UTF-8';
$config['subclass_prefix'] = 'MY_';
$config['log_path'] = APPPATH . 'logs/';
```

## Usage Examples

### 1. Basic Database Operations

```php
<?php
// In a controller or anywhere in your application

// Simple select query
$users = db_fetch_all("SELECT * FROM users WHERE active = ?", [1]);

// Fetch single row
$user = db_fetch("SELECT * FROM users WHERE id = ?", [123]);

// Insert data
$success = db_insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

// Update data
$updated = db_update('users', 
    ['name' => 'Jane Doe', 'updated_at' => date('Y-m-d H:i:s')],
    ['id' => 123]
);

// Delete data
$deleted = db_delete('users', ['id' => 123]);
```

### 2. Advanced Database Usage

```php
<?php
// Raw PDO connection for complex operations
$pdo = get_pdo_connection();

// Transaction example
try {
    $pdo->beginTransaction();
    
    db_insert('orders', [
        'user_id' => 1,
        'total' => 99.99,
        'status' => 'pending'
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    db_insert('order_items', [
        'order_id' => $order_id,
        'product_id' => 5,
        'quantity' => 2
    ]);
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    log_message('error', 'Transaction failed: ' . $e->getMessage());
}
```

### 3. Controller Example

```php
<?php
// application/controllers/Users.php
class Users extends Controller
{
    public function __construct($db, $config, $input, $output, $uri, $security)
    {
        parent::__construct($db, $config, $input, $output, $uri, $security);
    }
    
    public function index()
    {
        $users = db_fetch_all("SELECT * FROM users ORDER BY created_at DESC");
        $this->load_view('users/index', ['users' => $users]);
    }
    
    public function create()
    {
        if ($_POST) {
            $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (db_insert('users', $data)) {
                redirect('users');
            } else {
                show_error('Failed to create user');
            }
        }
        
        $this->load_view('users/create');
    }
    
    public function edit($id)
    {
        $user = db_fetch("SELECT * FROM users WHERE id = ?", [$id]);
        
        if (!$user) {
            show_404();
        }
        
        if ($_POST) {
            $data = [
                'name' => $this->input->post('name'),
                'email' => $this->input->post('email'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (db_update('users', $data, ['id' => $id])) {
                redirect('users');
            }
        }
        
        $this->load_view('users/edit', ['user' => $user]);
    }
}
```

### 4. Using Benchmarking

```php
<?php
// Initialize benchmark
$benchmark = new PDO_Benchmark(get_pdo_connection());

// Create table for storing benchmarks
$benchmark->createBenchmarkTable();

// Mark start
$benchmark->mark('operation_start');

// Your code here
$users = db_fetch_all("SELECT * FROM users");

// Mark end and store in database
$benchmark->mark('operation_end', true);

// Get elapsed time
echo "Operation took: " . $benchmark->elapsed_time('operation_start', 'operation_end') . " seconds";

// Get statistics from database
$stats = $benchmark->getStatsFromDB();
```

### 5. Configuration and Helpers

```php
<?php
// Get configuration items
$base_url = config_item('base_url');
$charset = config_item('charset');

// HTML escaping
$safe_output = html_escape($user_input);

// Check HTTPS
if (is_https()) {
    // Handle HTTPS specific logic
}

// Logging
log_message('info', 'User logged in: ' . $user_id);
log_message('error', 'Database connection failed');
log_message('debug', 'Processing order: ' . $order_id);
```

### 6. Error Handling

```php
<?php
// Show custom error
show_error('Something went wrong', 500, 'System Error');

// Show 404 page
show_404('users/nonexistent');

// Custom error handling
try {
    $result = db_fetch("SELECT * FROM nonexistent_table");
} catch (PDOException $e) {
    log_message('error', 'Database error: ' . $e->getMessage());
    show_error('Database temporarily unavailable', 503);
}
```

### 7. Multiple Database Connections

```php
<?php
// application/config/database.php
$db['default'] = [
    'dbdriver' => 'mysql',
    'hostname' => 'localhost',
    'database' => 'main_db',
    // ... other config
];

$db['analytics'] = [
    'dbdriver' => 'mysql',
    'hostname' => 'analytics-server',
    'database' => 'analytics_db',
    // ... other config
];

// Usage
$users = db_fetch_all("SELECT * FROM users", [], 'default');
$stats = db_fetch_all("SELECT * FROM page_views", [], 'analytics');
```

This framework provides a clean, modern PHP structure with PDO database integration while maintaining familiar patterns for developers coming from CodeIgniter.​​​​​​​​​​​​​​​​

**/
?>