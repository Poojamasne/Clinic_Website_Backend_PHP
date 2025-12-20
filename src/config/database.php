<?php

class Database {
    private static $instance = null;
    private $connection;
    private $transactionLevel = 0;
    
    private function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'clinic_db';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: 'root@123';
        
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        
        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ]);
            
            // Set timezone and session variables
            $this->connection->exec("SET time_zone = '+05:30'");
            $this->connection->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Test connection
            $this->connection->query("SELECT 1");
            
        } catch (PDOException $e) {
            $errorMsg = "Database connection failed: " . $e->getMessage();
            
            if (strpos($e->getMessage(), 'could not find driver') !== false) {
                $errorMsg .= "\n\n=== PDO MYSQL EXTENSION NOT LOADED ===\n";
                $errorMsg .= "The 'pdo_mysql' extension is not enabled in PHP.\n";
                $errorMsg .= "\nTo fix this:\n";
                $errorMsg .= "1. Edit: C:\\php8\\php.ini\n";
                $errorMsg .= "2. Find line: extension=pdo_mysql\n";
                $errorMsg .= "3. Make sure it's NOT commented (no semicolon)\n";
                $errorMsg .= "4. Restart PHP server\n";
                $errorMsg .= "\nCheck if file exists: C:\\php8\\ext\\php_pdo_mysql.dll\n";
            } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
                $errorMsg .= "\n\n=== DATABASE DOESN'T EXIST ===\n";
                $errorMsg .= "Database '$dbname' doesn't exist.\n";
                $errorMsg .= "Create it with: CREATE DATABASE $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
            } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
                $errorMsg .= "\n\n=== ACCESS DENIED ===\n";
                $errorMsg .= "Check your database credentials in .env file.\n";
            }
            
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            try {
                self::$instance = new self();
                error_log("Database connection established successfully");
            } catch (Exception $e) {
                error_log("Database instance creation failed: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check your configuration.", 0, $e);
            }
        }
        return self::$instance;
    }
    
    public function getConnection() {
        if (!$this->connection) {
            $this->reconnect();
        }
        return $this->connection;
    }
    
    private function reconnect() {
        try {
            $this->connect();
            error_log("Database reconnected successfully");
        } catch (Exception $e) {
            error_log("Database reconnection failed: " . $e->getMessage());
            throw new Exception("Database connection lost and reconnection failed");
        }
    }
    
    public function beginTransaction() {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionLevel++;
        return $this->transactionLevel;
    }
    
    public function commit() {
        if ($this->transactionLevel === 0) {
            throw new Exception("No transaction to commit");
        }
        
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->connection->commit();
        }
        
        return false;
    }
    
    public function rollback() {
        if ($this->transactionLevel === 0) {
            return false;
        }
        
        $this->transactionLevel = 0;
        return $this->connection->rollBack();
    }
    
    public function inTransaction() {
        return $this->transactionLevel > 0;
    }
    
    public function lastInsertId($name = null) {
        return $this->connection->lastInsertId($name);
    }
    
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!$stmt) {
                throw new PDOException("Failed to prepare statement: " . implode(", ", $this->connection->errorInfo()));
            }
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $paramType = $this->getParamType($value);
                $paramName = is_int($key) ? $key + 1 : $key;
                $stmt->bindValue($paramName, $value, $paramType);
            }
            
            $stmt->execute();
            return $stmt;
            
        } catch (PDOException $e) {
            $errorInfo = $this->connection->errorInfo();
            error_log("Query failed: " . $e->getMessage() . 
                     " [SQL: $sql]" . 
                     " [Params: " . json_encode($params) . "]" .
                     " [PDO Error: " . json_encode($errorInfo) . "]");
            
            // Check for deadlock or lock wait timeout
            if ($e->getCode() == 40001 || $e->getCode() == 1213) {
                // Deadlock detected
                error_log("Deadlock detected, retrying may be needed");
            } elseif ($e->getCode() == 1205) {
                // Lock wait timeout
                error_log("Lock wait timeout exceeded");
            }
            
            throw $e;
        }
    }
    
    private function getParamType($value) {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchColumn($sql, $params = [], $column = 0) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchColumn($column);
    }
    
    public function executeUpdate($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    public function executeInsert($sql, $params = []) {
        $stmt = $this->executeQuery($sql, $params);
        return $this->lastInsertId();
    }
    
    public function getTableInfo($tableName) {
        try {
            $sql = "SHOW COLUMNS FROM $tableName";
            return $this->fetchAll($sql);
        } catch (Exception $e) {
            error_log("Failed to get table info for $tableName: " . $e->getMessage());
            return [];
        }
    }
    
    public function tableExists($tableName) {
        try {
            $sql = "SELECT 1 FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ? 
                    LIMIT 1";
            $result = $this->fetchOne($sql, [$tableName]);
            return !empty($result);
        } catch (Exception $e) {
            error_log("Failed to check if table exists: " . $e->getMessage());
            return false;
        }
    }
    
    public function createTableIfNotExists($tableName, $createSql) {
        if (!$this->tableExists($tableName)) {
            try {
                $this->executeQuery($createSql);
                error_log("Table $tableName created successfully");
                return true;
            } catch (Exception $e) {
                error_log("Failed to create table $tableName: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
    
    public function ping() {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function quote($string) {
        return $this->connection->quote($string);
    }
    
    public function escapeLike($string) {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $string);
    }
    
    public function getErrorInfo() {
        return $this->connection->errorInfo();
    }
    
    public function __destruct() {
        // Rollback any uncommitted transactions
        if ($this->inTransaction()) {
            try {
                $this->rollback();
                error_log("Uncommitted transaction rolled back during destruct");
            } catch (Exception $e) {
                error_log("Failed to rollback transaction during destruct: " . $e->getMessage());
            }
        }
        
        // Close connection
        $this->connection = null;
    }
    
    public static function createTables() {
        $db = self::getInstance();
        
        // Create appointments table
        $appointmentsTable = "
        CREATE TABLE IF NOT EXISTS appointments (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            service VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            time VARCHAR(50) NOT NULL,
            notes TEXT,
            amount DECIMAL(10,2),
            consultation_fee DECIMAL(10,2) DEFAULT 500.00,
            status VARCHAR(50) DEFAULT 'pending',
            is_paid BOOLEAN DEFAULT FALSE,
            payment_id VARCHAR(255),
            createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_date_time (date, time),
            INDEX idx_status (status),
            INDEX idx_email (email),
            INDEX idx_created (createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Create contacts table
        $contactsTable = "
        CREATE TABLE IF NOT EXISTS contacts (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'unread',
            responded BOOLEAN DEFAULT FALSE,
            createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_email (email),
            INDEX idx_created (createdAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Create admins table
        $adminsTable = "
        CREATE TABLE IF NOT EXISTS admins (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'admin',
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            // Create tables
            $db->executeQuery($appointmentsTable);
            $db->executeQuery($contactsTable);
            $db->executeQuery($adminsTable);
            
            // Insert default admin if not exists
            $adminCheck = $db->fetchOne("SELECT id FROM admins WHERE email = ?", ['admin@clinic.com']);
            if (!$adminCheck) {
                $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
                $db->executeInsert(
                    "INSERT INTO admins (id, name, email, password, role) VALUES (?, ?, ?, ?, ?)",
                    [$db->generateUuid(), 'Admin User', 'admin@clinic.com', $hashedPassword, 'admin']
                );
                error_log("Default admin user created");
            }
            
            error_log("All tables created/verified successfully");
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to create tables: " . $e->getMessage());
            return false;
        }
    }
    
    // Helper method to generate UUID
    public function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Global helper function
function db() {
    return Database::getInstance()->getConnection();
}

// Helper function for quick database operations
function db_query($sql, $params = []) {
    $db = Database::getInstance();
    return $db->executeQuery($sql, $params);
}

function db_fetch_all($sql, $params = []) {
    $db = Database::getInstance();
    return $db->fetchAll($sql, $params);
}

function db_fetch_one($sql, $params = []) {
    $db = Database::getInstance();
    return $db->fetchOne($sql, $params);
}

function db_insert($table, $data) {
    $db = Database::getInstance();
    
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');
    $values = array_values($data);
    
    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = $db->executeQuery($sql, $values);
    return $db->lastInsertId();
}

function db_update($table, $data, $where, $whereParams = []) {
    $db = Database::getInstance();
    
    $setParts = [];
    $values = [];
    
    foreach ($data as $column => $value) {
        $setParts[] = "$column = ?";
        $values[] = $value;
    }
    
    $values = array_merge($values, $whereParams);
    
    $sql = "UPDATE $table SET " . implode(', ', $setParts) . " WHERE $where";
    
    $stmt = $db->executeQuery($sql, $values);
    return $stmt->rowCount();
}

function db_delete($table, $where, $params = []) {
    $db = Database::getInstance();
    
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $db->executeQuery($sql, $params);
    return $stmt->rowCount();
}