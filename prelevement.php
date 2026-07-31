<?php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Hôpital';
$user_email = $_SESSION['user_email'] ?? '';
// Récupération du code de l'hôpital en session (ex: 'HGY'), sinon repli sur user_name
$user_role = $_SESSION['user_role'] ?? $user_name;

if (!$user_id) {
    header("Location: connexion.html");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donneur_id = trim($_POST['donneur_id'] ?? '');
    $groupe_sanguin = trim($_POST['groupe_sanguin'] ?? '');
    $organe = trim($_POST['organe'] ?? '');
    $heure_clampage = trim($_POST['heure_clampage'] ?? '');
    $duree_viabilite = (int)($_POST['duree_viabilite'] ?? 12);
    $protocole_accepte = isset($_POST['protocole_accepte']) ? 1 : 0;

    if (empty($donneur_id) || empty($groupe_sanguin) || empty($organe) || empty($heure_clampage) || !$protocole_accepte) {
        $message = "Veuillez remplir tous les champs obligatoires et accepter le protocole.";
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            $prefixMap = [
                'Rein' => 'RN',
                'Foie' => 'FO',
                'Cœur' => 'CO',
                'Poumon' => 'PO',
                'Cornée' => 'CR',
                'Moelle osseuse' => 'MO'
            ];
            $prefix = $prefixMap[$organe] ?? 'OG';
            $code_greffon = "#" . $prefix . "-" . date('Y') . "-" . strtoupper(substr(md5(uniqid()), 0, 2));

            $stmt = $pdo->prepare("
                INSERT INTO greffons 
                (code_greffon, donor_name, organe, groupe_sanguin, hopital_source, heure_clampage, duree_viabilite_heures, statut_validation, consentement_valide)
                VALUES 
                (:code_greffon, :donor_name, :organe, :groupe_sanguin, :hopital_source, :heure_clampage, :duree_viabilite, 'En cours', 1)
            ");
            $stmt->execute([
                ':code_greffon' => $code_greffon,
                ':donor_name' => $donneur_id,
                ':organe' => $organe,
                ':groupe_sanguin' => $groupe_sanguin,
                ':hopital_source' => $user_role, // Utilise le code hôpital (ex: HGY)
                ':heure_clampage' => $heure_clampage,
                ':duree_viabilite' => $duree_viabilite
            ]);

            $greffonId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at)
                VALUES (:user_id, :action, :details, NOW())
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':action' => 'Déclaration de prélèvement',
                ':details' => "Prélèvement déclaré: $code_greffon ($organe - $groupe_sanguin) de l'hôpital $user_name"
            ]);

            $pdo->commit();

            $message = "Prélèvement enregistré avec succès ! Code: " . $code_greffon;
            $message_type = 'success';

            // Redirection vers le fichier PHP dynamique
            header("Refresh: 2; url=dashboard_hop.php");
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déclarer un Prélèvement - BioÉthique CM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="prelevement.css">
    <link rel="stylesheet" href="navigation.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-hospital"></i>
            <span><?= htmlspecialchars($user_name) ?></span>
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard_hop.php" class="menu-item">
                <i class="fa-solid fa-desktop"></i> Console Hôpital
            </a>
            <a href="prelevement.php" class="menu-item active">
                <i class="fa-solid fa-circle-plus"></i> Déclarer un Prélèvement
            </a>
            <a href="receveur.php" class="menu-item">
                <i class="fa-solid fa-hospital-user"></i> Nos Receveurs en Attente
            </a>
            <a href="expedition.html" class="menu-item">
                <i class="fa-solid fa-truck-ramp-box"></i> Suivi de nos Expéditions
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-info">
                <p class="user-role">Coordination Intra-Hospitalière</p>
                <p class="user-name" id="hospital-email"><?php echo htmlspecialchars($user_email ?? 'coordination@hgy.cm'); ?></p>
            </div>
            <a href="connexion.html" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div>
                <h1>Déclarer un nouveau prélèvement</h1>
                <p class="subtitle">Enregistrement sécurisé d'un greffon disponible et validation du protocole éthique</p>
            </div>
            <div class="header-actions">
                <a href="dashboard_hop.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Retour à la console
                </a>
            </div>
        </header>

        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-section">
                    <h2><i class="fa-solid fa-user-injured"></i> 1. Identification du Donneur</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="donneur-id">Identifiant Anonymisé du Donneur *</label>
                            <input type="text" id="donneur-id" name="donneur_id" placeholder="Ex: DN-2026-88Y" required>
                        </div>
                        <div class="form-group">
                            <label for="groupe-sanguin">Groupe Sanguin *</label>
                            <select id="groupe-sanguin" name="groupe_sanguin" required>
                                <option value="">Sélectionnez</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2><i class="fa-solid fa-box-tissue"></i> 2. Infos du Greffon</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="organe">Organe prélevé *</label>
                            <select id="organe" name="organe" required>
                                <option value="">Sélectionnez</option>
                                <option value="Rein">Rein</option>
                                <option value="Foie">Foie</option>
                                <option value="Cœur">Cœur</option>
                                <option value="Poumon">Poumon</option>
                                <option value="Cornée">Cornée</option>
                                <option value="Moelle osseuse">Moelle osseuse</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="heure-clampage">Heure de clampage *</label>
                            <input type="datetime-local" id="heure-clampage" name="heure_clampage" required>
                        </div>
                        <div class="form-group">
                            <label for="duree-viabilite">Durée de viabilité (heures) *</label>
                            <input type="number" id="duree-viabilite" name="duree_viabilite" value="12" min="1" max="72" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2><i class="fa-solid fa-file-check"></i> 3. Validations</h2>
                    <div class="checkbox-group">
                        <input type="checkbox" id="protocole" name="protocole_accepte" required>
                        <label for="protocole">J'affirme que ce prélèvement respecte le protocole éthique et légal du Cameroun.</label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-cancel">Annuler</button>
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Enregistrer le prélèvement
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const hospitalName = urlParams.get('name');
            const hospitalNameDisplay = document.getElementById('hospital-name');
            
            if (hospitalName && hospitalNameDisplay) {
                hospitalNameDisplay.textContent = decodeURIComponent(hospitalName);
                localStorage.setItem('hospital_name', decodeURIComponent(hospitalName));
            }
        });
    </script>
    <script src="navigation.js"></script>
</body>
</html>
