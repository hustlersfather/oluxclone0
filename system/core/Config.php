<?php
/**
 * Modern Config Class with PDO Support
 *
 * A refactored configuration management class that uses PDO for database
 * operations and define() constants for configuration values.
 *
 * @package     ModernFramework
 * @author      Refactored Version
 * @license     MIT License
 * @version     2.0.0
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    exit('No direct script access allowed');
}

/**
 * Config Class
 *
 * This class manages configuration files and provides PDO database connectivity
 *
 * @package     ModernFramework
 * @subpackage  Libraries
 * @category    Configuration
 */
class PDO_Config {

    /**
     * List of all loaded config values
     *
     * @var array
     */
    public $config = array();

    /**
     * List of all loaded config files
     *
     * @var array
     */
    public $is_loaded = array();

    /**
     * List of paths to search when trying to load a config file
     *
     * @var array
     */
    public $_config_paths = array();

    /**
     * PDO database connection instance
     *
     * @var PDO|null
     */
    protected $pdo_connection = null;

    /**
     * Database configuration
     *
     * @var array
     */
    protected $db_config = array();

    // --------------------------------------------------------------------

    /**
     * Class constructor
     *
     * Initializes the configuration system and sets up default values
     *
     * @return void
     */
    public function __construct()
    {
        // Define default configuration paths
        if (defined('APP_PATH')) {
            $this->_config_paths[] = APP_PATH;
        }

        // Load primary configuration
        $this->load_primary_config();

        // Set the base_url automatically if none was provided
        $this->auto_set_base_url();

        // Initialize database configuration if available
        $this->init_database_config();

        $this->log_message('info', 'PDO Config Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Load primary configuration and define constants
     *
     * @return void
     */
    protected function load_primary_config()
    {
        $config_file = $this->find_config_file('config');
        
        if ($config_file && file_exists($config_file)) {
            include($config_file);
            
            if (isset($config) && is_array($config)) {
                $this->config = array_merge($this->config, $config);
                
                // Define constants for frequently used config items
                $this->define_config_constants($config);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Define configuration constants using define()
     *
     * @param array $config Configuration array
     * @return void
     */
    protected function define_config_constants($config)
    {
        $constants_map = array(
            'base_url' => 'BASE_URL',
            'index_page' => 'INDEX_PAGE',
            'uri_protocol' => 'URI_PROTOCOL',
            'url_suffix' => 'URL_SUFFIX',
            'language' => 'DEFAULT_LANGUAGE',
            'charset' => 'CHARSET',
            'enable_hooks' => 'ENABLE_HOOKS',
            'subclass_prefix' => 'SUBCLASS_PREFIX',
            'composer_autoload' => 'COMPOSER_AUTOLOAD',
            'permitted_uri_chars' => 'PERMITTED_URI_CHARS',
            'allow_get_array' => 'ALLOW_GET_ARRAY',
            'enable_query_strings' => 'ENABLE_QUERY_STRINGS',
            'controller_trigger' => 'CONTROLLER_TRIGGER',
            'function_trigger' => 'FUNCTION_TRIGGER',
            'directory_trigger' => 'DIRECTORY_TRIGGER',
            'log_threshold' => 'LOG_THRESHOLD',
            'log_path' => 'LOG_PATH',
            'log_file_extension' => 'LOG_FILE_EXTENSION',
            'log_file_permissions' => 'LOG_FILE_PERMISSIONS',
            'log_date_format' => 'LOG_DATE_FORMAT',
            'error_views_path' => 'ERROR_VIEWS_PATH',
            'cache_path' => 'CACHE_PATH',
            'cache_query_string' => 'CACHE_QUERY_STRING',
            'encryption_key' => 'ENCRYPTION_KEY',
            'sess_driver' => 'SESSION_DRIVER',
            'sess_cookie_name' => 'SESSION_COOKIE_NAME',
            'sess_expiration' => 'SESSION_EXPIRATION',
            'sess_save_path' => 'SESSION_SAVE_PATH',
            'sess_match_ip' => 'SESSION_MATCH_IP',
            'sess_time_to_update' => 'SESSION_TIME_TO_UPDATE',
            'sess_regenerate_destroy' => 'SESSION_REGENERATE_DESTROY',
            'cookie_prefix' => 'COOKIE_PREFIX',
            'cookie_domain' => 'COOKIE_DOMAIN',
            'cookie_path' => 'COOKIE_PATH',
            'cookie_secure' => 'COOKIE_SECURE',
            'cookie_httponly' => 'COOKIE_HTTPONLY',
            'standardize_newlines' => 'STANDARDIZE_NEWLINES',
            'global_xss_filtering' => 'GLOBAL_XSS_FILTERING',
            'csrf_protection' => 'CSRF_PROTECTION',
            'csrf_token_name' => 'CSRF_TOKEN_NAME',
            'csrf_cookie_name' => 'CSRF_COOKIE_NAME',
            'csrf_expire' => 'CSRF_EXPIRE',
            'csrf_regenerate' => 'CSRF_REGENERATE',
            'csrf_exclude_uris' => 'CSRF_EXCLUDE_URIS',
            'compress_output' => 'COMPRESS_OUTPUT',
            'time_reference' => 'TIME_REFERENCE',
            'rewrite_short_tags' => 'REWRITE_SHORT_TAGS',
            'proxy_ips' => 'PROXY_IPS'
        );

        foreach ($constants_map as $config_key => $constant_name) {
            if (isset($config[$config_key]) && !defined($constant_name)) {
                define($constant_name, $config[$config_key]);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Initialize database configuration for PDO
     *
     * @return void
     */
    protected function init_database_config()
    {
        $db_config_file = $this->find_config_file('database');
        
        if ($db_config_file && file_exists($db_config_file)) {
            include($db_config_file);
            
            if (isset($db) && is_array($db)) {
                $this->db_config = $db;
                
                // Define database constants
                if (isset($db['default'])) {
                    $default_db = $db['default'];
                    
                    !defined('DB_HOSTNAME') && define('DB_HOSTNAME', $default_db['hostname'] ?? 'localhost');
                    !defined('DB_USERNAME') && define('DB_USERNAME', $default_db['username'] ?? '');
                    !defined('DB_PASSWORD') && define('DB_PASSWORD', $default_db['password'] ?? '');
                    !defined('DB_DATABASE') && define('DB_DATABASE', $default_db['database'] ?? '');
                    !defined('DB_DRIVER') && define('DB_DRIVER', $default_db['dbdriver'] ?? 'mysql');
                    !defined('DB_PREFIX') && define('DB_PREFIX', $default_db['dbprefix'] ?? '');
                    !defined('DB_CHARSET') && define('DB_CHARSET', $default_db['char_set'] ?? 'utf8mb4');
                    !defined('DB_COLLATION') && define('DB_COLLATION', $default_db['dbcollat'] ?? 'utf8mb4_unicode_ci');
                    !defined('DB_PORT') && define('DB_PORT', $default_db['port'] ?? 3306);
                }
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Get PDO database connection
     *
     * @param string $group Database group name (default: 'default')
     * @return PDO|false PDO connection or false on failure
     */
    public function get_pdo_connection($group = 'default')
    {
        if ($this->pdo_connection !== null) {
            return $this->pdo_connection;
        }

        if (!isset($this->db_config[$group])) {
            $this->log_message('error', "Database group '{$group}' not found in configuration");
            return false;
        }

        $db = $this->db_config[$group];
        
        try {
            $dsn = $this->build_dsn($db);
            $options = $this->get_pdo_options($db);
            
            $this->pdo_connection = new PDO($dsn, $db['username'], $db['password'], $options);
            
            // Set error mode
            $this->pdo_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->log_message('info', 'PDO Database connection established');
            
            return $this->pdo_connection;
            
        } catch (PDOException $e) {
            $this->log_message('error', 'PDO Connection failed: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Build DSN string for PDO connection
     *
     * @param array $db Database configuration
     * @return string DSN string
     */
    protected function build_dsn($db)
    {
        $driver = $db['dbdriver'] ?? 'mysql';
        $hostname = $db['hostname'] ?? 'localhost';
        $database = $db['database'] ?? '';
        $port = $db['port'] ?? 3306;
        $charset = $db['char_set'] ?? 'utf8mb4';

        switch ($driver) {
            case 'mysql':
            case 'mysqli':
                return "mysql:host={$hostname};port={$port};dbname={$database};charset={$charset}";
            
            case 'postgres':
            case 'postgre':
                return "pgsql:host={$hostname};port={$port};dbname={$database}";
            
            case 'sqlite':
            case 'sqlite3':
                return "sqlite:{$database}";
            
            case 'sqlsrv':
                return "sqlsrv:Server={$hostname},{$port};Database={$database}";
            
            default:
                return "mysql:host={$hostname};port={$port};dbname={$database};charset={$charset}";
        }
    }

    // --------------------------------------------------------------------

    /**
     * Get PDO options array
     *
     * @param array $db Database configuration
     * @return array PDO options
     */
    protected function get_pdo_options($db)
    {
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        );

        // Add charset option for MySQL
        if (in_array($db['dbdriver'] ?? 'mysql', ['mysql', 'mysqli'])) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . ($db['char_set'] ?? 'utf8mb4');
        }

        // Add SSL options if configured
        if (isset($db['encrypt']) && $db['encrypt'] === TRUE) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return $options;
    }

    // --------------------------------------------------------------------

    /**
     * Load Config File
     *
     * @param string $file Configuration file name
     * @param bool $use_sections Whether configuration values should be loaded into their own section
     * @param bool $fail_gracefully Whether to just return FALSE or display an error message
     * @return bool TRUE if the file was loaded correctly or FALSE on failure
     */
    public function load($file = '', $use_sections = FALSE, $fail_gracefully = FALSE)
    {
        $file = ($file === '') ? 'config' : str_replace('.php', '', $file);
        $loaded = FALSE;

        foreach ($this->_config_paths as $path) {
            foreach (array($file, (defined('ENVIRONMENT') ? ENVIRONMENT : 'development') . DIRECTORY_SEPARATOR . $file) as $location) {
                $file_path = $path . 'config/' . $location . '.php';
                
                if (in_array($file_path, $this->is_loaded, TRUE)) {
                    return TRUE;
                }

                if (!file_exists($file_path)) {
                    continue;
                }

                include($file_path);

                if (!isset($config) OR !is_array($config)) {
                    if ($fail_gracefully === TRUE) {
                        return FALSE;
                    }
                    $this->show_error('Your ' . $file_path . ' file does not appear to contain a valid configuration array.');
                }

                if ($use_sections === TRUE) {
                    $this->config[$file] = isset($this->config[$file])
                        ? array_merge($this->config[$file], $config)
                        : $config;
                } else {
                    $this->config = array_merge($this->config, $config);
                }

                // Define constants for loaded config
                $this->define_config_constants($config);

                $this->is_loaded[] = $file_path;
                $config = NULL;
                $loaded = TRUE;
                $this->log_message('debug', 'Config file loaded: ' . $file_path);
            }
        }

        if ($loaded === TRUE) {
            return TRUE;
        } elseif ($fail_gracefully === TRUE) {
            return FALSE;
        }

        $this->show_error('The configuration file ' . $file . '.php does not exist.');
    }

    // --------------------------------------------------------------------

    /**
     * Find config file in available paths
     *
     * @param string $file Configuration file name
     * @return string|false File path or false if not found
     */
    protected function find_config_file($file)
    {
        $file = str_replace('.php', '', $file);
        
        foreach ($this->_config_paths as $path) {
            $file_path = $path . 'config/' . $file . '.php';
            if (file_exists($file_path)) {
                return $file_path;
            }
            
            // Check environment-specific config
            if (defined('ENVIRONMENT')) {
                $env_file_path = $path . 'config/' . ENVIRONMENT . '/' . $file . '.php';
                if (file_exists($env_file_path)) {
                    return $env_file_path;
                }
            }
        }
        
        return false;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a config file item
     *
     * @param string $item Config item name
     * @param string $index Index name
     * @return mixed The configuration item or NULL if the item doesn't exist
     */
    public function item($item, $index = '')
    {
        if ($index == '') {
            return isset($this->config[$item]) ? $this->config[$item] : NULL;
        }

        return isset($this->config[$index], $this->config[$index][$item]) ? $this->config[$index][$item] : NULL;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a config file item with slash appended (if not empty)
     *
     * @param string $item Config item name
     * @return string|null The configuration item or NULL if the item doesn't exist
     */
    public function slash_item($item)
    {
        if (!isset($this->config[$item])) {
            return NULL;
        } elseif (trim($this->config[$item]) === '') {
            return '';
        }

        return rtrim($this->config[$item], '/') . '/';
    }

    // --------------------------------------------------------------------

    /**
     * Site URL
     *
     * Returns base_url . index_page [. uri_string]
     *
     * @param string|string[] $uri URI string or an array of segments
     * @param string $protocol Protocol
     * @return string
     */
    public function site_url($uri = '', $protocol = NULL)
    {
        $base_url = $this->slash_item('base_url');

        if (isset($protocol)) {
            // For protocol-relative links
            if ($protocol === '') {
                $base_url = substr($base_url, strpos($base_url, '//'));
            } else {
                $base_url = $protocol . substr($base_url, strpos($base_url, '://'));
            }
        }

        if (empty($uri)) {
            return $base_url . $this->item('index_page');
        }

        $uri = $this->_uri_string($uri);

        if ($this->item('enable_query_strings') === FALSE) {
            $suffix = isset($this->config['url_suffix']) ? $this->config['url_suffix'] : '';

            if ($suffix !== '') {
                if (($offset = strpos($uri, '?')) !== FALSE) {
                    $uri = substr($uri, 0, $offset) . $suffix . substr($uri, $offset);
                } else {
                    $uri .= $suffix;
                }
            }

            return $base_url . $this->slash_item('index_page') . $uri;
        } elseif (strpos($uri, '?') === FALSE) {
            $uri = '?' . $uri;
        }

        return $base_url . $this->item('index_page') . $uri;
    }

    // --------------------------------------------------------------------

    /**
     * Base URL
     *
     * Returns base_url [. uri_string]
     *
     * @param string|string[] $uri URI string or an array of segments
     * @param string $protocol Protocol
     * @return string
     */
    public function base_url($uri = '', $protocol = NULL)
    {
        $base_url = $this->slash_item('base_url');

        if (isset($protocol)) {
            // For protocol-relative links
            if ($protocol === '') {
                $base_url = substr($base_url, strpos($base_url, '//'));
            } else {
                $base_url = $protocol . substr($base_url, strpos($base_url, '://'));
            }
        }

        return $base_url . $this->_uri_string($uri);
    }

    // --------------------------------------------------------------------

    /**
     * Build URI string
     *
     * @param string|string[] $uri URI string or an array of segments
     * @return string
     */
    protected function _uri_string($uri)
    {
        if ($this->item('enable_query_strings') === FALSE) {
            is_array($uri) && $uri = implode('/', $uri);
            return ltrim($uri, '/');
        } elseif (is_array($uri)) {
            return http_build_query($uri);
        }

        return $uri;
    }

    // --------------------------------------------------------------------

    /**
     * Set a config file item
     *
     * @param string $item Config item key
     * @param mixed $value Config item value
     * @return void
     */
    public function set_item($item, $value)
    {
        $this->config[$item] = $value;
    }

    // --------------------------------------------------------------------

    /**
     * Auto-set base URL
     *
     * @return void
     */
    protected function auto_set_base_url()
    {
        if (empty($this->config['base_url'])) {
            if (isset($_SERVER['SERVER_ADDR'])) {
                if (strpos($_SERVER['SERVER_ADDR'], ':') !== FALSE) {
                    $server_addr = '[' . $_SERVER['SERVER_ADDR'] . ']';
                } else {
                    $server_addr = $_SERVER['SERVER_ADDR'];
                }

                $base_url = ($this->is_https() ? 'https' : 'http') . '://' . $server_addr
                    . substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], basename($_SERVER['SCRIPT_FILENAME'])));
            } else {
                $base_url = 'http://localhost/';
            }

            $this->set_item('base_url', $base_url);
            
            // Define BASE_URL constant
            if (!defined('BASE_URL')) {
                define('BASE_URL', $base_url);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     * Check if HTTPS is being used
     *
     * @return bool
     */
    protected function is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return TRUE;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return TRUE;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return TRUE;
        }

        return FALSE;
    }

    // --------------------------------------------------------------------

    /**
     * Log Message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @return void
     */
    protected function log_message($level, $message)
    {
        if (function_exists('log_message')) {
            log_message($level, $message);
        } else {
            // Fallback logging
            error_log("[{$level}] {$message}");
        }
    }

    // --------------------------------------------------------------------

    /**
     * Show Error
     *
     * @param string $message Error message
     * @return void
     */
    protected function show_error($message)
    {
        if (function_exists('show_error')) {
            show_error($message);
        } else {
            // Fallback error handling
            throw new Exception($message);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Get all configuration items
     *
     * @return array All configuration items
     */
    public function get_all_config()
    {
        return $this->config;
    }

    // --------------------------------------------------------------------

    /**
     * Check if a configuration item exists
     *
     * @param string $item Config item name
     * @param string $index Index name
     * @return bool
     */
    public function has_item($item, $index = '')
    {
        if ($index == '') {
            return isset($this->config[$item]);
        }

        return isset($this->config[$index], $this->config[$index][$item]);
    }

    // --------------------------------------------------------------------

    /**
     * Close PDO connection
     *
     * @return void
     */
    public function close_connection()
    {
        $this->pdo_connection = null;
    }

    // --------------------------------------------------------------------

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close_connection();
    }
}

/**
 * Helper function to get global configuration
 * 
 * @return array
 */
function &get_config()
{
    static $_config;

    if (empty($_config)) {
        $_config = array();
        
        // Load main config file if it exists
        if (defined('APP_PATH') && file_exists(APP_PATH . 'config/config.php')) {
            include(APP_PATH . 'config/config.php');
            
            if (isset($config)) {
                $_config = $config;
            }
        }
    }

    return $_config;
}
?>