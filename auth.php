<?php
session_start();
require_once 'database.php';

class Auth {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function register($username, $password) {
        $username = SQLite3::escapeString(trim($username));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', $passwordHash, SQLITE3_TEXT);

        try {
            $result = $stmt->execute();
            return $this->db->lastInsertRowID();
        } catch (Exception $e) {
            return false;
        }
    }

    public function login($username, $password) {
        $username = SQLite3::escapeString(trim($username));

        $stmt = $this->db->prepare("SELECT id, password FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            return true;
        }
        return false;
    }

    public function logout() {
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }

    public function getUsernameById($userId) {
        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        return $user['username'] ?? 'Unknown';
    }
}
