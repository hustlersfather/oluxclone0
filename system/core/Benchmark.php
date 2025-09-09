<?php
/**
 * PDO Benchmark Class
 *
 * This class enables you to mark points and calculate the time difference
 * between them. Memory consumption can also be displayed.
 * Converted from CodeIgniter to use PDO for database operations.
 *
 * @package     PDO_Benchmark
 * @author      Converted from CodeIgniter
 * @license     MIT License
 */

class PDO_Benchmark {

    /**
     * List of all benchmark markers
     *
     * @var array
     */
    public $marker = array();

    /**
     * PDO database connection
     *
     * @var PDO
     */
    private $db;

    /**
     * Constructor
     *
     * @param PDO $pdo PDO database connection instance
     */
    public function __construct(PDO $pdo = null)
    {
        $this->db = $pdo;
    }

    /**
     * Set a benchmark marker
     *
     * Multiple calls to this function can be made so that several
     * execution points can be timed. Optionally stores marker in database.
     *
     * @param string $name Marker name
     * @param bool $store_in_db Whether to store the marker in database
     * @return void
     */
    public function mark($name, $store_in_db = false)
    {
        $timestamp = microtime(TRUE);
        $this->marker[$name] = $timestamp;
        
        if ($store_in_db && $this->db instanceof PDO) {
            $this->storeBenchmarkInDB($name, $timestamp);
        }
    }

    /**
     * Store benchmark marker in database
     *
     * @param string $name Marker name
     * @param float $timestamp Timestamp
     * @return void
     */
    private function storeBenchmarkInDB($name, $timestamp)
    {
        try {
            $sql = "INSERT INTO benchmarks (marker_name, timestamp, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $timestamp]);
        } catch (PDOException $e) {
            // Log error or handle as needed
            error_log("Benchmark DB Error: " . $e->getMessage());
        }
    }

    /**
     * Elapsed time
     *
     * Calculates the time difference between two marked points.
     *
     * @param string $point1 A particular marked point
     * @param string $point2 A particular marked point
     * @param int $decimals Number of decimal places
     * @return string Calculated elapsed time on success,
     *                an '{elapsed_string}' if $point1 is empty
     *                or an empty string if $point1 is not found.
     */
    public function elapsed_time($point1 = '', $point2 = '', $decimals = 4)
    {
        if ($point1 === '') {
            return '{elapsed_time}';
        }

        if (!isset($this->marker[$point1])) {
            return '';
        }

        if (!isset($this->marker[$point2])) {
            $this->marker[$point2] = microtime(TRUE);
        }

        return number_format($this->marker[$point2] - $this->marker[$point1], $decimals);
    }

    /**
     * Memory Usage
     *
     * Simply returns the {memory_usage} marker.
     *
     * @return string '{memory_usage}'
     */
    public function memory_usage()
    {
        return '{memory_usage}';
    }

    /**
     * Get all benchmarks from database
     *
     * @param int $limit Number of records to retrieve
     * @return array Array of benchmark records
     */
    public function getBenchmarksFromDB($limit = 100)
    {
        if (!$this->db instanceof PDO) {
            return array();
        }

        try {
            $sql = "SELECT * FROM benchmarks ORDER BY created_at DESC LIMIT ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Benchmark DB Error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Clear all benchmark markers
     *
     * @return void
     */
    public function clear()
    {
        $this->marker = array();
    }

    /**
     * Get benchmark statistics from database
     *
     * @param string $marker_name Optional marker name to filter by
     * @return array Statistics array
     */
    public function getStatsFromDB($marker_name = null)
    {
        if (!$this->db instanceof PDO) {
            return array();
        }

        try {
            if ($marker_name) {
                $sql = "SELECT 
                           marker_name,
                           COUNT(*) as count,
                           AVG(timestamp) as avg_time,
                           MIN(timestamp) as min_time,
                           MAX(timestamp) as max_time
                        FROM benchmarks 
                        WHERE marker_name = ?
                        GROUP BY marker_name";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$marker_name]);
            } else {
                $sql = "SELECT 
                           marker_name,
                           COUNT(*) as count,
                           AVG(timestamp) as avg_time,
                           MIN(timestamp) as min_time,
                           MAX(timestamp) as max_time
                        FROM benchmarks 
                        GROUP BY marker_name";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Benchmark DB Error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Create benchmarks table if it doesn't exist
     *
     * @return bool Success status
     */
    public function createBenchmarkTable()
    {
        if (!$this->db instanceof PDO) {
            return false;
        }

        try {
            $sql = "CREATE TABLE IF NOT EXISTS benchmarks (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        marker_name VARCHAR(255) NOT NULL,
                        timestamp DECIMAL(15,6) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_marker_name (marker_name),
                        INDEX idx_created_at (created_at)
                    )";
            
            $this->db->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Benchmark DB Error: " . $e->getMessage());
            return false;
        }
    }
}

// Example usage:
/*
// Database connection
$dsn = 'mysql:host=localhost;dbname=your_database';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Create benchmark instance
    $benchmark = new PDO_Benchmark($pdo);
    
    // Create table (run once)
    $benchmark->createBenchmarkTable();
    
    // Mark benchmarks
    $benchmark->mark('start');
    
    // Your code here...
    sleep(1);
    
    $benchmark->mark('end', true); // Store in database
    
    // Get elapsed time
    echo "Elapsed time: " . $benchmark->elapsed_time('start', 'end') . " seconds\n";
    
    // Get stats from database
    $stats = $benchmark->getStatsFromDB();
    print_r($stats);
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
*/
?>