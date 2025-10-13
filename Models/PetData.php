<?php
// Model: Models/PetData.php - Represents a single pet entity object (ORM).

class PetData
{
    protected $id;
    protected $name;
    protected $type;
    protected $status;
    protected $location;
    protected $contact_id;

    // Accessor methods with return type hints
    public function getID(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getType(): string { return $this->type; }
    public function getStatus(): string { return $this->status; }
    public function getLocation(): string { return $this->location; }
    public function getContactId(): int { return $this->contact_id; }

    // Magic method for populating protected properties from Model data
    public function __set(string $name, $value) { $this->$name = $value; }
}