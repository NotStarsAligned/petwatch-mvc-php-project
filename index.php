<?php
// Controller: index.php - Main entry for sightings view
session_start();

require_once('Models/UserModel.php');
require_once('Models/SightingModel.php');

// SETUP
$view = new stdClass();
$view->pageTitle = 'petWatch: Home';
$view->username = null;
$view->isLoggedIn = false;
$view->loginMessage = null;

$userModel = new UserModel();
$sightingModel = new SightingModel();

// --- Template Init ---
require_once('template_init.php');


// LOGIN STATE CHECK
if (isset($_SESSION['user_id'])) {
    $username = $userModel->getUsernameById((int)$_SESSION['user_id']);
    if ($username) {
        $view->isLoggedIn = true;
        $view->username = $username;

        // Generate CSRF token for the AJAX sighting form in the map popups
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    } else {
        session_destroy();
        session_start();
    }
}

// Generate AJAX search token for LiveSearch.js - regenerated on each page load.
$_SESSION['ajaxToken'] = substr(str_shuffle(MD5(microtime())), 0, 20);

// SEARCH FILTERS
$filters = [
    'name' => trim($_GET['search_name'] ?? ''),
    'species' => trim($_GET['search_type'] ?? ''),
    'status' => trim($_GET['search_status'] ?? 'All'),
];
$view->searchParams = $filters;

//  PAGINATION SETUP
$itemsPerPage = 6;
$view->currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($view->currentPage - 1) * $itemsPerPage;

//  FETCH SIGHTINGS
$view->totalItems = $sightingModel->countAllSightings($filters);
$view->sightings = $sightingModel->getAllSightings($filters, $itemsPerPage, $offset);
$view->totalPages = ceil($view->totalItems / $itemsPerPage);

//  VIEW RENDERING
require_once('Views/index.phtml');