<?php
// This exists to make the top nav-bar thing ACTUALLY dynamically update with the pages

if(!isset($view)) $view = new stdClass();

$view->isLoggedIn = false;
$view->username = null;
$view->role = null;

if (isset($_SESSION['user_id'])) {
    require_once('Models/UserModel.php');
    $userModel = new UserModel();

    $userId = (int)$_SESSION['user_id'];
    $username = $userModel->getUsernameById($userId);
    $role = $userModel->getRoleById($userId);

    if ($username) {
        $view->isLoggedIn = true;
        $view->username = $username;
        $view->role = $role;
    }
}