<?php
  require_once dirname(dirname(__DIR__)) . '/env_loader.php';
  // making connection with server
  class Config {
    private $DBHOST = 'localhost';
    private $DBUSER = 'u797909128_demoproject';
    private $DBPASS = 'QK&0/aF@5';
    private $DBNAME = 'u797909128_demo';

    private $dsn = null;
    protected $conn = null;

    // Method for connection to the database
    public function __construct() {
      $this->DBHOST = getenv('DB_HOST') ?: 'localhost';
      $this->DBUSER = getenv('DB_USER') ?: 'u797909128_demoproject';
      $this->DBPASS = getenv('DB_PASS') ?: 'QK&0/aF@5';
      $this->DBNAME = getenv('DB_NAME') ?: 'u797909128_demo';
      $this->dsn = 'mysql:host=' . $this->DBHOST . ';dbname=' . $this->DBNAME . '';
      try {
        $this->conn = new PDO($this->dsn, $this->DBUSER, $this->DBPASS);
        $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        die('Error: ' . $e->getMessage());
      }
    }
    // Method to get the connection
    public function getConnection() {
      return $this->conn;
    }
  }
?>