<?php
// Controller: login.php - Handles authentication logic.
session_start();

require_once('Models/UserModel.php');

// 1. SETUP AND INITIALIZATION
$view = new stdClass();
$view->pageTitle = 'User Login';
$view->errorMessage = null;

$userModel = new UserModel();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 2. INPUT HANDLING (Handle Login Submission)
if (isset($_POST['login'])) {
    // Sanitize input
    $user = isset($_POST['username']) ? htmlspecialchars(trim($_POST['username'])) : '';
    $pass = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '';

    // Uses OOP Model for credential verification
    $userId = $userModel->verifyCredentials($user, $pass);

    if ($userId) {
        $_SESSION['user_id'] = $userId;
        header('Location: index.php'); // PRG pattern redirect
        exit;
    } else {
        $view->errorMessage = "Invalid username or password. (Try Lee/password or Zara/password)";
    }
}

// 3. VIEW RENDERING
require_once('Views/login.phtml');
?>