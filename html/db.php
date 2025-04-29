<?php
// Конфигурация MariaDB
define('MARIADB_HOST', 'db');
define('MARIADB_DB', 'db_first');
define('MARIADB_USER', 'root');
define('MARIADB_PASS', 'kali');

// Подключение к MariaDB с автоматическим созданием таблицы
function getMariaDBConnection() {
    static $mariadb = null;
    
    if ($mariadb === null) {
        try {
            // Устанавливаем соединение
            $mariadb = new PDO(
                "mysql:host=" . MARIADB_HOST . ";dbname=" . MARIADB_DB . ";charset=utf8mb4",
                MARIADB_USER,
                MARIADB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            // Проверяем существование таблицы users
            $tableExists = $mariadb->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
            
            // Создаем таблицу если не существует
            if (!$tableExists) {
                $mariadb->exec("CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                // Опционально: добавляем тестового пользователя
                $stmt = $mariadb->prepare(
                    "INSERT INTO users (username, email, password) VALUES (?, ?, ?)"
                );
                $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt->execute(['admin', 'admin@example.com', $passwordHash]);
            }

        } catch (PDOException $e) {
            die("MariaDB connection failed: " . $e->getMessage());
        }
    }
    
    return $mariadb;
}

// Пример использования
try {
    $db = getMariaDBConnection();
    echo "Successfully connected to MariaDB!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
