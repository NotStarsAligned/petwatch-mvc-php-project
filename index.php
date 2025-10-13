<?php
// Controller: index.php - Main entry point. Handles session, logout, and displays listings.
session_start();

require_once('Models/UserModel.php');
require_once('Models/PetModel.php');


// 1. INITIALIZATION (MUST RUN FIRST)
$view = new stdClass();
$view->pageTitle = 'petWatch: Home (DEV MODE)';
$view->username = null;
$view->loginMessage = null;
$view->isLoggedIn = false;

$userModel = new UserModel();
$petModel = new PetModel();

// 2. INPUT HANDLING (Handle Logout)
if (isset($_POST['logout'])) {
    session_destroy();
    session_start();
    $view->loginMessage = "You have been logged out successfully.";
}

// 3. DATA LOADING & STATE CHECK
if (isset($_SESSION['user_id'])) {
    $view->isLoggedIn = true;
    // Uses OOP Model to get username
    $view->username = $userModel->getUsernameById($_SESSION['user_id']);
}

// Loads pet list (array of PetData objects) from the Model
$view->petList = $petModel->getAllPets();


// 4. VIEW RENDERING (MUST BE THE LAST LINE)
require_once('Views/index.phtml');
?>