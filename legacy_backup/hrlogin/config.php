<?php
require_once dirname(__DIR__) . '/env_loader.php';
date_default_timezone_set('Asia/Kolkata');
  // Making connection with server
class Config {
  private $DBHOST = 'localhost';
  private $DBUSER = 'u797909128_demoproject';
  private $DBPASS = 'QK&0/aF@5';
  private $DBNAME = 'u797909128_demo';

  protected $conn = null;

  // Method for connection to the database
  public function __construct() {
      $this->DBHOST = getenv('DB_HOST') ?: 'localhost';
      $this->DBUSER = getenv('DB_USER') ?: 'u797909128_demoproject';
      $this->DBPASS = getenv('DB_PASS') ?: 'QK&0/aF@5';
      $this->DBNAME = getenv('DB_NAME') ?: 'u797909128_demo';

      $dsn = 'mysql:host=' . $this->DBHOST . ';dbname=' . $this->DBNAME . ';charset=utf8mb4';
      try {
          $this->conn = new PDO($dsn, $this->DBUSER, $this->DBPASS);
          $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
          
          // Set MySQL timezone to Asia/Kolkata to match PHP timezone
          $this->conn->exec("SET time_zone = '+05:30'");
      } catch (PDOException $e) {
          error_log('Database Connection Error: ' . $e->getMessage());
      }
  }

  // Public method to get the database connection
  public function getConnection() {
      return $this->conn;
  }

  /**
   * mysqli connection for legacy scripts. Tries TCP (127.0.0.1) first because
   * shared hosts often block the Unix socket used by localhost.
   */
  public function getMysqliConnection() {
      $hosts = array_unique(['127.0.0.1', $this->DBHOST, 'localhost']);
      $previousReport = mysqli_report(MYSQLI_REPORT_OFF);

      foreach ($hosts as $host) {
          try {
              $con = mysqli_connect($host, $this->DBUSER, $this->DBPASS, $this->DBNAME);
              if ($con) {
                  mysqli_set_charset($con, 'utf8mb4');
                  mysqli_query($con, "SET time_zone = '+05:30'");
                  mysqli_report($previousReport);
                  return $con;
              }
          } catch (mysqli_sql_exception $e) {
              // try next host
          }
      }

      mysqli_report($previousReport);
      return null;
  }
}