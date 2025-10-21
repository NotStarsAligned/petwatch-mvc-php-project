<?php
// Models/PetModel.php
require_once('Database.php');
require_once('PetData.php');

class PetModel
{
    /**
     * Get PDO connection
     */
    private function getDbConnection(): PDO
    {
        return Database::getInstance()->getdbConnection();
    }

    /**
     * Counts the total number of pets (with optional search filters)
     */
    public function countAllPets(array $filters = []): int
    {
        $db = $this->getDbConnection();
        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT COUNT(id) FROM pets" . ($where ? " WHERE $where" : "");
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database Error (countAllPets): " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieves a list of pets, filtered and paginated
     */
    public function getAllPets(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $db = $this->getDbConnection();
        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "SELECT id, name, species, breed, color, photo_url, status, description, date_reported, user_id
                FROM pets" . ($where ? " WHERE $where" : "") .
            " ORDER BY date_reported DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $db->prepare($sql);

            // Bind regular parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            // Bind pagination params
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_CLASS, 'PetData');
        } catch (PDOException $e) {
            error_log("Database Error (getAllPets): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Inserts a new pet report into the database
     */
    public function addPet(array $data): bool
    {
        $db = $this->getDbConnection();
        $sql = "INSERT INTO pets (name, species, breed, color, photo_url, status, description, date_reported, user_id)
                VALUES (:name, :species, :breed, :color, :photo_url, :status, :description, :date_reported, :user_id)";
        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("Database Error (addPet): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Builds the WHERE clause for search filters
     */
    private function buildWhereClause(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['name'])) {
            $where[] = "name LIKE :name";
            $params['name'] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['species'])) {
            $where[] = "species LIKE :species";
            $params['species'] = '%' . $filters['species'] . '%';
        }

        if (!empty($filters['status']) && $filters['status'] !== 'All') {
            $mapped = match ($filters['status']) {
                'Missing' => 'Lost',
                'Sighted' => 'Found',
                default => $filters['status']
            };
            $where[] = "status = :status";
            $params['status'] = $mapped;
        }

        return [implode(' AND ', $where), $params];
    }
}
