<?php
// Models/UserModel.php (Authentication Logic)
require_once('Database.php');

class UserModel
{
    private function getDbConnection(): PDO
    {
        return Database::getInstance();
    }

    public function verifyCredentials(string $username, string $password): ?int
    {
        $db = $this->getDbConnection();

        try {
            $sql = "SELECT id, password_hash FROM users WHERE username = :username";

            $stmt = $db->prepare($sql);
            $stmt->execute(['username' => $username]);

            $userRow = $stmt->fetch();

            if ($userRow) {
                $hash = $userRow->password_hash;

                // Allow both hashed and plain text matches
                if (password_verify($password, $hash) || $password === $hash) {
                    return (int)$userRow->id;
                }
            }

            return null;
        } catch (PDOException $e) {
            error_log("Database Error in verifyCredentials: " . $e->getMessage());
            return null;
        }
    }

    public function getUsernameById(int $id): ?string
    {
        $db = $this->getDbConnection();
        try {
            $sql = "SELECT username FROM users WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetchColumn();

            return $result ? (string)$result : null;

        } catch (PDOException $e) {
            error_log("Database Error in getUsernameById: " . $e->getMessage());
            return null;
        }
    }
}