<?php
// Models/Database.php - PDO Connection Singleton for SQLite

class Database
{
    private static ?PDO $dbConnection = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$dbConnection == null) {

            $databaseFile = 'petwatch.sqlite';
            $path = __DIR__ . "/../{$databaseFile}";

            if (!file_exists($path)) {
                die("FATAL ERROR: Database file not found at path: {$path}.");
            }

            try {
                self::$dbConnection = new PDO("sqlite:{$path}");
                self::$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$dbConnection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$dbConnection;
    }
}