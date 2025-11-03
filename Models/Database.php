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

            $databaseFile = __DIR__ . '/../petwatch.sqlite';
            if (!file_exists($databaseFile)) {
                throw new Exception("Database file not found: " . $databaseFile);
            }

            // Initialize PDO connection
            $this->_dbHandle = new PDO("sqlite:" . $databaseFile);
            $this->_dbHandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

    /**
     * Destructor: closes the PDO connection
     */
    public function __destruct() {
        $this->_dbHandle = null;
    }
}
