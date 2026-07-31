<?php
// expedition.php - Récupération UNIQUEMENT des greffons Matchés
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    // 1. Détection dynamique des colonnes de la table liste_attente
    $colsQuery = $pdo->query("DESCRIBE liste_attente");
    $existingCols = $colsQuery->fetchAll(PDO::FETCH_COLUMN);

    // Champ Nom du Patient
    if (in_array('nom_patient', $existingCols)) {
        $colNom = "p.nom_patient";
    } elseif (in_array('nom', $existingCols) && in_array('prenom', $existingCols)) {
        $colNom = "CONCAT(p.nom, ' ', p.prenom)";
    } elseif (in_array('nom', $existingCols)) {
        $colNom = "p.nom";
    } else {
        $colNom = "'Patient Inconnu'";
    }

    // Champ Code du Patient
    $colCodePatient = in_array('code_patient', $existingCols) ? "p.code_patient" : "CONCAT('PAT-', p.id)";

    // Détection de la colonne organe dans liste_attente
    $possibleOrganCols = ['type_organe', 'organe', 'organe_requis', 'organe_souhaite', 'besoin_organe'];
    $colOrganePatient = null;

    foreach ($possibleOrganCols as $col) {
        if (in_array($col, $existingCols)) {
            $colOrganePatient = "p." . $col;
            break;
        }
    }

    if (!$colOrganePatient) {
        foreach ($existingCols as $col) {
            if (strpos($col, 'organ') !== false || strpos($col, 'greff') !== false) {
                $colOrganePatient = "p." . $col;
                break;
            }
        }
    }

    if (!$colOrganePatient) {
        throw new Exception("Impossible de localiser la colonne de l'organe dans liste_attente.");
    }

    // 2. Requête SQL avec filtre STRICT sur le statut 'Matché'
    $sql = "
        SELECT 
            g.id AS id_assignation,
            g.code_greffon,
            g.organe,
            COALESCE(g.donor_name, 'Anonyme') AS donneur,
            COALESCE(g.groupe_sanguin, 'N/A') AS groupe_sanguin,
            COALESCE(g.expedition_destination, g.hopital_source, 'Non spécifié') AS hopital_destination,
            g.statut_validation AS statut,
            DATE_FORMAT(g.created_at, '%d/%m/%Y %H:%i') AS date_assignation,
            
            MAX(p.id) AS id_receveur,
            MAX({$colCodePatient}) AS code_patient,
            MAX({$colNom}) AS nom_patient
        FROM greffons g
        LEFT JOIN liste_attente p 
            ON LOWER(TRIM(g.organe)) = LOWER(TRIM({$colOrganePatient}))
        WHERE LOWER(TRIM(g.statut_validation)) = 'matché'
        GROUP BY g.id
        ORDER BY g.id DESC
    ";

    $stmt = $pdo->query($sql);
    $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = "Erreur SQL : " . $e->getMessage();
}

echo json_encode($response);
exit;