<?php
session_start();

// 1. Génération d'un NOUVEAU code OTP aléatoire à 6 chiffres
$new_otp = rand(100000, 999999);

// 2. Mise à jour du code stocké en Session
$_SESSION['otp_code'] = (string)$new_otp;

// 3. Récupération de l'email stocké lors de la connexion
$user_email = $_SESSION['user_email'] ?? '';

if (!empty($user_email)) {
    $subject = "Nouveau code de vérification - Bioéthique CM";
    $message = "Voici votre nouveau code de confirmation : " . $new_otp;
    $headers = "From: no-reply@bioethique.cm\r\n" .
               "Reply-To: no-reply@bioethique.cm\r\n";

    @mail($user_email, $subject, $message, $headers);
}

// 4. Redirection vers double_auth.html avec le nouveau code dans l'URL (pour vos tests locaux)
header("Location: double_auth.html?code=" . $new_otp . "&resent=1");
exit();
?>