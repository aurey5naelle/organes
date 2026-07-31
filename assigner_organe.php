<?php
session_start();
require_once 'db.php';

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Non autorisé. Veuillez vous connecter.']);
        exit();
    }
    header("Location: connexion.html");
    exit();
}

// Si la page est ouverte directement dans le navigateur
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    readfile('assigner_organe.html');
    exit();
}

header('Content-Type: application/json; charset=utf-8');
$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        /* =========================================================
           1. Récupération des organes disponibles (Table: greffons)
           ========================================================= */
        case 'get_organes':
            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    code_greffon, 
                    COALESCE(donor_name, 'Anonyme') AS donor_name,
                    donor_contact,
                    organe, 
                    COALESCE(groupe_sanguin, 'N/A') AS groupe_sanguin,
                    donor_age,
                    donor_weight,
                    COALESCE(hopital_source, 'Non spécifié') AS hopital_source,
                    heure_clampage,
                    COALESCE(statut_validation, 'En cours') AS statut
                FROM greffons 
                WHERE (
                    LOWER(TRIM(statut_validation)) = 'en cours' 
                    OR LOWER(TRIM(statut_validation)) = 'disponible' 
                    OR statut_validation IS NULL
                )
                ORDER BY id DESC
            ");
            $stmt->execute();
            $organes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($organes);
            break;

        /* =========================================================
           2. Récupération des receveurs (Table: liste_attente)
           ========================================================= */
        case 'get_patients':
            // 1. Inspecter les colonnes réellement existantes dans liste_attente
            $colsQuery = $pdo->query("DESCRIBE liste_attente");
            $existingCols = $colsQuery->fetchAll(PDO::FETCH_COLUMN);

            // Détection du champ Nom Patient
            if (in_array('nom_patient', $existingCols)) {
                $colNom = "nom_patient";
            } elseif (in_array('nom', $existingCols) && in_array('prenom', $existingCols)) {
                $colNom = "CONCAT(nom, ' ', prenom)";
            } elseif (in_array('nom', $existingCols)) {
                $colNom = "nom";
            } else {
                $colNom = "'Patient Inconnu'";
            }

            // Détection du Code Patient
            $colCode = in_array('code_patient', $existingCols) ? "code_patient" : "CONCAT('PAT-', id)";

            // Détection du Type d'organe requis avec vérification élargie
            $possibleOrganCols = ['organe_requis', 'type_organe', 'organe', 'organe_souhaite', 'organe_recherche', 'type_greffe', 'besoin_organe'];
            $colOrgane = null;

            foreach ($possibleOrganCols as $col) {
                if (in_array($col, $existingCols)) {
                    $colOrgane = $col;
                    break;
                }
            }

            // Si aucun nom standard n'est trouvé, cherche n'importe quelle colonne contenant 'organ' ou 'greff'
            if (!$colOrgane) {
                foreach ($existingCols as $col) {
                    if (strpos($col, 'organ') !== false || strpos($col, 'greff') !== false) {
                        $colOrgane = $col;
                        break;
                    }
                }
            }

            // Valeur de secours ultime
            if (!$colOrgane) {
                $colOrgane = "'Non spécifié'";
            }

            // Détection du Groupe Sanguin
            $colSanguin = in_array('groupe_sanguin', $existingCols) ? "COALESCE(groupe_sanguin, 'N/A')" : "'N/A'";

            // Détection du Score d'urgence
            if (in_array('score_urgence', $existingCols)) {
                $colUrgence = "COALESCE(score_urgence, 0)";
            } elseif (in_array('urgence', $existingCols)) {
                $colUrgence = "COALESCE(urgence, 0)";
            } elseif (in_array('niveau_urgence', $existingCols)) {
                $colUrgence = "COALESCE(niveau_urgence, 0)";
            } else {
                $colUrgence = "0";
            }

            // Détection du Statut
            $colStatut = in_array('statut', $existingCols) ? "statut" : "NULL";

            // Construction du filtre selon le statut s'il existe
            $whereClause = "WHERE 1=1";
            if (in_array('statut', $existingCols)) {
                $whereClause .= " AND (
                    statut IS NULL 
                    OR statut = '' 
                    OR LOWER(TRIM(statut)) = 'en_attente'
                    OR LOWER(TRIM(statut)) = 'en attente'
                    OR LOWER(TRIM(statut)) = 'actif'
                )";
            }

            $organ_type = isset($_POST['organ_type']) ? trim($_POST['organ_type']) : '';
            $base_organ_type = preg_replace('/\s*\(.*?\)/', '', $organ_type);

            $params = [];
            if (!empty($organ_type) && $colOrgane !== "'Non spécifié'") {
                $whereClause .= " AND (
                    LOWER({$colOrgane}) LIKE LOWER(:exact_type)
                    OR LOWER({$colOrgane}) LIKE LOWER(:base_type)
                    OR LOWER(:exact_type) LIKE LOWER(CONCAT('%', {$colOrgane}, '%'))
                )";
                $params[':exact_type'] = '%' . $organ_type . '%';
                $params[':base_type']  = '%' . $base_organ_type . '%';
            }

            $sql = "
                SELECT 
                    id, 
                    {$colCode} AS code_patient, 
                    {$colNom} AS nom_patient, 
                    {$colOrgane} AS type_organe_requis, 
                    {$colSanguin} AS groupe_sanguin, 
                    {$colUrgence} AS score_urgence,
                    {$colStatut} AS statut
                FROM liste_attente 
                {$whereClause}
                ORDER BY {$colUrgence} DESC, id DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($patients);
            break;

        /* =========================================================
           3. Assignation d'un organe à un receveur
           ========================================================= */
        case 'assign_organ':
            $greffon_id = isset($_POST['greffon_id']) ? intval($_POST['greffon_id']) : 0;
            $patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;

            if ($greffon_id <= 0 || $patient_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Identifiants invalides.']);
                exit();
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT code_greffon, organe FROM greffons WHERE id = :id");
            $stmt->execute([':id' => $greffon_id]);
            $organe = $stmt->fetch(PDO::FETCH_ASSOC);

            // Vérifier structure liste_attente pour la récupération du nom
            $colsQuery = $pdo->query("DESCRIBE liste_attente");
            $existingCols = $colsQuery->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('nom_patient', $existingCols)) {
                $colNomSelect = "nom_patient";
            } elseif (in_array('nom', $existingCols) && in_array('prenom', $existingCols)) {
                $colNomSelect = "CONCAT(nom, ' ', prenom)";
            } elseif (in_array('nom', $existingCols)) {
                $colNomSelect = "nom";
            } else {
                $colNomSelect = "'Patient'";
            }

            $stmt = $pdo->prepare("SELECT {$colNomSelect} AS nom_patient FROM liste_attente WHERE id = :id");
            $stmt->execute([':id' => $patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$organe || !$patient) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Organe ou receveur introuvable.']);
                exit();
            }

            // Mise à jour du statut du greffon
            $stmt1 = $pdo->prepare("UPDATE greffons SET statut_validation = 'Matché' WHERE id = :id");
            $stmt1->execute([':id' => $greffon_id]);

            // Mise à jour du statut du receveur si le champ existe
            if (in_array('statut', $existingCols)) {
                $stmt2 = $pdo->prepare("UPDATE liste_attente SET statut = 'transplante' WHERE id = :id");
                $stmt2->execute([':id' => $patient_id]);
            }

            // Journalisation
            $stmtLog = $pdo->prepare("INSERT INTO activity_logs (action, details, created_at) VALUES (:action, :details, NOW())");
            $stmtLog->execute([
                ':action'  => 'Assignation organe',
                ':details' => "L'organe {$organe['code_greffon']} ({$organe['organe']}) a été attribué au patient {$patient['nom_patient']}."
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'greffon' => $organe['code_greffon'],
                'patient' => $patient['nom_patient']
            ]);
            break;

        default:
            echo json_encode(['error' => 'Action non reconnue']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => 'Erreur SQL : ' . $e->getMessage()]);
}