<?php


class PetData
{

    protected ?int $id = null;
    protected string $name;
    protected string $species;
    protected ?string $breed = null;
    protected ?string $color = null;
    protected ?string $photo_url = null;
    protected string $status;
    protected ?string $description = null;
    protected string $date_reported;
    protected ?int $user_id = null;

    // Accessor Methods
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