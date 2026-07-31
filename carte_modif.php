<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'donor') {
    header("Location: connexion.html");
    exit();
}

// Inclure le contenu HTML
readfile('carte_modif.html');
?>
