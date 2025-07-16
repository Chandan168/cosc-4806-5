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
        $statement = $db->prepare("SELECT id, username, password, is_admin FROM users WHERE username = :name;");
        $statement->bindValue(':name', $username);
        $statement->execute();
        $rows = $statement->fetch(PDO::FETCH_ASSOC);

        if ($rows && password_verify($password, $rows['password'])) {
            $_SESSION['auth'] = 1;
            $_SESSION['username'] = ucwords($username);
            $_SESSION['user_id'] = $rows['id'];  // <-- SET user_id here
            $_SESSION['is_admin'] = isset($rows['is_admin']) && $rows['is_admin'] == 1; // Set admin session

            // Log the successful login
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

    public function createUser($username, $password) {
        $db = db_connect();
        if ($db === null) {
            return false;
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $statement = $db->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
        $statement->bindValue(':username', strtolower($username));
        $statement->bindValue(':password', $hashedPassword);
        return $statement->execute();
    }

    public function logLogin($userId) {
        $db = db_connect();
        if ($db === null) {
            return false;
        }

        // Changed login_time to attempt_time
        $statement = $db->prepare("INSERT INTO login_logs (user_id, attempt_time) VALUES (:user_id, NOW())");
        $statement->bindValue(':user_id', $userId);
        return $statement->execute();
    }

    public function getLoginCounts() {
        $db = db_connect();
        if ($db === null) {
            return [];
        }
        $statement = $db->prepare("
            SELECT u.username, COUNT(ll.id) as login_count 
            FROM users u 
            LEFT JOIN login_logs ll ON u.id = ll.user_id 
            GROUP BY u.id, u.username 
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
}
