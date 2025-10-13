<?php
// Model: Models/UserModel.php (HARDCODED VERSION - MODERN PHP)

class UserModel
{
    private array $users = [
        1 => ['username' => 'Lee', 'password_hash' => 'password', 'name' => 'Lee (Owner)'],
        2 => ['username' => 'Zara', 'password_hash' => 'password', 'name' => 'Zara (Browser)']
    ];

    public function __construct()
    {
        // NO DATABASE CONNECTION
    }

    public function verifyCredentials(string $username, string $password): ?int
    {
        foreach ($this->users as $id => $user) {
            if ($user['username'] === $username && $user['password_hash'] === $password) {
                return $id;
            }
        }
        return null;
    }

    public function getUsernameById(int $id): ?string
    {
        return $this->users[$id]['name'] ?? null;
    }
}