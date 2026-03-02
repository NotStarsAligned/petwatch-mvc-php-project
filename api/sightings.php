<?php
// api/sightings.php
// Returns sightings as JSON for the live map feature.
// Public endpoint — no auth required to VIEW sightings.

session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once('../Models/Database.php');
require_once('../Models/SightingModel.php');

try {
    $sightingModel = new SightingModel();

    // Optional filter params passed from JS FilterBar
    $filters = [
        'name'    => trim($_GET['name']    ?? ''),
        'species' => trim($_GET['species'] ?? ''),
        'status'  => trim($_GET['status']  ?? 'lost'),
    ];

    // Fetch all matching sightings (generous limit for map)
    $sightings = $sightingModel->getAllSightings($filters, 500, 0);

    $output = [];
    foreach ($sightings as $s) {
        $lat = (float)$s->latitude;
        $lng = (float)$s->longitude;

        // Skip malformed coordinates
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) continue;
        if ($lat === 0.0 && $lng === 0.0) continue;

        $output[] = [
            'id'        => (int)$s->id,
            'pet_name'  => htmlspecialchars($s->pet_name,  ENT_QUOTES, 'UTF-8'),
            'species'   => htmlspecialchars($s->species,   ENT_QUOTES, 'UTF-8'),
            'status'    => htmlspecialchars($s->status,    ENT_QUOTES, 'UTF-8'),
            'comment'   => htmlspecialchars($s->comment,   ENT_QUOTES, 'UTF-8'),
            'username'  => htmlspecialchars($s->username,  ENT_QUOTES, 'UTF-8'),
            'timestamp' => htmlspecialchars($s->timestamp, ENT_QUOTES, 'UTF-8'),
            'lat'       => $lat,
            'lng'       => $lng,
        ];
    }

    echo json_encode(['success' => true, 'sightings' => $output]);

} catch (Exception $e) {
    error_log("API Error (sightings.php): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
}
