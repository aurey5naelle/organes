<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// 1. Contrôle de session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Session expirée. Veuillez vous reconnecter.']);
    exit();
}

// 2. Contrôle de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Requête non autorisée.']);
    exit();
}

// 3. Récupération des données POST
$code_dossier   = trim($_POST['code_dossier'] ?? '');
$target_user_id = trim($_POST['target_user_id'] ?? '');
$date_rdv       = trim($_POST['date_rdv'] ?? '');
$lieu           = trim($_POST['lieu'] ?? '');
$instructions   = trim($_POST['instructions'] ?? '');

// Validation simple
if (empty($code_dossier) || empty($date_rdv) || empty($lieu)) {
    echo json_encode(['success' => false, 'message' => 'Les champs Date et Lieu sont obligatoires.']);
    exit();
}

try {
    // 4. Si target_user_id est vide, recherche dynamique de l'utilisateur concerné
    if (empty($target_user_id)) {
        $stmtSearch = $pdo->prepare("
            SELECT user_id FROM greffons WHERE code_greffon = ? OR CONCAT('TX-', id) = ?
            UNION
            SELECT user_id FROM donor_cards WHERE CONCAT('CARD-', id) = ?
            UNION
            SELECT donneur_id AS user_id FROM dossiers_ethique WHERE code_dossier = ?
            LIMIT 1
        ");
        $stmtSearch->execute([$code_dossier, $code_dossier, $code_dossier, $code_dossier]);
        $row = $stmtSearch->fetch(PDO::FETCH_ASSOC);
        $target_user_id = $row['user_id'] ?? null;
    }

    // 5. Enregistrement dans la table `convocations`
    $stmtIns = $pdo->prepare("
        INSERT INTO convocations (user_id, code_dossier, date_rdv, lieu, instructions, statut, created_at)
        VALUES (?, ?, ?, ?, ?, 'En attente', NOW())
    ");
    
    $stmtIns->execute([
        !empty($target_user_id) ? $target_user_id : null,
        $code_dossier,
        $date_rdv,
        $lieu,
        $instructions
    ]);

    // 6. Optionnel : Journalisation dans les logs d'activité
    if (!empty($_SESSION['user_id'])) {
        $stmtLog = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details) 
            VALUES (?, 'Création de convocation', ?)
        ");
        $stmtLog->execute([
            $_SESSION['user_id'], 
            "Convocation programmée pour le dossier {$code_dossier} le {$date_rdv} à {$lieu}"
        ]);
    }

    // Réponse de succès au client JS
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}