<?php
// api/ajax.php


session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once('../Models/Database.php');
require_once('../Models/SightingModel.php');
require_once('../Models/PetModel.php');
require_once('../Models/UserModel.php');

// Read the command from the query string
$cmd = trim($_GET['cmd'] ?? '');

if (empty($cmd)) {
    http_response_code(400);
    echo json_encode(['error' => 'No command specified. Use ?cmd=getdata|search|add|getpets']);
    exit;
}

// Route to the correct handler based on cmd
switch ($cmd) {

    case 'getdata':
        handleGetData();
        break;

    case 'search':
        handleSearch();
        break;

    case 'add':
        handleAdd();
        break;

    case 'getpets':
        handleGetPets();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown command: ' . htmlspecialchars($cmd, ENT_QUOTES, 'UTF-8')]);
        exit;
}


// cmd=getdata
// Returns all sightings for the map markers.
function handleGetData() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'GET required for cmd=getdata']);
        return;
    }

    $filters = [
        'name' => trim($_GET['name']    ?? ''),
        'species' => trim($_GET['species'] ?? ''),
        'status' => trim($_GET['status']  ?? ''),
    ];

    $sightings = fetchSightings($filters, 500, 0);
    echo json_encode(['success' => true, 'cmd' => 'getdata', 'sightings' => $sightings]);
}


//cmd=search
// Live search - returns filtered sightings for the search results
function handleSearch() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'GET required for cmd=search']);
        return;
    }

    $filters = [
        'name' => trim($_GET['name']    ?? ''),
        'species' => trim($_GET['species'] ?? ''),
        'status' => trim($_GET['status']  ?? ''),
    ];

    // Pagination - offset based
    $limit = min((int)($_GET['limit'] ?? 12),50); // cap at 50 per page
    $offset = max((int)($_GET['offset'] ?? 0),0);

    $sightingModel = new SightingModel();
    $total = $sightingModel->countAllSightings($filters);
    $sightings = fetchSightings($filters, $limit, $offset);

    echo json_encode([
        'success' => true,
        'cmd' => 'search',
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'sightings' => $sightings,
    ]);
}


// cmd=add
// Add a new sighting. Authenticated + CSRF protected.
function handleAdd() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required for cmd=add']);
        return;
    }

    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'You must be logged in to submit a sighting.']);
        return;
    }

    $userModel = new UserModel();
    $userId = (int)$_SESSION['user_id'];

    if (!$userModel->getUsernameById($userId)) {
        http_response_code(401);
        echo json_encode(['error' => 'Session invalid. Please log in again.']);
        return;
    }

    // CSRF validation - token sent as custom header by JS
    $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($sentToken) || empty($sessionToken) || !hash_equals($sessionToken, $sentToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token. Please refresh the page.']);
        return;
    }

    // Read JSON body
    $body = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload.']);
        return;
    }

    $petId = isset($body['pet_id'])  ? (int)$body['pet_id'] : 0;
    $comment = isset($body['comment']) ? trim($body['comment']) : '';
    $latitude = isset($body['latitude']) ? (float)$body['latitude'] : null;
    $longitude = isset($body['longitude']) ? (float)$body['longitude'] : null;

    // Server-side validation
    $errors = [];
    if ($petId <= 0) $errors[] = 'Please select a valid pet.';
    if (strlen($comment) < 5) $errors[] = 'Comment must be at least 5 characters.';
    if (strlen($comment) > 500) $errors[] = 'Comment must not exceed 500 characters.';
    if ($latitude  === null || $latitude  < -90  || $latitude  > 90)  $errors[] = 'Invalid latitude.';
    if ($longitude === null || $longitude < -180 || $longitude > 180) $errors[] = 'Invalid longitude.';

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['error' => implode(' ', $errors)]);
        return;
    }

    // Verify the pet exists
    $petModel = new PetModel();
    $pet = $petModel->getPetById($petId);

    if (!$pet) {
        http_response_code(404);
        echo json_encode(['error' => 'Pet not found.']);
        return;
    }

    // Save sighting using the same model as report.php
    $sightingModel = new SightingModel();
    $success = $sightingModel->addSighting([
        'pet_id' => $petId,
        'user_id' => $userId,
        'comment' => $comment,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'timestamp' => date('Y-m-d H:i:s'),
    ]);

    if ($success) {
        // Rotate CSRF token after each successful write
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        echo json_encode([
            'success' => true,
            'cmd' => 'add',
            'message' => 'Sighting submitted successfully!',
            'csrf_token' => $_SESSION['csrf_token'],
            'sighting' => [
                'pet_name' => htmlspecialchars($pet->getName(),    ENT_QUOTES, 'UTF-8'),
                'species' => htmlspecialchars($pet->getSpecies(), ENT_QUOTES, 'UTF-8'),
                'status' => htmlspecialchars($pet->getStatus(),  ENT_QUOTES, 'UTF-8'),
                'comment' => htmlspecialchars($comment,           ENT_QUOTES, 'UTF-8'),
                'lat' => $latitude,
                'lng' => $longitude,
                'timestamp' => date('Y-m-d H:i:s'),
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save sighting. Please try again.']);
    }
}


// cmd=getpets
// Returns all registered pets for the sighting form dropdown.
function handleGetPets() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'GET required for cmd=getpets']);
        return;
    }

    $petModel = new PetModel();
    $pets = $petModel->getAllPets([], 500, 0);

    $output = [];
    foreach ($pets as $pet) {
        $output[] = [
            'id' => $pet->getId(),
            'name' => htmlspecialchars($pet->getName(),ENT_QUOTES, 'UTF-8'),
            'species' => htmlspecialchars($pet->getSpecies(),ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($pet->getStatus(),ENT_QUOTES, 'UTF-8'),
        ];
    }

    echo json_encode(['success' => true, 'cmd' => 'getpets', 'pets' => $output]);
}


// Shared helper
// Fetches and sanitises sightings - used by both getdata and search.

function fetchSightings(array $filters, int $limit, int $offset): array {
    $sightingModel = new SightingModel();
    $rows = $sightingModel->getAllSightings($filters, $limit, $offset);

    $output = [];
    foreach ($rows as $s) {
        $lat = (float)$s->latitude;
        $lng = (float)$s->longitude;

        $output[] = [
            'id' => (int)$s->id,
            'pet_name' => htmlspecialchars($s->pet_name,  ENT_QUOTES, 'UTF-8'),
            'species' => htmlspecialchars($s->species,   ENT_QUOTES, 'UTF-8'),
            'status' => htmlspecialchars($s->status,    ENT_QUOTES, 'UTF-8'),
            'comment' => htmlspecialchars($s->comment,   ENT_QUOTES, 'UTF-8'),
            'username' => htmlspecialchars($s->username,  ENT_QUOTES, 'UTF-8'),
            'timestamp' => htmlspecialchars($s->timestamp, ENT_QUOTES, 'UTF-8'),
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    return $output;
}