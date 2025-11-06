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
     * Get all pets with optional filters and pagination
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
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
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
     * Fetch a single pet by ID
     *
     * TODO: Could potentially see some use later (i doubt anyone is gonna search a pet by their ID but who knows)
     */
    public function getPetById(int $id): ?PetData
    {
        $db = $this->getDbConnection();
        try {
            $stmt = $db->prepare("SELECT * FROM pets WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $stmt->setFetchMode(PDO::FETCH_CLASS, 'PetData');
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            error_log("Database Error (getPetById): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build WHERE clause for filters which comes in handy because, I like searching for things I think
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
            $where[] = "status = :status";
            $params['status'] = strtolower($filters['status']); // ensures 'Lost'/'Found' → 'lost'/'found'
        }


        return [implode(' AND ', $where), $params];
    }


    /**
     * Now, you won't believe me when I say this but, it lets you add pets...
     * It takes in data that'll be handled by the form and performs an insert into SQL statement!
     *
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
            error_log("Database Error (addOwnedPet): " . $e->getMessage());
            return false;
        }
    }

    /**
     * This uh, you guessed it, deletes the pet! (it runs a simple DELETE FROM sql statement!!)
     *
     * Should also mention that it takes the ID to actually delete the correct pet...
     *
     */
    public function deletePet(int $id): bool
    {
        $db = $this->getDbConnection();
        $sql = "DELETE FROM pets WHERE id = :id";

        try{
            $stmt = $db->prepare($sql);
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Database Error (deleteOwnedPet): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates a pet record in the database
     */

    public function updatePet(int $id, array $data): bool {
        $db = $this->getDbConnection();
        $sql = "UPDATE pets
        SET name = :name,
            species = :species,
            breed = :breed,
            color = :color,
            photo_url = :photo_url,
            status = :status,
            description = :description
        WHERE id = :id";

    try {
        $stmt = $db->prepare($sql);

        $data['id'] = $id;

        return $stmt->execute($data);
    } catch (PDOException $e) {
        error_log("Database Error (updateOwnedPet): " . $e->getMessage());
        return false;
    }

}



}
