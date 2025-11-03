<?php
// Controller: add_pet.php - Handles pet registration by owners
session_start();

require_once('Models/UserModel.php');
require_once('Models/PetModel.php');

// --- INITIAL SETUP ---
$view = new stdClass();
$view->pageTitle = 'Add Pet';
$view->errorMessage = null;
$view->successMessage = null;

$userModel = new UserModel();
$petModel = new PetModel();

// --- Template Init ---
require_once('template_init.php');


// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !$userModel->getUsernameById((int)$_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$userRole = $userModel->getRoleById($currentUserId);

if ($userRole !== 'admin') {
    $view->errorMessage = "Access denied. Only owners can add pets.";
    require_once('Views/add_pet.phtml');
}

// --- HANDLE FORM SUBMISSION ---
if (isset($_POST['submit_pet'])) {
    $data = [
        'name'          => htmlspecialchars(trim($_POST['name'] ?? '')),
        'species'       => htmlspecialchars(trim($_POST['species'] ?? '')),
        'breed'         => htmlspecialchars(trim($_POST['breed'] ?? '')),
        'color'         => htmlspecialchars(trim($_POST['color'] ?? '')),
        'photo_url'     => htmlspecialchars(trim($_POST['photo_url'] ?? '')),
        'status'        => htmlspecialchars(trim($_POST['status'] ?? '')),
        'description'   => htmlspecialchars(trim($_POST['description'] ?? '')),
        'date_reported' => date('Y-m-d H:i:s'),
        'user_id'       => $currentUserId
    ];

    if (empty($data['name']) || empty($data['species'])) {
        $view->errorMessage = "Error: Pet name and species are required.";
    } elseif ($petModel->addPet($data)) {
        $view->successMessage = "Pet successfully added!";
    } else {
        $view->errorMessage = "Failed to save pet. Please check your database connection.";
    }
}

// --- RENDER VIEW ---
require_once('Views/add_pet.phtml');
