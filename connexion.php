<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role     = isset($_POST['user-role']) ? trim($_POST['user-role']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $user_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';

    if (empty($role) || empty($username) || empty($password) || empty($user_name)) {
        header("Location: connexion.html?error=champs_incomplets");
        exit();
    }

    $random_otp = rand(100000, 999999);

    $role_for_db = match (strtolower(trim($role))) {
        'donneur', 'donor' => 'donor',
        'hopital', 'hospital' => 'hospital',
        'organe', 'ethic-board', 'agence' => 'ethic-board',
        default => 'donor'
    };

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $userStmt->execute(['email' => $username]);
    $existingUser = $userStmt->fetch();

    if ($existingUser) {
        $user_id = (int)$existingUser['id'];
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insertUser = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (:email, :password, :role)");
        $insertUser->execute([
            'email' => $username,
            'password' => $passwordHash,
            'role' => $role_for_db,
        ]);
        $user_id = (int)$pdo->lastInsertId();
    }

    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id']        = $user_id;
    $_SESSION['user_email']     = $username;
    $_SESSION['user_name']      = $user_name;
    $_SESSION['user_role']      = $role;
    $_SESSION['otp_code']       = (string)$random_otp;
    unset($_SESSION['consentement_valide']);
    unset($_SESSION['consentement_date']);

    // Enregistrer/Mettre à jour l'hôpital s'il se connecte en tant qu'hôpital
    if ($role_for_db === 'hospital') {
        try {
            $stmtCheck = $pdo->prepare("SELECT id FROM hôpitaux_partenaires WHERE email = :email LIMIT 1");
            $stmtCheck->execute([':email' => $username]);
            $existingHospital = $stmtCheck->fetch();

            if ($existingHospital) {
                // Mettre à jour l'hôpital existant
                $stmtUpdate = $pdo->prepare("
                    UPDATE hôpitaux_partenaires
                    SET nom = :nom, updated_at = NOW()
                    WHERE email = :email
                ");
                $stmtUpdate->execute([
                    ':nom' => $user_name,
                    ':email' => $username
                ]);
            } else {
                // Créer un nouvel enregistrement hôpital
                $stmtInsert = $pdo->prepare("
                    INSERT INTO hôpitaux_partenaires (nom, code, email, statut)
                    VALUES (:nom, :code, :email, 'actif')
                ");
                $stmtInsert->execute([
                    ':nom' => $user_name,
                    ':code' => strtoupper(substr($user_name, 0, 3)),
                    ':email' => $username
                ]);
            }
        } catch (Exception $e) {
            // Silencieusement échouer si la table n'existe pas
        }
    }

    $to      = $username;
    $subject = "Votre code de vérification - Bioéthique CM";
    $message = "Bonjour,\n\nVoici votre code de confirmation pour accéder à votre espace : " . $random_otp . "\n\nCe code est confidentiel.";
    $headers = "From: no-reply@bioethique.cm\r\n" .
               "Reply-To: no-reply@bioethique.cm\r\n" .
               "X-Mailer: PHP/" . phpversion();

    @mail($to, $subject, $message, $headers);

    header("Location: double_auth.html?code=" . $random_otp);
    exit();

} else {
    header("Location: connexion.html");
    exit();
}
?>
