<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(303);
    header('Location: carte_donneur.html');
    exit();
}

$fullName = trim($_POST['full_name'] ?? '');
$bloodGroup = trim($_POST['blood_group'] ?? '');
$birthYear = trim($_POST['birth_year'] ?? '');
$emergencyContact = trim($_POST['emergency_contact'] ?? '');
$consentedOrgans = trim($_POST['consented_organs'] ?? '');

if ($fullName === '' || $bloodGroup === '' || $birthYear === '' || $emergencyContact === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez remplir tous les champs obligatoires.'
    ]);
    exit();
}

$_SESSION['donor_card'] = [
    'full_name'         => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
    'blood_group'       => htmlspecialchars($bloodGroup, ENT_QUOTES, 'UTF-8'),
    'birth_year'        => htmlspecialchars($birthYear, ENT_QUOTES, 'UTF-8'),
    'emergency_contact' => htmlspecialchars($emergencyContact, ENT_QUOTES, 'UTF-8'),
    'consented_organs'  => htmlspecialchars($consentedOrgans, ENT_QUOTES, 'UTF-8')
];

$userId = $_SESSION['user_id'] ?? null;
$pdo = null;

if (file_exists('db.php')) {
    require_once 'db.php';
    $pdo = $pdo ?? null;
}

if ($pdo && $userId !== null && is_numeric($userId)) {
    try {
        $parts = preg_split('/\s+/', $fullName, 2);
        $prenom = trim($parts[0] ?? '');
        $nom = trim($parts[1] ?? '');

        $check = $pdo->prepare('SELECT id FROM donor_cards WHERE user_id = :user_id LIMIT 1');
        $check->execute(['user_id' => (int)$userId]);

        if ($check->fetch()) {
            $stmt = $pdo->prepare('UPDATE donor_cards SET prenom = :prenom, nom = :nom, birth_year = :birth_year, blood_group = :blood_group, consented_organs = :consented_organs, emergency_contact = :emergency_contact, updated_at = NOW() WHERE user_id = :user_id');
            $stmt->execute([
                'prenom' => $prenom,
                'nom' => $nom,
                'birth_year' => (int)$birthYear,
                'blood_group' => $bloodGroup,
                'consented_organs' => $consentedOrgans,
                'emergency_contact' => $emergencyContact,
                'user_id' => (int)$userId
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO donor_cards (user_id, prenom, nom, birth_year, blood_group, consented_organs, emergency_contact) VALUES (:user_id, :prenom, :nom, :birth_year, :blood_group, :consented_organs, :emergency_contact)');
            $stmt->execute([
                'user_id' => (int)$userId,
                'prenom' => $prenom,
                'nom' => $nom,
                'birth_year' => (int)$birthYear,
                'blood_group' => $bloodGroup,
                'consented_organs' => $consentedOrgans,
                'emergency_contact' => $emergencyContact
            ]);
        }
    } catch (Exception $e) {
        // L'enregistrement en base peut échouer sans bloquer l'expérience utilisateur.
    }
}

echo json_encode([
    'success' => true,
    'redirect' => 'dashboard_donneur.php'
]);
exit();
?>