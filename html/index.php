<?php
require_once 'db.php';

header('Content-Type: application/json');

// Инициализация хранилища в памяти (статическое хранилище в рамках одного запроса)
class MemoryStorage {
    private static $users = [];
    private static $nextId = 1;

    public static function getAllUsers() {
        return array_map(function($user) {
            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];
        }, self::$users);
    }

    public static function getUser($id) {
        foreach (self::$users as $user) {
            if ($user['id'] == $id) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ];
            }
        }
        return null;
    }

    public static function createUser($username, $email, $password) {
        $id = self::$nextId++;
        self::$users[] = [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT)
        ];
        return $id;
    }

    public static function updateUser($id, $data) {
        foreach (self::$users as &$user) {
            if ($user['id'] == $id) {
                if (isset($data['username'])) $user['username'] = $data['username'];
                if (isset($data['email'])) $user['email'] = $data['email'];
                if (isset($data['password'])) $user['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                return true;
            }
        }
        return false;
    }

    public static function deleteUser($id) {
        foreach (self::$users as $key => $user) {
            if ($user['id'] == $id) {
                unset(self::$users[$key]);
                return true;
            }
        }
        return false;
    }
}

// Получаем метод запроса и данные
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$queryParams = $_GET;

// Функция для очистки данных
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Определяем тип БД из запроса
$dbType = strtolower($queryParams['db'] ?? 'mariadb');
if (!in_array($dbType, ['mariadb', 'sqlite', 'memory'])) {
    $dbType = 'mariadb';
}

// Обработка запросов
switch ($method) {
    case 'GET':
        if (isset($queryParams['id'])) {
            // Получить одного пользователя
            $userId = (int)cleanInput($queryParams['id']);
            
            if ($dbType === 'mariadb') {
                $stmt = getMariaDBConnection()->prepare("SELECT id, username, email FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($dbType === 'sqlite') {
                $stmt = getSQLiteConnection()->prepare("SELECT id, username, email FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $user = MemoryStorage::getUser($userId);
            }
            
            if ($user) {
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } else {
            // Получить всех пользователей
            if ($dbType === 'mariadb') {
                $stmt = getMariaDBConnection()->query("SELECT id, username, email FROM users");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($dbType === 'sqlite') {
                $stmt = getSQLiteConnection()->query("SELECT id, username, email FROM users");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $users = MemoryStorage::getAllUsers();
            }
            
            echo json_encode($users);
        }
        break;
        
    case 'POST':
        $username = cleanInput($input['username'] ?? '');
        $email = cleanInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required']);
            break;
        }
        
        try {
            if ($dbType === 'mariadb') {
                $stmt = getMariaDBConnection()->prepare(
                    "INSERT INTO users (username, email, password) VALUES (?, ?, ?)"
                );
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $passwordHash]);
                $userId = getMariaDBConnection()->lastInsertId();
            } elseif ($dbType === 'sqlite') {
                $stmt = getSQLiteConnection()->prepare(
                    "INSERT INTO users (username, email, password) VALUES (?, ?, ?)"
                );
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$username, $email, $passwordHash]);
                $userId = getSQLiteConnection()->lastInsertId();
            } else {
                $userId = MemoryStorage::createUser($username, $email, $password);
            }
            
            http_response_code(201);
            echo json_encode([
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'message' => 'User created successfully'
            ]);
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'User creation failed: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        $userId = (int)cleanInput($input['id'] ?? 0);
        
        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID']);
            break;
        }
        
        $data = [];
        if (isset($input['username'])) $data['username'] = cleanInput($input['username']);
        if (isset($input['email'])) $data['email'] = cleanInput($input['email']);
        if (isset($input['password'])) $data['password'] = $input['password'];
        
        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            break;
        }
        
        try {
            if ($dbType === 'mariadb') {
                $fields = [];
                $params = [];
                
                if (isset($data['username'])) {
                    $fields[] = "username = ?";
                    $params[] = $data['username'];
                }
                if (isset($data['email'])) {
                    $fields[] = "email = ?";
                    $params[] = $data['email'];
                }
                if (isset($data['password'])) {
                    $fields[] = "password = ?";
                    $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                
                $params[] = $userId;
                $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = getMariaDBConnection()->prepare($query);
                $stmt->execute($params);
                $affected = $stmt->rowCount();
            } elseif ($dbType === 'sqlite') {
                $fields = [];
                $params = [];
                
                if (isset($data['username'])) {
                    $fields[] = "username = ?";
                    $params[] = $data['username'];
                }
                if (isset($data['email'])) {
                    $fields[] = "email = ?";
                    $params[] = $data['email'];
                }
                if (isset($data['password'])) {
                    $fields[] = "password = ?";
                    $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                
                $params[] = $userId;
                $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
                $stmt = getSQLiteConnection()->prepare($query);
                $stmt->execute($params);
                $affected = $stmt->rowCount();
            } else {
                $affected = MemoryStorage::updateUser($userId, $data) ? 1 : 0;
            }
            
            if ($affected > 0) {
                echo json_encode(['message' => 'User updated successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'User update failed: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        $userId = (int)cleanInput($queryParams['id'] ?? 0);
        
        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID']);
            break;
        }
        
        try {
            if ($dbType === 'mariadb') {
                $stmt = getMariaDBConnection()->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $affected = $stmt->rowCount();
            } elseif ($dbType === 'sqlite') {
                $stmt = getSQLiteConnection()->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $affected = $stmt->rowCount();
            } else {
                $affected = MemoryStorage::deleteUser($userId) ? 1 : 0;
            }
            
            if ($affected > 0) {
                echo json_encode(['message' => 'User deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
        } catch (PDOException $e) {
            http_response_code(400);
            echo json_encode(['error' => 'User deletion failed: ' . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>
