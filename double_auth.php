<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_code = isset($_POST['verification-code']) ? trim($_POST['verification-code']) : '';
    
    $valid_code = $_SESSION['otp_code'] ?? '123456';
    $user_role  = $_SESSION['user_role'] ?? 'donor';

    if (empty($submitted_code)) {
        die("Erreur : Aucun code n'a été transmis par le formulaire.");
    }

    if ($submitted_code === $valid_code) {
        
        $_SESSION['2fa_verified'] = true;

        switch (strtolower(trim($user_role))) {
            
            case 'donor':
            case 'donneur':
                if (!empty($_SESSION['consentement_valide'])) {
                    header("Location: carte_donneur.html?name=" . urlencode($_SESSION['user_name'] ?? ''));
                } else {
                    header("Location: consentement_donneur.php");
                }
                break;

            case 'hospital':
            case 'hopital':
                header("Location: dashboard_hop.php?name=" . urlencode($_SESSION['user_name'] ?? ''));
                break;

            case 'organe':
            case 'ethic-board':
            case 'agence':
                header("Location: dashboard_organe.php");
                break;

            default:
                header("Location: connexion.html");
                break;
        }
        exit();

    } else {
        header("Location: double_auth.html?error=code_invalide");
        exit();
    }

} else {
    $code_param = $_SESSION['otp_code'] ?? '';
    header("Location: double_auth.html?code=" . urlencode($code_param));
    exit();
}
?>
