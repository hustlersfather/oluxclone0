<?php
/**
 * PDO Application Bootstrap
 *
 * Converted from CodeIgniter to use PDO for database operations
 * 
 * This content is released under the MIT License (MIT)
 *
 * @package     PDO_Framework
 * @author      Converted from CodeIgniter
 * @license     MIT License
 */

/**
 * Framework Version
 *
 * @var string
 */
const FRAMEWORK_VERSION = '1.0.0';

// Start output buffering
ob_start();

/*
 * ------------------------------------------------------
 *  Define framework constants
 * ------------------------------------------------------
 */
if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__FILE__) . '/');
}

if (!defined('APPPATH')) {
    define('APPPATH', BASEPATH . 'application/');
}

if (!defined('VIEWPATH')) {
    define('VIEWPATH', APPPATH . 'views/');
}

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', isset($_SERVER['ENVIRONMENT']) ? $_SERVER['ENVIRONMENT'] : 'development');
}

/*
 * ------------------------------------------------------
 *  Load configuration files
 * ------------------------------------------------------
 */
$config = [];
if (file_exists(APPPATH . 'config/' . ENVIRONMENT . '/config.php')) {
    require_once(APPPATH . 'config/' . ENVIRONMENT . '/config.php');
}
if (file_exists(APPPATH . 'config/config.php')) {
    require_once(APPPATH . 'config/config.php');
}

// Load database configuration
$database_config = [];
if (file_exists(APPPATH . 'config/' . ENVIRONMENT . '/database.php')) {
    require_once(APPPATH . 'config/' . ENVIRONMENT . '/database.php');
}
if (file_exists(APPPATH . 'config/database.php')) {
    require_once(APPPATH . 'config/database.php');
}

/*
 * ------------------------------------------------------
 *  Load core functions
 * ------------------------------------------------------
 */
require_once(BASEPATH . 'core/Common.php');

/*
 * ------------------------------------------------------
 *  Security and compatibility checks
 * ------------------------------------------------------
 */
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    exit('PHP 7.4 or higher is required.');
}

// Set error reporting based on environment
switch (ENVIRONMENT) {
    case 'development':
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        break;
    case 'testing':
    case 'production':
        ini_set('display_errors', 0);
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        break;
    default:
        header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
        echo 'The application environment is not set correctly.';
        exit(1);
}

/*
 * ------------------------------------------------------
 *  Set custom error handlers
 * ------------------------------------------------------
 */
set_error_handler('framework_error_handler');
set_exception_handler('framework_exception_handler');
register_shutdown_function('framework_shutdown_handler');

/*
 * ------------------------------------------------------
 *  Initialize PDO database connection
 * ------------------------------------------------------
 */
class DatabaseManager
{
    private static $connections = [];
    
    public static function connect($group = 'default')
    {
        global $database_config;
        
        if (isset(self::$connections[$group])) {
            return self::$connections[$group];
        }
        
        if (!isset($database_config[$group])) {
            throw new Exception("Database configuration for '{$group}' not found");
        }
        
        $db = $database_config[$group];
        
        try {
            $dsn = "{$db['dbdriver']}:host={$db['hostname']};dbname={$db['database']};charset={$db['char_set']}";
            if (!empty($db['port'])) {
                $dsn .= ";port={$db['port']}";
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => $db['pconnect'] ?? false
            ];
            
            self::$connections[$group] = new PDO($dsn, $db['username'], $db['password'], $options);
            
            // Set additional PDO attributes if specified
            if (!empty($db['stricton'])) {
                self::$connections[$group]->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
            }
            
            return self::$connections[$group];
            
        } catch (PDOException $e) {
            log_message('error', 'Database connection failed: ' . $e->getMessage());
            show_error('Database connection failed', 500, 'Database Error');
        }
    }
    
    public static function closeConnection($group = 'default')
    {
        if (isset(self::$connections[$group])) {
            self::$connections[$group] = null;
            unset(self::$connections[$group]);
        }
    }
    
    public static function getAllConnections()
    {
        return self::$connections;
    }
}

/*
 * ------------------------------------------------------
 *  Initialize Composer autoloader if available
 * ------------------------------------------------------
 */
if (!empty($config['composer_autoload'])) {
    if ($config['composer_autoload'] === TRUE) {
        if (file_exists(APPPATH . 'vendor/autoload.php')) {
            require_once(APPPATH . 'vendor/autoload.php');
        } else {
            log_message('error', 'Composer autoload enabled but vendor/autoload.php not found');
        }
    } elseif (file_exists($config['composer_autoload'])) {
        require_once($config['composer_autoload']);
    } else {
        log_message('error', 'Could not find composer autoload file: ' . $config['composer_autoload']);
    }
}

/*
 * ------------------------------------------------------
 *  Initialize benchmark and timing
 * ------------------------------------------------------
 */
require_once(BASEPATH . 'core/Benchmark.php');
$benchmark = new PDO_Benchmark();
$benchmark->mark('total_execution_time_start');
$benchmark->mark('bootstrap_start');

/*
 * ------------------------------------------------------
 *  Initialize core framework classes
 * ------------------------------------------------------
 */
require_once(BASEPATH . 'core/Hooks.php');
require_once(BASEPATH . 'core/Config.php');
require_once(BASEPATH . 'core/URI.php');
require_once(BASEPATH . 'core/Router.php');
require_once(BASEPATH . 'core/Output.php');
require_once(BASEPATH . 'core/Security.php');
require_once(BASEPATH . 'core/Input.php');

// Initialize core classes
$hooks = new Hooks();
$config_obj = new Config($config);
$uri = new URI();
$router = new Router($uri);
$output = new Output();
$security = new Security();
$input = new Input($security);

// Call pre-system hook
$hooks->call_hook('pre_system');

/*
 * ------------------------------------------------------
 *  Set charset and encoding
 * ------------------------------------------------------
 */
$charset = $config['charset'] ?? 'UTF-8';
ini_set('default_charset', $charset);

if (extension_loaded('mbstring')) {
    define('MB_ENABLED', TRUE);
    mb_internal_encoding($charset);
    mb_substitute_character('none');
} else {
    define('MB_ENABLED', FALSE);
}

if (extension_loaded('iconv')) {
    define('ICONV_ENABLED', TRUE);
    iconv_set_encoding('internal_encoding', $charset);
} else {
    define('ICONV_ENABLED', FALSE);
}

/*
 * ------------------------------------------------------
 *  Initialize database connection
 * ------------------------------------------------------
 */
try {
    $db = DatabaseManager::connect();
    // Make database available globally
    $GLOBALS['db'] = $db;
} catch (Exception $e) {
    if (ENVIRONMENT === 'development') {
        show_error($e->getMessage(), 500, 'Database Connection Error');
    } else {
        show_error('A database error occurred', 500, 'System Error');
    }
}

/*
 * ------------------------------------------------------
 *  Load base controller
 * ------------------------------------------------------
 */
require_once(BASEPATH . 'core/Controller.php');

/*
 * ------------------------------------------------------
 *  Route the request
 * ------------------------------------------------------
 */
$benchmark->mark('routing_start');

$controller_name = $router->get_class();
$method_name = $router->get_method();
$params = $router->get_params();

// Check if controller file exists
$controller_file = APPPATH . 'controllers/' . $router->get_directory() . $controller_name . '.php';

if (!file_exists($controller_file)) {
    show_404();
}

require_once($controller_file);

// Check if controller class exists
if (!class_exists($controller_name)) {
    show_404();
}

$benchmark->mark('routing_end');

/*
 * ------------------------------------------------------
 *  Pre-controller hook
 * ------------------------------------------------------
 */
$hooks->call_hook('pre_controller');

/*
 * ------------------------------------------------------
 *  Instantiate and execute controller
 * ------------------------------------------------------
 */
$benchmark->mark('controller_execution_start');

try {
    // Create controller instance with dependencies
    $controller = new $controller_name($db, $config_obj, $input, $output, $uri, $security);
    
    // Post controller constructor hook
    $hooks->call_hook('post_controller_constructor');
    
    // Check if method exists and is callable
    if (!method_exists($controller, $method_name)) {
        if (method_exists($controller, '_remap')) {
            $controller->_remap($method_name, $params);
        } else {
            show_404();
        }
    } else {
        // Check if method is public
        $reflection = new ReflectionMethod($controller, $method_name);
        if (!$reflection->isPublic() || $reflection->isConstructor()) {
            show_404();
        }
        
        // Call the controller method
        call_user_func_array([$controller, $method_name], $params);
    }
    
} catch (Exception $e) {
    log_message('error', 'Controller execution error: ' . $e->getMessage());
    show_error('An error occurred while processing your request', 500);
}

$benchmark->mark('controller_execution_end');

/*
 * ------------------------------------------------------
 *  Post-controller hook
 * ------------------------------------------------------
 */
$hooks->call_hook('post_controller');

/*
 * ------------------------------------------------------
 *  Display output
 * ------------------------------------------------------
 */
if ($hooks->call_hook('display_override') === FALSE) {
    $output->display();
}

/*
 * ------------------------------------------------------
 *  Post-system hook and cleanup
 * ------------------------------------------------------
 */
$hooks->call_hook('post_system');

// Mark end of execution
$benchmark->mark('total_execution_time_end');

// Log execution time in development mode
if (ENVIRONMENT === 'development') {
    $execution_time = $benchmark->elapsed_time('total_execution_time_start', 'total_execution_time_end');
    log_message('info', "Total execution time: {$execution_time} seconds");
}

// Close database connections
DatabaseManager::closeConnection();

/*
 * ------------------------------------------------------
 *  Helper functions
 * ------------------------------------------------------
 */
function &get_instance()
{
    // Return current controller instance
    static $instance;
    return $instance;
}

function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered')
{
    http_response_code($status_code);
    echo "<h1>{$heading}</h1>";
    echo "<p>{$message}</p>";
    exit();
}

function show_404()
{
    http_response_code(404);
    echo "<h1>404 Page Not Found</h1>";
    echo "<p>The page you requested was not found.</p>";
    exit();
}

function log_message($level, $message)
{
    $log_path = APPPATH . 'logs/';
    if (!is_dir($log_path)) {
        mkdir($log_path, 0755, true);
    }
    
    $filename = $log_path . 'log-' . date('Y-m-d') . '.log';
    $log_entry = date('Y-m-d H:i:s') . " [{$level}] {$message}" . PHP_EOL;
    
    file_put_contents($filename, $log_entry, FILE_APPEND | LOCK_EX);
}

function framework_error_handler($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $error_msg = "Error [{$severity}]: {$message} in {$file} on line {$line}";
    log_message('error', $error_msg);
    
    if (ENVIRONMENT === 'development') {
        echo "<strong>Error:</strong> {$message} in <strong>{$file}</strong> on line <strong>{$line}</strong><br>";
    }
}

function framework_exception_handler($exception)
{
    $error_msg = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    log_message('error', $error_msg);
    
    if (ENVIRONMENT === 'development') {
        echo "<h1>Uncaught Exception</h1>";
        echo "<p><strong>Message:</strong> " . $exception->getMessage() . "</p>";
        echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    } else {
        show_error('An unexpected error occurred');
    }
}

function framework_shutdown_handler()
{
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $error_msg = "Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}";
        log_message('error', $error_msg);
        
        if (ENVIRONMENT === 'development') {
            echo "<h1>Fatal Error</h1>";
            echo "<p>{$error_msg}</p>";
        } else {
            show_error('A fatal error occurred');
        }
    }
}
?>