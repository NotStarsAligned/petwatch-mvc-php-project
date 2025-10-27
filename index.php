<?php
// Controller: index.php - Main entry for sightings view
session_start();

require_once('Models/UserModel.php');
require_once('Models/SightingModel.php');

// 1. SETUP
$view = new stdClass();
$view->pageTitle = 'petWatch: Home';
$view->username = null;
$view->isLoggedIn = false;
$view->loginMessage = null;

$userModel = new UserModel();
$sightingModel = new SightingModel();

// 2. LOGIN STATE CHECK
if (isset($_SESSION['user_id'])) {
    $username = $userModel->getUsernameById((int)$_SESSION['user_id']);
    if ($username) {
        $view->isLoggedIn = true;
        $view->username = $username;
    } else {
        session_destroy();
        session_start();
    }
}

// 3. SEARCH FILTERS
$filters = [
    'name' => trim($_GET['search_name'] ?? ''),
    'species' => trim($_GET['search_type'] ?? ''),
    'status' => trim($_GET['search_status'] ?? 'All'),
];
$view->searchParams = $filters;

// 4. PAGINATION SETUP
$itemsPerPage = 6;
$view->currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($view->currentPage - 1) * $itemsPerPage;

// 5. FETCH SIGHTINGS
$view->totalItems = $sightingModel->countAllSightings($filters);
$view->sightings = $sightingModel->getAllSightings($filters, $itemsPerPage, $offset);
$view->totalPages = ceil($view->totalItems / $itemsPerPage);

// 6. VIEW RENDERING
require_once('Views/index.phtml');
