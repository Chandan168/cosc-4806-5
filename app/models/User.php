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

    public function createAdminUser($username, $password) {
        $db = db_connect();
        if ($db === null) {
            return false;
        }

        // Check if admin user already exists
        $checkStatement = $db->prepare("SELECT id FROM users WHERE username = :username");
        $checkStatement->bindValue(':username', strtolower($username));
        $checkStatement->execute();

        if ($checkStatement->fetch()) {
            return false; // User already exists
        }

        // Create admin user with hashed password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $statement = $db->prepare("INSERT INTO users (username, password, is_admin) VALUES (:username, :password, 1)");
        $statement->bindValue(':username', strtolower($username));
        $statement->bindValue(':password', $hashedPassword);
        return $statement->execute();
    }
}
