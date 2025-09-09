<?php
/**
 * PDO Framework Exceptions Class
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
 * Exceptions Class
 *
 * Handles errors, exceptions, and 404 pages with optional database logging
 *
 * @package     PDO_Framework
 * @subpackage  Libraries
 * @category    Exceptions
 */
class PDO_Exceptions {

    /**
     * Nesting level of the output buffering mechanism
     *
     * @var int
     */
    public $ob_level;

    /**
     * PDO database connection for logging
     *
     * @var PDO
     */
    private $db;

    /**
     * List of available error levels
     *
     * @var array
     */
    public $levels = [
        E_ERROR             => 'Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parsing Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated'
    ];

    /**
     * Class constructor
     *
     * @param PDO $pdo Optional PDO connection for database logging
     */
    public function __construct(PDO $pdo = null)
    {
        $this->ob_level = ob_get_level();
        $this->db = $pdo;
        
        // Create error logs table if database is available
        if ($this->db instanceof PDO) {
            $this->createErrorLogsTable();
        }
    }

    /**
     * Create error logs table if it doesn't exist
     *
     * @return bool
     */
    private function createErrorLogsTable()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS error_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        severity VARCHAR(50) NOT NULL,
                        message TEXT NOT NULL,
                        filepath VARCHAR(500),
                        line_number INT,
                        user_agent TEXT,
                        ip_address VARCHAR(45),
                        request_uri VARCHAR(500),
                        http_method VARCHAR(10),
                        session_id VARCHAR(128),
                        user_id INT DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_severity (severity),
                        INDEX idx_created_at (created_at),
                        INDEX idx_filepath (filepath(255))
                    )";
            
            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            // Silently fail if table creation fails
            error_log("Failed to create error_logs table: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exception Logger
     *
     * Logs PHP generated error messages to file and optionally database
     *
     * @param int $severity Log level
     * @param string $message Error message
     * @param string $filepath File path
     * @param int $line Line number
     * @return void
     */
    public function log_exception($severity, $message, $filepath, $line)
    {
        $severity_name = $this->levels[$severity] ?? $severity;
        $log_message = "Severity: {$severity_name} --> {$message} {$filepath} {$line}";
        
        // Log to file
        log_message('error', $log_message);
        
        // Log to database if available
        if ($this->db instanceof PDO) {
            $this->logToDatabase($severity_name, $message, $filepath, $line);
        }
    }

    /**
     * Log error to database
     *
     * @param string $severity
     * @param string $message
     * @param string $filepath
     * @param int $line
     * @return void
     */
    private function logToDatabase($severity, $message, $filepath, $line)
    {
        try {
            $sql = "INSERT INTO error_logs 
                    (severity, message, filepath, line_number, user_agent, ip_address, 
                     request_uri, http_method, session_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $severity,
                $message,
                $filepath,
                $line,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $this->getClientIpAddress(),
                $_SERVER['REQUEST_URI'] ?? null,
                $_SERVER['REQUEST_METHOD'] ?? null,
                session_id() ?: null,
                $this->getCurrentUserId()
            ]);
        } catch (PDOException $e) {
            // Log database error to file as fallback
            error_log("Failed to log error to database: " . $e->getMessage());
        }
    }

    /**
     * Get client IP address
     *
     * @return string|null
     */
    private function getClientIpAddress()
    {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Get current user ID (override this method in your application)
     *
     * @return int|null
     */
    protected function getCurrentUserId()
    {
        // Override this method to return the current user ID
        // Example: return $_SESSION['user_id'] ?? null;
        return null;
    }

    /**
     * 404 Error Handler
     *
     * @param string $page Page URI
     * @param bool $log_error Whether to log the error
     * @return void
     */
    public function show_404($page = '', $log_error = TRUE)
    {
        if (is_cli()) {
            $heading = 'Not Found';
            $message = 'The controller/method pair you requested was not found.';
        } else {
            $heading = '404 Page Not Found';
            $message = 'The page you requested was not found.';
        }

        // Log the 404 error
        if ($log_error) {
            $log_msg = $heading . ': ' . $page;
            log_message('error', $log_msg);
            
            // Log to database
            if ($this->db instanceof PDO) {
                $this->logToDatabase('404 Error', $log_msg, $page, 0);
            }
        }

        echo $this->show_error($heading, $message, 'error_404', 404);
        exit(4);
    }

    /**
     * General Error Page
     *
     * @param string $heading Page heading
     * @param string|array $message Error message
     * @param string $template Template name
     * @param int $status_code HTTP status code
     * @return string Error page output
     */
    public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
    {
        // Log the error
        $error_message = is_array($message) ? implode(' | ', $message) : $message;
        log_message('error', "Error [{$status_code}]: {$heading} - {$error_message}");
        
        // Log to database
        if ($this->db instanceof PDO) {
            $this->logToDatabase("HTTP {$status_code}", "{$heading}: {$error_message}", '', 0);
        }

        $templates_path = config_item('error_views_path');
        if (empty($templates_path)) {
            $templates_path = (defined('VIEWPATH') ? VIEWPATH : APPPATH . 'views/') . 'errors' . DIRECTORY_SEPARATOR;
        }

        if (is_cli()) {
            $message = "\t" . (is_array($message) ? implode("\n\t", $message) : $message);
            $template = 'cli' . DIRECTORY_SEPARATOR . $template;
        } else {
            http_response_code($status_code);
            $message = '<p>' . (is_array($message) ? implode('</p><p>', $message) : $message) . '</p>';
            $template = 'html' . DIRECTORY_SEPARATOR . $template;
        }

        // Clean output buffer
        if (ob_get_level() > $this->ob_level + 1) {
            ob_end_flush();
        }

        ob_start();
        
        // Include template file if it exists, otherwise show basic error
        $template_file = $templates_path . $template . '.php';
        if (file_exists($template_file)) {
            include($template_file);
        } else {
            $this->showBasicError($heading, $message, $status_code);
        }
        
        $buffer = ob_get_contents();
        ob_end_clean();
        
        return $buffer;
    }

    /**
     * Show basic error when template is not available
     *
     * @param string $heading
     * @param string $message
     * @param int $status_code
     * @return void
     */
    private function showBasicError($heading, $message, $status_code)
    {
        if (is_cli()) {
            echo $heading . "\n" . strip_tags($message) . "\n";
        } else {
            echo "<!DOCTYPE html>\n";
            echo "<html><head><title>{$status_code} {$heading}</title></head><body>\n";
            echo "<h1>{$heading}</h1>\n";
            echo "<div>{$message}</div>\n";
            echo "<hr><small>PDO Framework</small>\n";
            echo "</body></html>";
        }
    }

    /**
     * Show exception
     *
     * @param Exception $exception
     * @return void
     */
    public function show_exception($exception)
    {
        $message = $exception->getMessage();
        if (empty($message)) {
            $message = '(null)';
        }

        // Log the exception
        $log_msg = 'Exception: ' . $message . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine();
        log_message('error', $log_msg);
        
        // Log to database
        if ($this->db instanceof PDO) {
            $this->logToDatabase('Exception', $message, $exception->getFile(), $exception->getLine());
        }

        $templates_path = config_item('error_views_path');
        if (empty($templates_path)) {
            $templates_path = (defined('VIEWPATH') ? VIEWPATH : APPPATH . 'views/') . 'errors' . DIRECTORY_SEPARATOR;
        }

        if (is_cli()) {
            $templates_path .= 'cli' . DIRECTORY_SEPARATOR;
        } else {
            $templates_path .= 'html' . DIRECTORY_SEPARATOR;
            http_response_code(500);
        }

        // Clean output buffer
        if (ob_get_level() > $this->ob_level + 1) {
            ob_end_flush();
        }

        ob_start();
        
        $template_file = $templates_path . 'error_exception.php';
        if (file_exists($template_file)) {
            include($template_file);
        } else {
            $this->showBasicException($exception);
        }
        
        $buffer = ob_get_contents();
        ob_end_clean();
        echo $buffer;
    }

    /**
     * Show basic exception when template is not available
     *
     * @param Exception $exception
     * @return void
     */
    private function showBasicException($exception)
    {
        if (is_cli()) {
            echo "Uncaught Exception: " . $exception->getMessage() . "\n";
            echo "File: " . $exception->getFile() . "\n";
            echo "Line: " . $exception->getLine() . "\n";
            echo "Trace:\n" . $exception->getTraceAsString() . "\n";
        } else {
            echo "<!DOCTYPE html>\n";
            echo "<html><head><title>Uncaught Exception</title></head><body>\n";
            echo "<h1>Uncaught Exception</h1>\n";
            echo "<p><strong>Message:</strong> " . html_escape($exception->getMessage()) . "</p>\n";
            
            if (ENVIRONMENT === 'development') {
                echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>\n";
                echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>\n";
                echo "<pre>" . html_escape($exception->getTraceAsString()) . "</pre>\n";
            }
            
            echo "</body></html>";
        }
    }

    /**
     * Native PHP error handler
     *
     * @param int $severity Error level
     * @param string $message Error message
     * @param string $filepath File path
     * @param int $line Line number
     * @return void
     */
    public function show_php_error($severity, $message, $filepath, $line)
    {
        $severity_name = $this->levels[$severity] ?? $severity;
        
        // Log the PHP error
        log_message('error', "PHP {$severity_name}: {$message} in {$filepath} on line {$line}");
        
        // Log to database
        if ($this->db instanceof PDO) {
            $this->logToDatabase("PHP {$severity_name}", $message, $filepath, $line);
        }

        $templates_path = config_item('error_views_path');
        if (empty($templates_path)) {
            $templates_path = (defined('VIEWPATH') ? VIEWPATH : APPPATH . 'views/') . 'errors' . DIRECTORY_SEPARATOR;
        }

        // For security reasons, don't show full file path in non-CLI requests
        if (!is_cli()) {
            $filepath = str_replace('\\', '/', $filepath);
            if (strpos($filepath, '/') !== FALSE) {
                $x = explode('/', $filepath);
                $filepath = $x[count($x) - 2] . '/' . end($x);
            }
            $template = 'html' . DIRECTORY_SEPARATOR . 'error_php';
        } else {
            $template = 'cli' . DIRECTORY_SEPARATOR . 'error_php';
        }

        // Clean output buffer
        if (ob_get_level() > $this->ob_level + 1) {
            ob_end_flush();
        }

        ob_start();
        
        $template_file = $templates_path . $template . '.php';
        if (file_exists($template_file)) {
            include($template_file);
        } else {
            $this->showBasicPhpError($severity_name, $message, $filepath, $line);
        }
        
        $buffer = ob_get_contents();
        ob_end_clean();
        echo $buffer;
    }

    /**
     * Show basic PHP error when template is not available
     *
     * @param string $severity
     * @param string $message
     * @param string $filepath
     * @param int $line
     * @return void
     */
    private function showBasicPhpError($severity, $message, $filepath, $line)
    {
        if (is_cli()) {
            echo "PHP {$severity}: {$message}\n";
            echo "File: {$filepath}\n";
            echo "Line: {$line}\n";
        } else {
            echo "<!DOCTYPE html>\n";
            echo "<html><head><title>PHP {$severity}</title></head><body>\n";
            echo "<h1>A PHP Error was encountered</h1>\n";
            echo "<p><strong>Severity:</strong> {$severity}</p>\n";
            echo "<p><strong>Message:</strong> " . html_escape($message) . "</p>\n";
            
            if (ENVIRONMENT === 'development') {
                echo "<p><strong>Filename:</strong> {$filepath}</p>\n";
                echo "<p><strong>Line Number:</strong> {$line}</p>\n";
            }
            
            echo "</body></html>";
        }
    }

    /**
     * Get error statistics from database
     *
     * @param int $limit Number of records to return
     * @param string $severity Filter by severity level
     * @return array
     */
    public function getErrorStats($limit = 100, $severity = null)
    {
        if (!$this->db instanceof PDO) {
            return [];
        }

        try {
            $sql = "SELECT severity, COUNT(*) as count, 
                           MAX(created_at) as last_occurrence,
                           MIN(created_at) as first_occurrence
                    FROM error_logs";
            
            $params = [];
            
            if ($severity) {
                $sql .= " WHERE severity = ?";
                $params[] = $severity;
            }
            
            $sql .= " GROUP BY severity ORDER BY count DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_message('error', 'Failed to get error stats: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear old error logs from database
     *
     * @param int $days Number of days to keep
     * @return bool
     */
    public function clearOldLogs($days = 30)
    {
        if (!$this->db instanceof PDO) {
            return false;
        }

        try {
            $sql = "DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$days]);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            log_message('error', 'Failed to clear old logs: ' . $e->getMessage());
            return false;
        }
    }
}