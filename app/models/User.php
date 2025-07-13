<?php

class User {

    public $username;
    public $password;
    public $auth = false;

    public function __construct() {
        // Constructor logic if needed
    }

    public function test() {
        $db = db_connect();
        $statement = $db->prepare("SELECT * FROM users;");
        $statement->execute();
        $rows = $statement->fetch(PDO::FETCH_ASSOC);
        return $rows;
    }

    public function authenticate($username, $password) {
        $username = strtolower($username);
        $db = db_connect();
        $statement = $db->prepare("SELECT * FROM users WHERE username = :name;");
        $statement->bindValue(':name', $username);
        $statement->execute();
        $rows = $statement->fetch(PDO::FETCH_ASSOC);

        if ($rows && password_verify($password, $rows['password'])) {
            $_SESSION['auth'] = 1;
            $_SESSION['username'] = ucwords($username);
            $_SESSION['user_id'] = $rows['id'];  // <-- SET user_id here

            // Log the login
            $this->logLogin($rows['id']);

            unset($_SESSION['failedAuth']);
            header('Location: /home');
            exit;
        } else {
            if (isset($_SESSION['failedAuth'])) {
                $_SESSION['failedAuth']++;
            } else {
                $_SESSION['failedAuth'] = 1;
            }
            header('Location: /login');
            exit;
        }
    }

    public function getUserIdByUsername($username) {
        $db = db_connect();
        if ($db === null) {
            return null;
        }
        $username = strtolower($username);
        $statement = $db->prepare("SELECT id FROM users WHERE username = :username");
        $statement->bindValue(':username', $username);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }

    // Admin report methods
    public function getLoginCounts() {
        $db = db_connect();
        if ($db === null) {
            return [];
        }
        $statement = $db->prepare("
            SELECT u.username, COALESCE(l.login_count, 0) as login_count 
            FROM users u 
            LEFT JOIN (
                SELECT user_id, COUNT(*) as login_count 
                FROM user_logins 
                GROUP BY user_id
            ) l ON u.id = l.user_id 
            ORDER BY login_count DESC
        ");
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalUserCount() {
        $db = db_connect();
        if ($db === null) {
            return 0;
        }
        $statement = $db->prepare("SELECT COUNT(*) as count FROM users");
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    public function logLogin($user_id) {
        $db = db_connect();
        if ($db === null) {
            return false;
        }
        $statement = $db->prepare("INSERT INTO user_logins (user_id, login_time) VALUES (:user_id, NOW())");
        $statement->bindValue(':user_id', $user_id);
        return $statement->execute();
    }

    public function createAdminUser() {
        $db = db_connect();
        if ($db === null) {
            return false;
        }

        // Check if admin already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = 'admin'");
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
            return true; // Admin already exists
        }

        // Create admin user
        $hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
        $statement = $db->prepare("INSERT INTO users (username, password, is_admin) VALUES ('admin', :password, 1)");
        $statement->bindValue(':password', $hashedPassword);
        return $statement->execute();
    }
}