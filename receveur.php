<?php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Hôpital';
$user_email = $_SESSION['user_email'] ?? 'coordination@hgy.cm';

if (!$user_id) {
    header("Location: connexion.html");
    exit();
}

// Récupération des messages stockés en session (si redirection après POST)
$message = $_SESSION['flash_message'] ?? '';
$message_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_patient') {
        $code_patient = trim($_POST['code_patient'] ?? '');
        $nom_patient = trim($_POST['nom_patient'] ?? '');
        $type_organe = trim($_POST['type_organe'] ?? '');
        $groupe_sanguin = trim($_POST['groupe_sanguin'] ?? '');
        $score_urgence = (int)($_POST['score_urgence'] ?? 0);

        if (empty($code_patient) || empty($nom_patient) || empty($type_organe) || empty($groupe_sanguin) || $score_urgence <= 0) {
            $message = "Veuillez remplir tous les champs obligatoires.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO liste_attente 
                    (code_patient, nom_patient, type_organe_requis, groupe_sanguin, score_urgence, statut)
                    VALUES (:code, :nom, :organe, :groupe, :urgence, 'en_attente')
                ");
                $stmt->execute([
                    ':code' => $code_patient,
                    ':nom' => $nom_patient,
                    ':organe' => $type_organe,
                    ':groupe' => $groupe_sanguin,
                    ':urgence' => $score_urgence
                ]);

                $stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, created_at)
                    VALUES (:user_id, :action, :details, NOW())
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':action' => 'Inscription patient receveur',
                    ':details' => "Patient $nom_patient ($code_patient) inscrit pour $type_organe, score urgence: $score_urgence"
                ]);

                // Redirection PRG pour éviter le rechargement de formulaire
                $_SESSION['flash_message'] = "Patient inscrit avec succès !";
                $_SESSION['flash_type'] = 'success';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();

            } catch (Exception $e) {
                $message = "Erreur : " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Récupération de la liste des patients
try {
    $stmt = $pdo->query("
        SELECT * FROM liste_attente
        ORDER BY score_urgence DESC
        LIMIT 20
    ");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
} catch (Exception $e) {
    $patients = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Receveurs - BioÉthique CM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="receveur.css">
    <link rel="stylesheet" href="navigation.css">
    <style>
        /* Overlay sombre et flouté */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        /* Masquage complet de la modale */
        .modal-overlay.hidden {
            display: none !important;
        }

        /* Modale centrée au-dessus de tout le contenu */
        .modal-overlay .form-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 650px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
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
            <a href="prelevement.php" class="menu-item">
                <i class="fa-solid fa-circle-plus"></i> Déclarer un Prélèvement
            </a>
            <a href="receveur.php" class="menu-item active">
                <i class="fa-solid fa-hospital-user"></i> Nos Receveurs en Attente
            </a>
            <a href="expedition.html" class="menu-item">
                <i class="fa-solid fa-truck-ramp-box"></i> Suivi de nos Expéditions
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <p class="user-role">Coordination Intra-Hospitalière</p>
                <p class="user-name" id="hospital-email"><?= htmlspecialchars($user_email) ?></p>
            </div>
            <a href="connexion.html" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div>
                <h1>Nos Receveurs en Attente</h1>
                <p class="subtitle">Suivi clinique des patients inscrits sur la Liste Nationale d'Attente</p>
            </div>
            <div class="header-actions">
                <button id="btn-toggle-form" class="btn-primary-action">
                    <i class="fa-solid fa-user-plus"></i> Inscrire un receveur
                </button>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- CONTENEUR MODALE (FOND FLOUTÉ + FORMULAIRE) -->
        <div class="modal-overlay hidden" id="modal-overlay">
            <div class="form-card" id="form-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-folder-open"></i> Nouvelle inscription</h2>
                    <button type="button" id="btn-close-form" class="btn-close">&times;</button>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_patient">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="code-patient">Code Patient *</label>
                            <input type="text" id="code-patient" name="code_patient" placeholder="P-001" required>
                        </div>
                        <div class="form-group">
                            <label for="nom-patient">Nom du Patient *</label>
                            <input type="text" id="nom-patient" name="nom_patient" placeholder="Jean Dupont" required>
                        </div>
                        <div class="form-group">
                            <label for="type-organe">Organe Requis *</label>
                            <select id="type-organe" name="type_organe" required>
                                <option value="">Sélectionnez</option>
                                <option value="Rein">Rein</option>
                                <option value="Foie">Foie</option>
                                <option value="Cœur">Cœur</option>
                                <option value="Poumon">Poumon</option>
                                <option value="Cornée">Cornée</option>
                            </select>
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
                        <div class="form-group">
                            <label for="urgence">Score Urgence (0-100) *</label>
                            <input type="number" id="urgence" name="score_urgence" min="1" max="100" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">Inscrire le patient</button>
                </form>
            </div>
        </div>

        <div class="data-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-list"></i> Liste des Patients en Attente</h2>
                <span class="badge"><?= count($patients) ?> patients</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Organe Requis</th>
                            <th>Groupe Sanguin</th>
                            <th>Urgence</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($patient['code_patient']) ?></strong></td>
                                <td><?= htmlspecialchars($patient['nom_patient']) ?></td>
                                <td><?= htmlspecialchars($patient['type_organe_requis']) ?></td>
                                <td><?= htmlspecialchars($patient['groupe_sanguin']) ?></td>
                                <td><span class="urgence-badge"><?= htmlspecialchars($patient['score_urgence']) ?>/100</span></td>
                                <td>
                                    <span class="status-badge <?= $patient['statut'] === 'en_attente' ? 'attente' : 'transplante' ?>">
                                        <?= $patient['statut'] === 'en_attente' ? 'En attente' : 'Transplanté' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const btnToggle = document.getElementById('btn-toggle-form');
            const btnClose = document.getElementById('btn-close-form');
            const modalOverlay = document.getElementById('modal-overlay');

            // Ouvrir la modale (et le flou d'arrière-plan)
            if (btnToggle && modalOverlay) {
                btnToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    modalOverlay.classList.remove('hidden');
                });
            }

            // Fermer avec la croix
            if (btnClose && modalOverlay) {
                btnClose.addEventListener('click', function() {
                    modalOverlay.classList.add('hidden');
                });
            }

            // Fermer si l'utilisateur clique sur la zone floutée à l'extérieur du formulaire
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === modalOverlay) {
                        modalOverlay.classList.add('hidden');
                    }
                });
            }
        });
    </script>
    <script src="navigation.js"></script>
</body>
</html>