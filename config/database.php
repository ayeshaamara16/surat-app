<?php
// config/database.php
date_default_timezone_set('Asia/Jakarta');

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $host = 'localhost';
            $dbname = 'surat_app';
            $user = 'root';
            $pass = '';
            
            $this->conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("❌ Koneksi database gagal: " . $e->getMessage() . "<br><br>Jalankan <a href='../install.php'>install.php</a> untuk setup database.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}
?>