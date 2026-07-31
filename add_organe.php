<?php
// Configuration de la connexion à la base de données
require_once 'db.php';

// Activer les exceptions PDO pour intercepter les erreurs SQL
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // 1. Récupération et nettoyage des données POST
    $donor_name       = trim($_POST['donor_name'] ?? '');
    $donor_contact    = trim($_POST['donor_contact'] ?? '');
    $organ_type       = trim($_POST['organ_type'] ?? '');
    $hospital_source  = trim($_POST['hospital_source'] ?? 'HGY');
    $appointment_date = trim($_POST['appointment_date'] ?? '');
    $lab_centre       = trim($_POST['lab_centre'] ?? '');
    $blood_group      = trim($_POST['blood_group'] ?? '');
    
    // Nettoyage et conversion sécurisée pour l'âge et le poids
    $donor_age_raw    = $_POST['donor_age'] ?? '';
    $donor_weight_raw = $_POST['donor_weight'] ?? '';
    
    $donor_age    = (is_numeric($donor_age_raw) && $donor_age_raw > 0) ? (int)$donor_age_raw : null;
    $donor_weight = (is_numeric($donor_weight_raw) && $donor_weight_raw > 0) ? (float)$donor_weight_raw : null;

    // Vérification des consentements obligatoires
    $consent_free    = isset($_POST['consent_free']);
    $consent_medical = isset($_POST['consent_medical']);
    $consent_legal   = isset($_POST['consent_legal']);

    if (empty($donor_name) || empty($donor_contact) || empty($organ_type) || empty($appointment_date) || empty($lab_centre)) {
        die("Erreur : Veuillez remplir tous les champs obligatoires (nom, contact, organe, date rdv, laboratoire).");
    }

    if (!$consent_free || !$consent_medical || !$consent_legal) {
        die("Erreur : Le consentement éclairé complet est obligatoire.");
    }

    // --- CORRECTION FORMAT DATE ---
    // Si la date vient d'un input type="date" (ex: "2026-08-01"), on ajoute l'heure pour MySQL DATETIME
    if (strlen($appointment_date) === 10) {
        $appointment_date .= " 09:00:00"; 
    }

    // 2. Génération du Code Greffon unique (ex: #RN-2026-B3)
    $prefixMap = [
        'rein'   => 'RN',
        'foie'   => 'FO',
        'cornee' => 'CO',
        'moelle' => 'MO'
    ];
    $clean_organ = strtolower($organ_type);
    $prefix = $prefixMap[$clean_organ] ?? 'OG';
    $randomCode = strtoupper(substr(md5(uniqid()), 0, 2));
    $code_greffon = "#" . $prefix . "-2026-" . $randomCode;

    try {
        $pdo->beginTransaction();

        // 3. Insertion dans la table `greffons`
        $sqlGreffon = "INSERT INTO `greffons` 
            (`code_greffon`, `donor_name`, `donor_contact`, `organe`, `groupe_sanguin`, `donor_age`, `donor_weight`, `hopital_source`, `heure_clampage`, `statut_validation`, `consentement_valide`) 
            VALUES 
            (:code_greffon, :donor_name, :donor_contact, :organe, :groupe_sanguin, :donor_age, :donor_weight, :hopital_source, NOW(), 'En cours', 1)";

        $stmtGreffon = $pdo->prepare($sqlGreffon);
        $stmtGreffon->execute([
            ':code_greffon'   => $code_greffon,
            ':donor_name'     => $donor_name,
            ':donor_contact'  => $donor_contact,
            ':organe'         => ucfirst($clean_organ),
            ':groupe_sanguin' => !empty($blood_group) ? $blood_group : null,
            ':donor_age'      => $donor_age,
            ':donor_weight'   => $donor_weight,
            ':hopital_source' => $hospital_source
        ]);

        $greffonId = $pdo->lastInsertId();

        // 4. Programmation du rendez-vous d'analyses
        $sqlRdv = "INSERT INTO `rendez_vous_analyses` (`greffon_id`, `date_rdv`, `laboratoire`, `statut_examen`) 
                   VALUES (:greffon_id, :date_rdv, :laboratoire, 'Programmé')";
        
        $stmtRdv = $pdo->prepare($sqlRdv);
        $stmtRdv->execute([
            ':greffon_id'  => $greffonId,
            ':date_rdv'    => $appointment_date,
            ':laboratoire' => $lab_centre
        ]);

        // 5. Enregistrement dans le journal `activity_logs`
        $sqlLog = "INSERT INTO `activity_logs` (`action`, `details`, `created_at`) 
                   VALUES (:action, :details, NOW())";
        $stmtLog = $pdo->prepare($sqlLog);
        $stmtLog->execute([
            ':action'  => 'Nouveau consentement donneur',
            ':details' => "Consentement validé pour le donneur {$donor_name} ({$code_greffon}). RDV prévu le {$appointment_date}."
        ]);

        // Valider la transaction
        $pdo->commit();

        // Redirection succès
        header("Location: dashboard_organe.php?success=1&code=" . urlencode($code_greffon));
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("<h3>Erreur lors de l'enregistrement SQL :</h3> " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        die("<h3>Erreur système :</h3> " . $e->getMessage());
    }

} else {
    header("Location: add_organe.html");
    exit();
}