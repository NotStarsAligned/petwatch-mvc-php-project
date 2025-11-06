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

// --- Template Init ---
require_once('template_init.php');

// --- SECURITY CHECK ---
// When you're not logged in, you get a cool error message yelling at you (also a good way to filter out non-admins :D)
if (!isset($_SESSION['user_id']) || !$userModel->getUsernameById((int)$_SESSION['user_id'])) {
    $view->errorMessage = 'You must be logged in to access this page!';
    require_once('Views/login.phtml');
    return;
}

// Responsible for checking if you have the right role!!
$userRole = $userModel->getRoleById((int)$_SESSION['user_id']);

$_SESSION['role'] = $userRole;
if ($userRole !== 'admin') {
    $view->errorMessage = "Access denied. Only owners can add pets.";
    require_once('Views/pets.phtml'); // This actually updates the pets page so it hides it when you have the incorrect perms
    return;
}

// Updates the pet!!

if (isset($_POST['update_pet'])) {
    $id = (int)($_POST['pet_id'] ?? 0);

    $data = [
        'name' => htmlspecialchars(trim($_POST['name'] ?? '')),
        'species' => htmlspecialchars(trim($_POST['species'] ?? '')),
        'breed' => htmlspecialchars(trim($_POST['breed'] ?? '')),
        'color' => htmlspecialchars(trim($_POST['color'] ?? '')),
        'photo_url' => htmlspecialchars(trim($_POST['photo_url'] ?? '')),
        'status' => htmlspecialchars(trim($_POST['status'] ?? '')),
        'description' => htmlspecialchars(trim($_POST['description'] ?? '')),
    ];

    if($petModel->updatePet($id, $data)) {
        $view->successMessage = 'Pet has been updated!';
    } else {
        $view->errorMessage = 'Failed to update pet!';
    }
}

// Deletes a pet!!

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
