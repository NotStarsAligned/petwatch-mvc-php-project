<?php
// Models/PetData.php - The ORM Entity (Matches DB Schema)

class PetData
{
    // Properties are public for PDO hydration, nullable types noted with '?'
    public ?int $id = null;
    public string $name;
    public string $species;
    public ?string $breed = null;
    public ?string $color = null;
    public ?string $photo_url = null;
    public string $status;
    public ?string $description = null;
    public string $date_reported;
    public ?int $user_id = null;

    // Accessor Methods (Getters) for safe, encapsulated access
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getSpecies(): string { return $this->species; }
    public function getBreed(): ?string { return $this->breed; }
    public function getColor(): ?string { return $this->color; }
    public function getPhotoUrl(): ?string { return $this->photo_url; }
    public function getStatus(): string { return $this->status; }
    public function getDescription(): ?string { return $this->description; }
    public function getDateReported(): string { return $this->date_reported; }
    public function getUserId(): ?int { return $this->user_id; }

    public function getFormattedReportDate(): string
    {
        return date('jS F Y', strtotime($this->date_reported));
    }
}