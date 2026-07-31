<?php
// dashboard_hop.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Connexion à la base de données
require_once 'db.php';

// Code de l'hôpital connecté (ex: depuis la session)
$hopital_code = $_SESSION['user_role'] ?? 'HGY'; 
$user_email   = $_SESSION['user_email'] ?? 'coordination@hgy.cm';
$message_status = '';

try {
    // 1. Traitement du formulaire de mise à jour de la garde
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_garde'])) {
        $chirurgien   = trim($_POST['chirurgien_garde'] ?? '');
        $contact      = trim($_POST['coordinateur_contact'] ?? '');
        $capacite_usi = trim($_POST['statut_dispo'] ?? 'disponible');

        $stmt_update = $pdo->prepare("
            INSERT INTO parametres_garde (hopital_code, chirurgien_garde, coordinateur_contact, capacite_usi)
            VALUES (:hopital, :chirurgien, :contact, :capacite)
            ON DUPLICATE KEY UPDATE 
                chirurgien_garde = VALUES(chirurgien_garde), 
                coordinateur_contact = VALUES(coordinateur_contact), 
                capacite_usi = VALUES(capacite)
        ");
        
        if ($stmt_update->execute([
            'chirurgien' => $chirurgien,
            'contact'    => $contact,
            'capacite'   => $capacite_usi,
            'hopital'    => $hopital_code
        ])) {
            $message_status = "<div style='color: #2e7d32; background-color: #e8f5e9; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 500;'>Garde mise à jour avec succès !</div>";
        }
    }

    // 2. Récupération des paramètres de garde
    $stmt_garde = $pdo->prepare("SELECT * FROM parametres_garde WHERE hopital_code = :hopital LIMIT 1");
    $stmt_garde->execute(['hopital' => $hopital_code]);
    $garde = $stmt_garde->fetch(PDO::FETCH_ASSOC);

    if (!$garde) {
        $garde = [
            'chirurgien_garde'     => 'Dr. Ndemba (Néphrologie)',
            'coordinateur_contact' => '+237 677 88 99 00',
            'capacite_usi'         => 'disponible',
            'lits_occupes'         => 8,
            'lits_totaux'          => 12
        ];
    }

    // 3. Récupération des greffons
    $stmt_greffons = $pdo->prepare("SELECT * FROM greffons WHERE hopital_source = :hopital OR hopital_source = 'HGY' ORDER BY heure_clampage DESC");
    $stmt_greffons->execute(['hopital' => $hopital_code]);
    $greffons = $stmt_greffons->fetchAll(PDO::FETCH_ASSOC);

    // 4. Nombre d'organes en attente
    $stmt_attente = $pdo->prepare("SELECT COUNT(*) FROM greffons WHERE (hopital_source = :hopital OR hopital_source = 'HGY') AND expedition_destination = 'En attente'");
    $stmt_attente->execute(['hopital' => $hopital_code]);
    $organes_en_attente = (int) $stmt_attente->fetchColumn();

} catch (PDOException $e) {
    die("<div style='color: red; padding: 20px; font-family: sans-serif;'><strong>Erreur SQL :</strong> " . htmlspecialchars($e->getMessage()) . "</div>");
}

// 5. Chargement de la vue HTML
ob_start();
require_once 'dashboard_hop.html';
$page = ob_get_clean();
$page = str_replace('</head>', "    <link rel=\"stylesheet\" href=\"navigation.css\">\n</head>", $page);
$page = str_replace('</body>', "    <script src=\"navigation.js\"></script>\n</body>", $page);
echo $page;
