<?php
// Controller: report.php - Handles sighting submissions
session_start();


require_once('Models/SightingModel.php');
require_once('Models/UserModel.php');
require_once('Models/PetModel.php');

// --- INITIAL SETUP ---
$view = new stdClass();
$view->pageTitle = 'Report a Sighting';
$view->errorMessage = null;
$view->successMessage = null;

$userModel = new UserModel();
$petModel = new PetModel();
$sightingModel = new SightingModel();

// --- Template Init ---
require_once('template_init.php');


// --- SECURITY CHECK (must be logged in to report) ---
if (!isset($_SESSION['user_id']) || !$userModel->getUsernameById((int)$_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// --- FETCH PETS FOR DROPDOWN ---
$allPets = $petModel->getAllPets([], 100, 0); // simple limit for selection

// --- HANDLE FORM SUBMISSION ---
if (isset($_POST['report_submit'])) {

    $petId = (int)($_POST['pet_id'] ?? 0);
    $comment = htmlspecialchars(trim($_POST['comment'] ?? ''));
    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    // Validate input
    if (empty($petId) || empty($comment) || empty($latitude) || empty($longitude)) {
        $view->errorMessage = "Error: All fields are required (pet, comment, and coordinates).";
    } else {
        // Inserts the sighting into the funny database
        $success = $sightingModel->addSighting([
            'pet_id' => $petId,
            'user_id' => $currentUserId,
            'comment' => $comment,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        if ($success) {
            $_SESSION['report_message'] = "Sighting report successfully submitted!";
        } else {
            $view->errorMessage = "Database error: Failed to save sighting report.";
        }
    }
}

// --- SUCCESS MESSAGE (POST-REDIRECT) ---
if (isset($_SESSION['report_message'])) {
    $view->successMessage = $_SESSION['report_message'];
    unset($_SESSION['report_message']);
}

// --- RENDER VIEW ---
require_once('Views/report.phtml');
