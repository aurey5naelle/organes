<?php
session_start();
require_once 'db.php';

$error_message = null;
$success_message = null;

// --- Traitement du formulaire d'agrément ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_hopital') {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Nettoyage et récupération des entrées
        $nom = trim($_POST['nom'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $responsable = trim($_POST['responsable'] ?? '');
        $lits_usi = filter_var($_POST['lits_usi'] ?? 0, FILTER_VALIDATE_INT);
        $capacite_urgence = filter_var($_POST['capacite_urgence'] ?? 0, FILTER_VALIDATE_INT);
        $statut = trim($_POST['statut'] ?? 'actif');

        if (empty($nom) || empty($code)) {
            throw new Exception("Le nom et le code de l'hôpital sont obligatoires.");
        }

        // Requête d'insertion
        $sql_insert = "INSERT INTO hopitaux_partenaires 
                        (nom, code, adresse, telephone, email, responsable, lits_usi, capacite_urgence, statut) 
                       VALUES 
                        (:nom, :code, :adresse, :telephone, :email, :responsable, :lits_usi, :capacite_urgence, :statut)";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            ':nom' => $nom,
            ':code' => $code,
            ':adresse' => $adresse,
            ':telephone' => $telephone,
            ':email' => $email,
            ':responsable' => $responsable,
            ':lits_usi' => $lits_usi,
            ':capacite_urgence' => $capacite_urgence,
            ':statut' => $statut
        ]);

        $success_message = "L'hôpital $nom a été agréé avec succès !";

    } catch (Exception $e) {
        $error_message = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// --- Récupération des hôpitaux ---
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    $sql = "SELECT id, nom, code, adresse, telephone, email, responsable, lits_usi, capacite_urgence, statut 
            FROM hopitaux_partenaires 
            ORDER BY nom ASC";

    $stmt = $pdo->query($sql);
    $hopitaux = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Calcul des statistiques
    $total_hopitaux = count($hopitaux);
    $total_lits = array_sum(array_map('intval', array_column($hopitaux, 'lits_usi')));
    $total_capacite = array_sum(array_map('intval', array_column($hopitaux, 'capacite_urgence')));

} catch (Exception $e) {
    $hopitaux = [];
    $total_hopitaux = 0;
    $total_lits = 0;
    $total_capacite = 0;
    $error_message = "Erreur SQL : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hôpitaux Partenaires - BioÉthique CM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="partenaire.css">
    <link rel="stylesheet" href="navigation.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>BioÉthique CM</span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_organe.php" class="menu-item">
                <i class="fa-solid fa-chart-line"></i> Vue d'ensemble
            </a>
            <a href="add_organe.html" class="menu-item">
                <i class="fa-solid fa-dna"></i> Enregistrer un Organe
            </a>
            <a href="assigner_organe.html" class="menu-item">
                <i class="fa-solid fa-handshake"></i> Assigner Organe
            </a>
            <a href="partenaire.php" class="menu-item active">
                <i class="fa-solid fa-hospital"></i> Hôpitaux Partenaires
            </a>
            <a href="ethique.php" class="menu-item">
                <i class="fa-solid fa-scale-balanced"></i> Suivi Éthique
            </a>
            <a href="historique.php" class="menu-item">
                <i class="fa-solid fa-clock-rotate-left"></i> Historique / Traçabilité
            </a>
            <a href="consentements_organe.php" class="menu-item">
                <i class="fa-solid fa-file-signature"></i> Consentements Donneurs
            </a>
        </nav>

        <div class="sidebar-footer">
           <div class="user-info">
                <p class="user-role">Organe National</p>
                <p class="user-name" id="user-name-display">ethique.national@sante.gov</p>
            </div>
            <a href="connexion.html" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <div>
                <h1>Hôpitaux Partenaires</h1>
                <p class="subtitle">Réseau des établissements de santé partenaires du système national de transplantation</p>
            </div>
            <div class="header-actions">
                <!-- Bouton ouvrant la modale -->
                <button class="btn-primary-action" id="openAgrementModal">
                    <i class="fa-solid fa-plus"></i> Agréer un nouvel hôpital
                </button>
            </div>
        </header>

        <!-- Notifications -->
        <?php if ($error_message): ?>
            <div class="alert-error" style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert-success" style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <!-- Blocs Statistiques Partenaires -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-hospital"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($total_hopitaux) ?></h3>
                    <p>Total Hôpitaux</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fa-solid fa-bed"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($total_lits) ?></h3>
                    <p>Lits USI Disponibles</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-solid fa-kit-medical"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($total_capacite) ?></h3>
                    <p>Capacité d'Urgence Totale</p>
                </div>
            </div>
        </div>

        <!-- Tableau des Hôpitaux -->
        <div class="data-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-list"></i> Liste des Hôpitaux Partenaires</h2>
                <span class="badge"><?= htmlspecialchars($total_hopitaux) ?> hôpitaux au total</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom de l'Hôpital</th>
                            <th>Adresse</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Responsable</th>
                            <th>Lits USI</th>
                            <th>Capacité Urgence</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hopitaux)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px;">
                                    <i class="fa-solid fa-inbox"></i> Aucun hôpital partenaire enregistré
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($hopitaux as $hopital): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($hopital['code'] ?? '—') ?></strong></td>
                                    <td><?= htmlspecialchars($hopital['nom'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($hopital['adresse'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($hopital['telephone'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($hopital['email'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($hopital['responsable'] ?? '—') ?></td>
                                    <td><span class="badge-lits"><?= htmlspecialchars($hopital['lits_usi'] ?? 0) ?></span></td>
                                    <td><span class="badge-lits" style="background: #e8f5e9; color: #388e3c;"><?= htmlspecialchars($hopital['capacite_urgence'] ?? 0) ?></span></td>
                                    <td>
                                        <span class="status-badge <?= strtolower($hopital['statut'] ?? '') === 'actif' ? 'actif' : 'inactif' ?>">
                                            <?= strtolower($hopital['statut'] ?? '') === 'actif' ? '✓ Actif' : '✗ Inactif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-action">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modale de Formulaire d'Agrément -->
    <div id="agrementModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class="fa-solid fa-hospital-user"></i> Formulaire d'Agrément d'un Hôpital</h2>
                <button class="modal-close" id="closeAgrementModal">&times;</button>
            </div>
            <form action="partenaire.php" method="POST" class="modal-body">
                <input type="hidden" name="action" value="ajouter_hopital">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nom">Nom de l'Hôpital *</label>
                        <input type="text" id="nom" name="nom" required placeholder="ex: Hôpital Général de Douala">
                    </div>

                    <div class="form-group">
                        <label for="code">Code de l'établissement *</label>
                        <input type="text" id="code" name="code" required placeholder="ex: HGD-001">
                    </div>

                    <div class="form-group full-width">
                        <label for="adresse">Adresse complète</label>
                        <input type="text" id="adresse" name="adresse" placeholder="Quartier, Ville, Région">
                    </div>

                    <div class="form-group">
                        <label for="telephone">Numéro de Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" placeholder="+237 ...">
                    </div>

                    <div class="form-group">
                        <label for="email">Adresse Email</label>
                        <input type="email" id="email" name="email" placeholder="contact@hopital.cm">
                    </div>

                    <div class="form-group">
                        <label for="responsable">Responsable désigné</label>
                        <input type="text" id="responsable" name="responsable" placeholder="Dr. / Pr. Nom Prénom">
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut d'Agrément</label>
                        <select id="statut" name="statut">
                            <option value="actif" selected>Actif</option>
                            <option value="inactif">Inactif / Suspendu</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="lits_usi">Nombre de Lits USI</label>
                        <input type="number" id="lits_usi" name="lits_usi" min="0" value="0">
                    </div>

                    <div class="form-group">
                        <label for="capacite_urgence">Capacité d'Urgence</label>
                        <input type="number" id="capacite_urgence" name="capacite_urgence" min="0" value="0">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelAgrementModal">Annuler</button>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Enregistrer l'agrément</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 20px;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e3f2fd;
            border-radius: 12px;
            color: #1976d2;
            font-size: 28px;
        }

        .stat-icon.orange {
            background: #fff3e0;
            color: #f57c00;
        }

        .stat-icon.green {
            background: #e8f5e9;
            color: #388e3c;
        }

        .stat-data h3 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .stat-data p {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .data-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
            font-size: 13px;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.actif {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.inactif {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-lits {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }

        .btn-action {
            padding: 6px 12px;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .btn-action:hover {
            background: #1565c0;
        }

        /* Styles de la modale */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 650px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            font-size: 18px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
        }

        .modal-body {
            padding: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #1976d2;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
    </style>

    <script src="navigation.js"></script>
    <script>
        // Scripts de contrôle de la modale
        const modal = document.getElementById('agrementModal');
        const openBtn = document.getElementById('openAgrementModal');
        const closeBtn = document.getElementById('closeAgrementModal');
        const cancelBtn = document.getElementById('cancelAgrementModal');

        openBtn.addEventListener('click', () => {
            modal.classList.add('active');
        });

        const closeModal = () => {
            modal.classList.remove('active');
        };

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>