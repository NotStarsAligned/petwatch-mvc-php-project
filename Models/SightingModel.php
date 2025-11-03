<?php
// Models/SightingModel.php
require_once('Database.php');

class SightingModel
{
    /**
     * Returns PDO connection
     */
    private function getDbConnection(): PDO
    {
        return Database::getInstance()->getdbConnection();
    }

    /**
     * Builds WHERE clause dynamically from search filters
     */
    private function buildWhereClause(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['name'])) {
            $where[] = "p.name LIKE :name";
            $params['name'] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['species'])) {
            $where[] = "p.species LIKE :species";
            $params['species'] = '%' . $filters['species'] . '%';
        }

        if (!empty($filters['status']) && $filters['status'] !== 'All') {
            $mapped = match ($filters['status']) {
                'Missing' => 'lost',
                'Sighted' => 'found',
                default => $filters['status']
            };
            $where[] = "p.status = :status";
            $params['status'] = $mapped;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereClause, $params];
    }

    /**
     * Returns total count of filtered sightings
     */
    public function countAllSightings(array $filters = []): int
    {
        $db = $this->getDbConnection();
        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "
            SELECT COUNT(s.id)
            FROM sightings s
            JOIN pets p ON s.pet_id = p.id
            JOIN users u ON s.user_id = u.id
            $where
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetches filtered sightings (with pagination)
     */
    public function getAllSightings(array $filters = [], int $limit = 6, int $offset = 0): array
    {
        $db = $this->getDbConnection();
        [$where, $params] = $this->buildWhereClause($filters);

        $sql = "
            SELECT s.id, s.comment, s.latitude, s.longitude, s.timestamp,
                   p.name AS pet_name, p.species, p.status,
                   u.username
            FROM sightings s
            JOIN pets p ON s.pet_id = p.id
            JOIN users u ON s.user_id = u.id
            $where
            ORDER BY s.timestamp DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Adds a new sighting to the database
     */
    public function addSighting(array $data): bool
    {
        $db = $this->getDbConnection();
        $sql = "INSERT INTO sightings (pet_id, user_id, comment, latitude, longitude, timestamp)
                VALUES (:pet_id, :user_id, :comment, :latitude, :longitude, :timestamp)";

        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($data);
        } catch (PDOException $e) {
            error_log("Database Error (addSighting): " . $e->getMessage());
            return false;
        }
    }

}
