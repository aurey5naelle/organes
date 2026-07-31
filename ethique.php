<?php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: connexion.html");
    exit();
}

try {
    // Requête unifiée : Récupère également g.user_id / dc.user_id pour cibler le donneur
    $stmt = $pdo->query("
        SELECT 
            g.id AS source_id,
            g.user_id AS user_id,
            COALESCE(g.code_greffon, CONCAT('TX-', g.id)) AS code_dossier,
            g.organe AS organe,
            COALESCE(g.donor_name, 'Donneur Anonyme') AS nom_donneur,
            COALESCE(g.hopital_source, 'Hôpital Non Défini') AS hopital,
            COALESCE(g.statut_validation, 'En cours') AS statut_validation,
            g.created_at AS date_creation
        FROM greffons g

        UNION ALL

        SELECT 
            dc.id AS source_id,
            dc.user_id AS user_id,
            CONCAT('CARD-', dc.id) AS code_dossier,
            COALESCE(dc.consented_organs, 'Organe non spécifié') AS organe,
            CONCAT(dc.prenom, ' ', dc.nom) AS nom_donneur,
            'Enregistrement Ligne' AS hopital,
            'En cours' AS statut_validation,
            dc.updated_at AS date_creation
        FROM donor_cards dc
        WHERE dc.user_id NOT IN (SELECT user_id FROM greffons WHERE user_id IS NOT NULL)

        ORDER BY date_creation DESC
    ");
    $donneurs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    // Compteurs statistiques réels
    $stmt_encours = $pdo->query("SELECT COUNT(*) FROM greffons WHERE statut_validation = 'En cours' OR statut_validation IS NULL");
    $total_encours = (int)$stmt_encours->fetchColumn();

    $stmt_valides = $pdo->query("SELECT COUNT(*) FROM greffons WHERE statut_validation IN ('Validé', 'Approuvé par l\'État')");
    $total_valides = (int)$stmt_valides->fetchColumn();

    $stmt_matches = $pdo->query("SELECT COUNT(*) FROM greffons WHERE statut_validation IN ('Matché', 'Attribution en cours')");
    $total_matches = (int)$stmt_matches->fetchColumn();

} catch (Exception $e) {
    $error_msg = $e->getMessage();
    $donneurs = [];
    $total_encours = 0;
    $total_valides = 0;
    $total_matches = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi Éthique - BioÉthique CM</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="ethique.css">
    
    <style>
        .status-pill.en-cours { background-color: #fff3cd; color: #856404; }
        .status-pill.valide { background-color: #d4edda; color: #155724; }
        .status-pill.matche { background-color: #cce5ff; color: #004085; }
        .status-pill.regete { background-color: #f8d7da; color: #721c24; }
        
        .btn-action-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            margin-right: 3px;
            color: white;
            transition: opacity 0.2s;
        }
        .btn-action-sm:hover { opacity: 0.85; }
        .btn-convocate { background-color: #17a2b8; }

        /* Styles de la Modale */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-content-box {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            width: 420px;
            max-width: 90%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 5px; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;
        }
    </style>
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
                <i class="fa-solid fa-dna"></i> Enregistrer un Donneur
            </a>
            <a href="assigner_organe.php" class="menu-item">
                <i class="fa-solid fa-handshake"></i> Assigner Organe
            </a>
            <a href="partenaire.php" class="menu-item">
                <i class="fa-solid fa-hospital"></i> Hôpitaux Partenaires
            </a>
            <a href="ethique.php" class="menu-item active">
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
                <h1>Suivi Éthique & Processus d'Appariement</h1>
                <p class="subtitle">Gestion du consentement, convocation aux analyses et matching receveurs</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary-action" id="btn-audit">
                    <i class="fa-solid fa-shield-halved"></i> Audit de conformité
                </button>
            </div>
        </header>

        <?php if (isset($error_msg)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong>Erreur SQL :</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="stat-data">
                    <h3 id="stat-encours"><?= htmlspecialchars($total_encours) ?></h3>
                    <p>En cours (Analyses)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-data">
                    <h3 id="stat-valides"><?= htmlspecialchars($total_valides) ?></h3>
                    <p>Validés (Prêts)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fa-solid fa-people-arrows"></i>
                </div>
                <div class="stat-data">
                    <h3 id="stat-matches"><?= htmlspecialchars($total_matches) ?></h3>
                    <p>Organes Matchés</p>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="data-card" style="grid-column: span 2;">
                <div class="card-header">
                    <h2><i class="fa-solid fa-clipboard-list"></i> Pipeline d'Approbation et de Matching</h2>
                    <span class="badge badge-info">Mise à jour en direct</span>
                </div>

                <div class="table-container">
                    <table id="ethics-table">
                        <thead>
                            <tr>
                                <th>ID Dossier</th>
                                <th>Type d'Organe</th>
                                <th>Donneur / Hôpital</th>
                                <th>Consentement</th>
                                <th>Statut Éthique</th>
                                <th>Actions & Communication</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($donneurs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 20px;">
                                        Aucun enregistrement trouvé dans la base de données.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($donneurs as $d): ?>
                                    <tr>
                                        <td><strong>#<?= htmlspecialchars($d['code_dossier']) ?></strong></td>
                                        <td><?= htmlspecialchars($d['organe']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($d['nom_donneur']) ?> 
                                            <small style="color: #6c757d;">(<?= htmlspecialchars($d['hopital']) ?>)</small>
                                        </td>
                                        <td>
                                            <span class="time-limit safe" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-file-signature"></i> Signé (Légal)
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $statut = $d['statut_validation'];
                                                $class_statut = 'en-cours';
                                                if (in_array($statut, ['Validé', 'Approuvé par l\'État'])) $class_statut = 'valide';
                                                if (in_array($statut, ['Matché', 'Attribution en cours'])) $class_statut = 'matche';
                                                if ($statut === 'Rejeté') $class_statut = 'regete';
                                            ?>
                                            <span class="status-pill <?= $class_statut ?>">
                                                <?= htmlspecialchars($statut) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn-action-sm btn-convocate" onclick="ouvrirModalConvocation('<?= htmlspecialchars($d['code_dossier']) ?>', '<?= htmlspecialchars(addslashes($d['nom_donneur'])) ?>', '<?= htmlspecialchars($d['user_id'] ?? '') ?>')" title="Planifier RDV / SMS">
                                                <i class="fa-solid fa-calendar-plus"></i> Convoquer
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- FENÊTRE MODALE DE CONVOCATION -->
    <div class="modal-backdrop" id="modalConvocation">
        <div class="modal-content-box">
            <h3><i class="fa-solid fa-calendar-plus" style="color: #17a2b8;"></i> Nouvelles Convocations</h3>
            <p id="convocation-target" style="font-weight:600; color:#495057; margin-top:5px; margin-bottom:15px;"></p>
            
            <form id="formConvocation">
                <input type="hidden" name="code_dossier" id="modal_code_dossier">
                <input type="hidden" name="target_user_id" id="modal_user_id">
                
                <div class="form-group">
                    <label>Date et heure du rendez-vous :</label>
                    <input type="datetime-local" name="date_rdv" required>
                </div>
                
                <div class="form-group">
                    <label>Lieu / Hôpital :</label>
                    <input type="text" name="lieu" placeholder="Ex: Hôpital Central, Service d'Hématologie" required>
                </div>

                <div class="form-group">
                    <label>Instructions ou consignes :</label>
                    <textarea name="instructions" rows="3" placeholder="Ex: Se présenter à jeun muni d'une pièce d'identité..."></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" onclick="fermerModal()" style="padding:8px 15px; background:#6c757d; color:#fff; border:none; border-radius:4px; cursor:pointer;">Annuler</button>
                    <button type="submit" style="padding:8px 15px; background:#17a2b8; color:#fff; border:none; border-radius:4px; cursor:pointer;"><i class="fa-solid fa-paper-plane"></i> Enregistrer & Envoyer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function ouvrirModalConvocation(codeDossier, nomDonneur, userId) {
        document.getElementById('modal_code_dossier').value = codeDossier;
        document.getElementById('modal_user_id').value = userId;
        document.getElementById('convocation-target').innerText = "Dossier #" + codeDossier + " — Donneur : " + nomDonneur;
        document.getElementById('modalConvocation').style.display = 'flex';
    }

    function fermerModal() {
        document.getElementById('modalConvocation').style.display = 'none';
    }

    document.getElementById('formConvocation').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('enregistrer_convocation.php', {
            method: 'POST',
            body: formData
        })
        .then(async res => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                // Si PHP a renvoyé du HTML d'erreur au lieu d'un JSON valide
                throw new Error("Réponse serveur non-JSON : " + text);
            }
        })
        .then(data => {
            if (data.success) {
                alert('La convocation a été enregistrée avec succès !');
                fermerModal();
                this.reset();
            } else {
                alert('Erreur BDD/Logique : ' + data.message);
            }
        })
        .catch(err => {
            console.error('Détail Erreur:', err);
            alert('Erreur de communication :\n' + err.message);
        });
    });
</script>
    <script src="navigation.js"></script>
</body>
</html>
