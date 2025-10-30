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

// --- DATA FETCH ---
$view->pets = $petModel->getAllPets(); // fetch all pets (limit to 100 for safety)

// --- RENDER VIEW ---
require_once('Views/pets.phtml');
