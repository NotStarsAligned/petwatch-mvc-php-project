<?php
// Controller: pets.php - Lists all pets
session_start();

require_once('Models/UserModel.php');
require_once('Models/PetModel.php');

// --- INITIAL SETUP ---
$view = new stdClass();
$view->pageTitle = 'All Pets';
$view->errorMessage = null;
$view->pets = [];

$userModel = new UserModel();
$petModel = new PetModel();

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !$userModel->getUsernameById((int)$_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $userModel->getRoleById((int)$_SESSION['user_id']);

$_SESSION['role'] = $userRole;
if ($userRole !== 'admin') {
    $view->errorMessage = "Access denied. Only owners can add pets.";
    require_once('Views/pets.phtml');
}



// --- DELETE PETS ---

if(isset($_POST['delete_pet']) && isset($_POST['id'])) {
    $petID = $_POST['id'];
    if ($petModel->deletePet($petID)) {
        $view->successMessage = 'The pet was successfully deleted.';
    } else {
        $view->errorMessage = 'The pet was not deleted. Please try again.';
    }
}

// --- DATA FETCH ---
$view->pets = $petModel->getAllPets(); // fetch all pets

// --- RENDER VIEW ---
require_once('Views/pets.phtml');
