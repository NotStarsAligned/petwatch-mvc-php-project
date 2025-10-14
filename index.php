<?php
// Controller: index.php - Main entry point. Handles session, logout, search, and displays listings.
session_start();

require_once('Models/UserModel.php');
require_once('Models/PetModel.php');


// 1. SETUP AND INITIALIZATION
$view = new stdClass();
$view->pageTitle = 'petWatch: Home';
$view->username = null;
$view->loginMessage = null;
$view->isLoggedIn = false;
$view->petList = [];

$userModel = new UserModel();
$petModel = new PetModel();

// PAGINATION SETUP
$itemsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $itemsPerPage;

// 2. INPUT HANDLING (Handle Logout via POST)
if (isset($_POST['logout'])) {
    session_destroy();
    session_start();
    $view->loginMessage = "You have been logged out successfully.";

    header('Location: index.php');
    exit;
}

// 3. DATA LOADING & STATE CHECK
if (isset($_SESSION['user_id'])) {
    $username = $userModel->getUsernameById((int)$_SESSION['user_id']);

    if ($username) {
        $view->isLoggedIn = true;
        $view->username = $username;
    } else {
        session_destroy();
        session_start();
        header('Location: login.php');
        exit;
    }
}

// SECURITY CHECK: Redirect unauthenticated users
if (!$view->isLoggedIn) {
    header('Location: login.php');
    exit;
}

// SEARCH PARAMETERS SETUP (Read from GET)
$searchParams = [
    'name' => trim($_GET['search_name'] ?? ''),
    'species' => trim($_GET['search_type'] ?? ''),
    'status' => trim($_GET['search_status'] ?? 'All'),
];

// Load pet list with filtering and pagination
$totalItems = $petModel->countAllPets($searchParams);
$view->petList = $petModel->getAllPets($searchParams, $itemsPerPage, $offset);

// Pass pagination/search metadata to the view
$view->searchParams = $searchParams;
$view->totalItems = $totalItems;
$view->itemsPerPage = $itemsPerPage;
$view->currentPage = $currentPage;
$view->totalPages = ceil($totalItems / $itemsPerPage);


// 4. VIEW RENDERING
require_once('Views/index.phtml');