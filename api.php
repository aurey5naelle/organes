<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Action non reconnue'];

try {
    switch ($action) {
        
        // ===== SEARCH / FILTRES =====
        
        case 'search_greffons':
            $search = $_GET['q'] ?? '';
            $organe = $_GET['organe'] ?? '';
            $statut = $_GET['statut'] ?? '';
            
            $query = "SELECT * FROM greffons WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $query .= " AND (code_greffon LIKE :search OR donor_name LIKE :search OR groupe_sanguin LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            if (!empty($organe)) {
                $query .= " AND organe = :organe";
                $params[':organe'] = $organe;
            }
            if (!empty($statut)) {
                $query .= " AND statut_validation = :statut";
                $params[':statut'] = $statut;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT 50";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            $response = [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            break;

        case 'search_patients':
            $search = $_GET['q'] ?? '';
            $organe = $_GET['organe'] ?? '';
            
            $query = "SELECT * FROM liste_attente WHERE statut = 'en_attente'";
            $params = [];
            
            if (!empty($search)) {
                $query .= " AND (code_patient LIKE :search OR nom_patient LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }
            if (!empty($organe)) {
                $query .= " AND type_organe_requis = :organe";
                $params[':organe'] = $organe;
            }
            
            $query .= " ORDER BY score_urgence DESC LIMIT 50";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            $response = [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            break;

        case 'search_hopitaux':
            $search = $_GET['q'] ?? '';
            
            $query = "SELECT id, nom, code, adresse, email, telephone FROM hôpitaux_partenaires";
            $query .= " WHERE (nom LIKE :search OR code LIKE :search)";
            $query .= " ORDER BY nom ASC LIMIT 50";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([':search' => '%' . $search . '%']);
            
            $response = [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            break;

        // ===== VALIDATIONS =====

        case 'check_code_greffon':
            $code = trim($_GET['code'] ?? '');
            
            if (empty($code)) {
                $response = ['success' => false, 'message' => 'Code vide'];
                break;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM greffons WHERE code_greffon = :code LIMIT 1");
            $stmt->execute([':code' => $code]);
            $exists = $stmt->fetch();
            
            $response = [
                'success' => true,
                'exists' => (bool)$exists,
                'message' => $exists ? 'Code déjà utilisé' : 'Code disponible'
            ];
            break;

        case 'check_code_patient':
            $code = trim($_GET['code'] ?? '');
            
            if (empty($code)) {
                $response = ['success' => false, 'message' => 'Code vide'];
                break;
            }
            
            $stmt = $pdo->prepare("SELECT id FROM liste_attente WHERE code_patient = :code LIMIT 1");
            $stmt->execute([':code' => $code]);
            $exists = $stmt->fetch();
            
            $response = [
                'success' => true,
                'exists' => (bool)$exists,
                'message' => $exists ? 'Code déjà utilisé' : 'Code disponible'
            ];
            break;

        case 'validate_blood_compatibility':
            $donor = trim($_GET['donor'] ?? '');
            $recipient = trim($_GET['recipient'] ?? '');
            
            $compatibility = [
                'O+' => ['O+', 'A+', 'B+', 'AB+'],
                'O-' => ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'],
                'A+' => ['A+', 'AB+'],
                'A-' => ['A+', 'A-', 'AB+', 'AB-'],
                'B+' => ['B+', 'AB+'],
                'B-' => ['B+', 'B-', 'AB+', 'AB-'],
                'AB+' => ['AB+'],
                'AB-' => ['AB+', 'AB-']
            ];
            
            $isCompatible = isset($compatibility[$donor]) && in_array($recipient, $compatibility[$donor]);
            
            $response = [
                'success' => true,
                'compatible' => $isCompatible
            ];
            break;

        // ===== STATISTIQUES =====

        case 'get_statistics':
            $totalGreffons = (int)$pdo->query("SELECT COUNT(*) FROM greffons WHERE statut_validation != 'Rejeté'")->fetchColumn();
            $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM liste_attente WHERE statut = 'en_attente'")->fetchColumn();
            $totalMatchings = (int)$pdo->query("SELECT COUNT(*) FROM matchings WHERE statut IN ('propose', 'accepte')")->fetchColumn();
            
            $greffonsParOrgane = $pdo->query("
                SELECT organe, COUNT(*) as total 
                FROM greffons 
                WHERE statut_validation != 'Rejeté'
                GROUP BY organe
            ")->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $response = [
                'success' => true,
                'data' => [
                    'total_greffons' => $totalGreffons,
                    'total_patients' => $totalPatients,
                    'total_matchings' => $totalMatchings,
                    'greffons_par_organe' => $greffonsParOrgane
                ]
            ];
            break;

        // ===== LOGS D'ACTIVITÉ =====

        case 'get_activity_logs':
            $limit = (int)($_GET['limit'] ?? 20);
            $user_id = $_GET['user_id'] ?? null;
            
            $query = "SELECT * FROM activity_logs";
            $params = [];
            
            if ($user_id) {
                $query .= " WHERE user_id = :user_id";
                $params[':user_id'] = $user_id;
            }
            
            $query .= " ORDER BY created_at DESC LIMIT $limit";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            $response = [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            break;

        // ===== GESTION DU PROFIL =====

        case 'get_user_profile':
            if (!isset($_SESSION['user_id'])) {
                $response = ['success' => false, 'message' => 'Non authentifié'];
                break;
            }
            
            $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user['role'] === 'donor') {
                $stmt = $pdo->prepare("SELECT * FROM donor_cards WHERE user_id = :user_id LIMIT 1");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $user['card'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $response = [
                'success' => true,
                'data' => $user
            ];
            break;

        case 'update_user_name':
            if (!isset($_SESSION['user_id']) || !isset($_POST['user_name'])) {
                $response = ['success' => false, 'message' => 'Données manquantes'];
                break;
            }
            
            $_SESSION['user_name'] = trim($_POST['user_name']);
            
            $response = [
                'success' => true,
                'message' => 'Nom mis à jour',
                'user_name' => $_SESSION['user_name']
            ];
            break;

        // ===== PAR DÉFAUT =====

        default:
            $response = ['success' => false, 'message' => 'Action non trouvée: ' . $action];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
