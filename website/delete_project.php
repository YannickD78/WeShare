<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$project_id = $_POST['project_id'] ?? '';
$user = current_user();
$email = strtolower($user['email']);

$projects = load_projects();
$project_found = false;
$is_creator = false;

foreach ($projects as $project) {
    if ($project['id'] === $project_id) {
        $project_found = true;
        // Seul le créateur peut supprimer
        if (strtolower($project['creator_email']) === $email) {
            $is_creator = true;
        }
        break;
    }
}

if (!$project_found) {
    $_SESSION['error'] = "Projet introuvable.";
    header('Location: dashboard.php');
    exit;
}

if (!$is_creator) {
    $_SESSION['error'] = "Seul le créateur peut supprimer ce projet.";
    header('Location: dashboard.php');
    exit;
}

// Supprimer le projet
$projects = array_filter($projects, function ($p) use ($project_id) {
    return $p['id'] !== $project_id;
});

// Réindexer le tableau pour éviter les problèmes avec les clés
$projects = array_values($projects);

save_projects($projects);

$_SESSION['success'] = "Le projet a été supprimé avec succès.";
header('Location: dashboard.php');
exit;
