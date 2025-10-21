<?php
// Models/UserModel.php
require_once('Database.php');

class UserModel
{
    /**
     * Get the PDO connection from the Database singleton
     * @return PDO
     */
    private function getDbConnection(): PDO
    {
        return Database::getInstance()->getdbConnection();
    }

    /**
     * Verifies username and password credentials
     * Returns the user ID on success, or null on failure
     */
    public function verifyCredentials(string $username, string $password): ?int
    {
        $db = $this->getDbConnection();

        try {
            $sql = "SELECT id, password_hash FROM users WHERE username = :username";
            $stmt = $db->prepare($sql);
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                $hash = $user->password_hash;
                // Allow both hashed and plaintext passwords (for testing/dev)
                if (password_verify($password, $hash) || $password === $hash) {
                    return (int)$user->id;
                }
            }

            return null;
        } catch (PDOException $e) {
            error_log("Database Error (verifyCredentials): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves a username by their user ID
     */
    public function getUsernameById(int $id): ?string
    {
        $db = $this->getDbConnection();

        try {
            $sql = "SELECT username FROM users WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $username = $stmt->fetchColumn();

            return $username ? (string)$username : null;
        } catch (PDOException $e) {
            error_log("Database Error (getUsernameById): " . $e->getMessage());
            return null;
        }
    }
}
