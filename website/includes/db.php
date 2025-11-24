<?php

$host = 'mysql-weshare.alwaysdata.net';
$db   = 'weshare_users';
$user = 'weshare_admin';
$pass = 'adminWeShare';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Création de la connexion globale $pdo
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, $options);
} catch (\PDOException $e) {
    // Si erreur on arrête tout
    die("Erreur de connexion base de données : " . $e->getMessage());
}
?>