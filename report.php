<?php
// Controller: report.php - Handles pet submission logic.
session_start();

require_once('Models/PetModel.php');
require_once('Models/UserModel.php');

// 1. SETUP AND INITIALIZATION
$view = new stdClass();
$view->pageTitle = 'Report a Pet';
$view->successMessage = null;
$view->errorMessage = null;

$petModel = new PetModel();
$userModel = new UserModel();

// SECURITY CHECK: Redirect if not logged in
if (!isset($_SESSION['user_id']) || !$userModel->getUsernameById((int)$_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];

// Check for a success message set in the session (PRG pattern cleanup)
if (isset($_SESSION['report_message'])) {
    $view->successMessage = $_SESSION['report_message'];
    unset($_SESSION['report_message']);
}

// 2. INPUT HANDLING (Handle Report Submission via 'report_submit')
if (isset($_POST['report_submit'])) {

    // Map form status (Missing/Sighted) to DB status (Lost/Found)
    $statusMap = [
        'Missing' => 'Lost',
        'Sighted' => 'Found'
    ];

    // Prepare data structure, mapping form inputs to DB schema
    $data = [
        'name' => htmlspecialchars(trim($_POST['name'] ?? '')),
        'species' => htmlspecialchars(trim($_POST['type'] ?? '')), // 'type' -> 'species'
        'breed' => null,
        'color' => null,
        'photo_url' => null,
        'status' => $statusMap[($_POST['status'] ?? '')] ?? 'Unknown',
        'description' => htmlspecialchars(trim($_POST['location'] ?? null)), // 'location' -> 'description'
        'date_reported' => date('Y-m-d H:i:s'),
        'user_id' => $currentUserId
    ];

    if (empty($data['name']) || empty($data['species']) || empty($_POST['status'])) {
        $view->errorMessage = "Error: Pet Name, Type, and Status are required.";
    } else {
        $success = $petModel->addPet($data);

        if ($success) {
            // Success: Set message and redirect to prevent form resubmission
            $_SESSION['report_message'] = "Pet report successfully submitted!";
            header('Location: report.php');
            exit;
        } else {
            $view->errorMessage = "Error: Failed to save the pet report to the database.";
        }
    }
}

// 3. VIEW RENDERING
require_once('Views/report.phtml');