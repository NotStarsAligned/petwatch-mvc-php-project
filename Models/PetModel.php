<?php
// Models/PetModel.php
require_once('PetData.php');
require_once('Database.php');

class PetModel
{
    private function getDbConnection(): PDO
    {
        return Database::getInstance();
    }

    public function countAllPets(array $searchParams = []): int
    {
        $db = $this->getDbConnection();
        $where = [];
        $params = [];

        if (!empty($searchParams['name'])) {
            $where[] = "name LIKE :name";
            $params['name'] = '%' . $searchParams['name'] . '%';
        }
        if (!empty($searchParams['species'])) {
            $where[] = "species LIKE :species";
            $params['species'] = '%' . $searchParams['species'] . '%';
        }
        if (!empty($searchParams['status']) && $searchParams['status'] !== 'All') {
            $where[] = "status = :status";
            $params['status'] = $searchParams['status'];
        }

        $sql = "SELECT COUNT(id) FROM pets";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database Error in countAllPets: " . $e->getMessage());
            return 0;
        }
    }

    public function getAllPets(array $searchParams = [], int $limit = 10, int $offset = 0): array
    {
        $db = $this->getDbConnection();
        $where = [];
        $params = [];

        if (!empty($searchParams['name'])) {
            $where[] = "name LIKE :name";
            $params['name'] = '%' . $searchParams['name'] . '%';
        }
        if (!empty($searchParams['species'])) {
            $where[] = "species LIKE :species";
            $params['species'] = '%' . $searchParams['species'] . '%';
        }
        if (!empty($searchParams['status']) && $searchParams['status'] !== 'All') {
            if (strcasecmp($searchParams['status'], 'Missing') === 0) {
                $where[] = "(status = 'Missing' OR status = 'Lost')";
            } else {
                $where[] = "status = :status";
                $params['status'] = $searchParams['status'];
            }
        }


        $sql = "SELECT id, name, species, breed, color, photo_url, status, description, date_reported, user_id 
                FROM pets";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY date_reported DESC LIMIT :limit OFFSET :offset";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        try {
            $stmt = $db->prepare($sql);

            foreach ($params as $key => &$val) {
                if ($key === 'limit' || $key === 'offset') {
                    $stmt->bindParam(":$key", $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(":$key", $val);
                }
            }
            $stmt->execute();

            $pets = $stmt->fetchAll(PDO::FETCH_CLASS, 'PetData');

            return $pets;

        } catch (PDOException $e) {
            error_log("Database Error in getAllPets: " . $e->getMessage());
            return [];
        }
    }

    public function addPet(array $data): bool
    {
        $db = $this->getDbConnection();

        try {
            $sql = "INSERT INTO pets (name, species, breed, color, photo_url, status, description, date_reported, user_id) 
                    VALUES (:name, :species, :breed, :color, :photo_url, :status, :description, :date_reported, :user_id)";

            $stmt = $db->prepare($sql);
            return $stmt->execute($data);

        } catch (PDOException $e) {
            error_log("Database Error in addPet: " . $e->getMessage());
            return false;
        }
    }
}