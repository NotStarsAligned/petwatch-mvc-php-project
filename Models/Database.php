<?php
// Models/Database.php

class Database {
    /**
     * @var Database null
     */
    protected static $_dbInstance = null;

    /**
     * @var PDOnull
     */
    protected $_dbHandle = null;

    /**
     * Get an instance of the Database (Singleton pattern)
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$_dbInstance === null) {
            self::$_dbInstance = new self();
        }
        return self::$_dbInstance;
    }

    /**
     * Private constructor: creates the PDO connection
     */
    private function __construct() {
        try {
                $host = 'localhost';
                $user = 'sge425';
                $password = 'Q4eEWog3bSsJZTl';
                $dbName = 'idunno';

                $this->_dbHandle = new PDO("mysql:host=$host;dbname=$dbName", $user, $password,[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $this->_dbHandle->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

            } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage();
            exit;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
            exit;
        }
    }

    /**
     * Returns the PDO database connection
     * @return PDO
     */
    public function getdbConnection(): PDO {
        return $this->_dbHandle;
    }
}
