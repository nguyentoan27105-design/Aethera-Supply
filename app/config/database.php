<?php
class Database {
    private $host = "localhost";      // hostname MySQL
    private $db_name = "my_store";    // tên database
    private $username = "root";       // username MySQL
    private $password = "";           // password MySQL
    private $conn;

    public function getConnection(){
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]
            );
            // Thiết lập chế độ lỗi
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e){
            echo "Kết nối DB thất bại: " . $e->getMessage();
            exit;
        }
        return $this->conn;
    }
}
?>
