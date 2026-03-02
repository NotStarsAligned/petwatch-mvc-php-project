<?php
// Controller: map.php - Interactive live sightings map
session_start();

require_once('Models/UserModel.php');

// --- INITIAL SETUP ---
$view = new stdClass();
$view->pageTitle = 'Live Map';
$view->errorMessage = null;

$userModel = new UserModel();

// --- Template Init ---
require_once('template_init.php');

// --- Generate CSRF token for authenticated users ---
// Passed into the view so JS can attach it to AJAX POST requests as a header.
if (!empty($_SESSION['user_id']) && $userModel->getUsernameById((int)$_SESSION['user_id'])) {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $view->csrfToken  = $_SESSION['csrf_token'];
    $view->isLoggedIn = true;
} else {
    $view->csrfToken  = '';
    $view->isLoggedIn = false;
}

// --- RENDER VIEW ---
require_once('Views/map.phtml');
