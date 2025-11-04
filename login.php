<?php
// Controller: login.php - Handles authentication logic.
session_start();

require_once('Models/UserModel.php');

// SETUP AND INITIALIZATION
$view = new stdClass();
$view->pageTitle = 'User Login';
$view->errorMessage = null;
$view->successMessage = null;
$view->loginMessage = null;
$view->logoutMessage = null;

$userModel = new UserModel();

//  HANDLE LOGOUT REQUESTS!!!
if (isset($_POST['logout'])) {
    session_unset();     // remove all session variables
    session_destroy();   // destroy session


    $view->logoutMessage = "You have been logged out successfully. Goodbye o/";
    require_once('Views/login.phtml');
    return;
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $userModel->getUsernameById((int)$_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

//  INPUT HANDLING (Handle Login Submission)
if (isset($_POST['login'])) {
    // Sanitize input
    $user = isset($_POST['username']) ? htmlspecialchars(trim($_POST['username'])) : '';
    $pass = isset($_POST['password']) ? $_POST['password'] : '';

    // Verify credentials
    $userId = $userModel->verifyCredentials($user, $pass);

    if ($userId) {
        // This just stores the session data for whoever is logged in.
        $_SESSION['user_id'] = $userId;
        $userRole = $userModel->getRoleById($userId);
        $_SESSION['role'] = $userRole;

        $actualUsername = $userModel->getUsernameById($userId);

        $view->successMessage = "Login successful! Welcome, <strong>"
            . htmlspecialchars($actualUsername) . "</strong>. "
            . "You can now <a href='index.php'>go to the homepage</a>.";


    } else {
        // Invalid credentials
        $view->errorMessage = "Invalid username or password.";
    }
}

// VIEW RENDERING
require_once('Views/login.phtml');
