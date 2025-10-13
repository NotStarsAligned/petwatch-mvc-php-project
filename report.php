<?php
// Controller: report.php - Handles submitting a pet report.
session_start();

require_once('Models/PetModel.php');

// 1. SETUP AND INITIALIZATION
$view = new stdClass();
$view->pageTitle = 'Report a Pet';
$view->errorMessage = null;
$view->successMessage = null;

// Enforce login requirement (Access Control)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$petModel = new PetModel();
$userId = $_SESSION['user_id'];

// 2. INPUT HANDLING (Handle Report Submission)
if (isset($_POST['report_submit'])) {

    // Sanitize and structure data
    $reportData = [
        'name'      => htmlspecialchars(trim($_POST['name'])),
        'type'      => htmlspecialchars(trim($_POST['type'])),
        'status'    => htmlspecialchars(trim($_POST['status'])),
        'location'  => htmlspecialchars(trim($_POST['location'])),
        'user_id'   => $userId
    ];

    if (empty($reportData['name']) || empty($reportData['location'])) {
        $view->errorMessage = "Please fill in all required fields.";
    } else {
        // Uses OOP Model to persist data (temporarily in memory)
        if ($petModel->addPet($reportData)) {
            $view->successMessage = "Report for '{$reportData['name']}' successfully submitted!";
        } else {
            $view->errorMessage = "Error saving report to temporary storage.";
        }
    }
}

// 3. VIEW RENDERING
require_once('Views/report.phtml');
?>