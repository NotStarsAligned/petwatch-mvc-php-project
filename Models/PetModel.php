<?php
// Model: Models/PetModel.php (HARDCODED VERSION - MODERN PHP)
require_once('PetData.php');

class PetModel
{
    private array $petsData = [
        ['id' => 101, 'name' => 'Whiskers', 'type' => 'Cat', 'status' => 'Missing', 'location' => 'Near Central Park', 'contact_id' => 1],
        ['id' => 102, 'name' => 'Buddy', 'type' => 'Dog', 'status' => 'Sighted', 'location' => 'By Salford Bus Station', 'contact_id' => 2],
    ];

    private static array $temporaryStorage = [];

    public function __construct()
    {
        $this->petsData = array_merge($this->petsData, self::$temporaryStorage);
    }

    public function getAllPets(): array
    {
        $dataSet = [];
        // Convert raw array data into PetData objects (ORM)
        foreach ($this->petsData as $row) {
            $pet = new PetData();
            foreach ($row as $key => $value) {
                $pet->{$key} = $value;
            }
            $dataSet[] = $pet;
        }
        return $dataSet;
    }

    public function addPet(array $data): bool
    {
        $newId = count($this->petsData) + count(self::$temporaryStorage) + 10;

        $newReport = [
            'id' => $newId,
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'],
            'location' => $data['location'],
            'contact_id' => $data['user_id']
        ];

        // Store in a static array (in-memory persistence for the session)
        self::$temporaryStorage[] = $newReport;

        return true;
    }
}