<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/auth.php';

class AuthController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function register($data) {
        if(empty($data['username']) || empty($data['email']) || 
           empty($data['password']) || empty($data['user_type'])) {
            return ['error' => 'All fields are required'];
        }

        if(!in_array($data['user_type'], ['buyer', 'seller', 'both'])) {
            return ['error' => 'Invalid user type'];
        }

        $password = password_hash($data['password'], PASSWORD_BCRYPT);

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, email, password, user_type)
                VALUES (:username, :email, :password, :user_type)
                RETURNING user_id, username, email, user_type, created_at
            ");

            $stmt->execute([
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password' => $password,
                ':user_type' => $data['user_type']
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $token = generateJWT($user['user_id'], $user['user_type']);

            return [
                'message' => 'User registered successfully',
                'token' => $token,
                'user' => $user
            ];

        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'unique') !== false) {
                return ['error' => 'Username or email already exists'];
            }
            return ['error' => 'Registration failed'];
        }
    }

    public function login($data) {
        if(empty($data['email']) || empty($data['password'])) {
            return ['error' => 'Email and password are required'];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM users WHERE email = :email
            ");
            $stmt->execute([':email' => $data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if(!$user || !password_verify($data['password'], $user['password'])) {
                return ['error' => 'Invalid email or password'];
            }

            $token = generateJWT($user['user_id'], $user['user_type']);

            return [
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'user_type' => $user['user_type']
                ]
            ];

        } catch(PDOException $e) {
            return ['error' => 'Login failed'];
        }
    }
}
?>