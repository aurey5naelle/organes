<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: connexion.html");
    exit();
}

try {
    // Récupérer tous les prélèvements déclarés (Correction de l'alias "g" dans la requête)
    $stmt = $pdo->query("
        SELECT g.id, g.code_greffon, g.donor_name, g.organe, g.groupe_sanguin, 
               g.hopital_source, g.heure_clampage, g.duree_viabilite_heures, 
               g.statut_validation, g.created_at
        FROM greffons g
        ORDER BY g.created_at DESC
    ");
    $prelevements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    // Statistiques des prélèvements
    $stats = [
        'total_prelevements'    => count($prelevements),
        'prelevements_valides'  => count(array_filter($prelevements, fn($p) => ($p['statut_validation'] ?? '') === 'Validé')),
        'prelevements_en_cours' => count(array_filter($prelevements, fn($p) => ($p['statut_validation'] ?? '') === 'En cours')),
        'prelevements_matches'  => count(array_filter($prelevements, fn($p) => ($p['statut_validation'] ?? '') === 'Matché'))
    ];

} catch (Exception $e) {
    $prelevements = [];
    $stats = [
        'total_prelevements'    => 0, 
        'prelevements_valides'  => 0, 
        'prelevements_en_cours' => 0, 
        'prelevements_matches'  => 0
    ];
}

function formaterViabilite($heureClamping, $dureeViabiliteHeures) {
    if (!$heureClamping || !$dureeViabiliteHeures) {
        return '—';
    }
    
    $deadline = strtotime($heureClamping) + ($dureeViabiliteHeures * 3600);
    $minutes = max(0, (int)(($deadline - time()) / 60));

    if ($minutes <= 0) {
        return '<span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Expiré</span>';
    }

    $heures = floor($minutes / 60);
    $mins = $minutes % 60;
    
    return sprintf('%dh %02dm', $heures, $mins);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - BioÉthique CM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="historique.css">
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
            <a href="assigner_organe.php" class="menu-item">
                <i class="fa-solid fa-handshake"></i> Assigner Organe
            </a>
            <a href="partenaire.php" class="menu-item">
                <i class="fa-solid fa-hospital"></i> Hôpitaux Partenaires
            </a>
            <a href="ethique.php" class="menu-item">
                <i class="fa-solid fa-scale-balanced"></i> Suivi Éthique
            </a>
            <a href="historique.php" class="menu-item active">
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
                <h1>Historique & Traçabilité des Prélèvements</h1>
                <p class="subtitle">Enregistrement complet de tous les prélèvements déclarés et paramètres de garde d'urgence</p>
            </div>
            <div class="header-actions">
                <button class="btn-secondary" onclick="window.print();">
                    <i class="fa-solid fa-print"></i> Exporter en PDF
                </button>
            </div>
        </header>

        <!-- Statistiques des Prélèvements -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($stats['total_prelevements']) ?></h3>
                    <p>Prélèvements Déclarés</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($stats['prelevements_valides']) ?></h3>
                    <p>Validés</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($stats['prelevements_en_cours']) ?></h3>
                    <p>En Cours</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fa-solid fa-handshake"></i>
                </div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($stats['prelevements_matches']) ?></h3>
                    <p>Matchés</p>
                </div>
            </div>
        </div>

        <!-- Tableau des Prélèvements -->
        <div class="data-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-table"></i> Registre Complet des Prélèvements</h2>
                <span class="badge"><?= htmlspecialchars($stats['total_prelevements']) ?> entrées</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Code Greffon</th>
                            <th>Donneur</th>
                            <th>Organe</th>
                            <th>Groupe Sanguin</th>
                            <th>Hôpital Source</th>
                            <th>Heure Clampage</th>
                            <th>Viabilité Restante</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prelevements)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px;">
                                    <i class="fa-solid fa-inbox"></i> Aucun prélèvement enregistré
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prelevements as $p): ?>
                                <tr>
                                    <td><small><?= !empty($p['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($p['created_at']))) : '—' ?></small></td>
                                    <td><strong><?= htmlspecialchars($p['code_greffon'] ?? '—') ?></strong></td>
                                    <td><?= htmlspecialchars($p['donor_name'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($p['organe'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($p['groupe_sanguin'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($p['hopital_source'] ?? '—') ?></td>
                                    <td><small><?= !empty($p['heure_clampage']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($p['heure_clampage']))) : '—' ?></small></td>
                                    <td><?= formaterViabilite($p['heure_clampage'] ?? null, $p['duree_viabilite_heures'] ?? null) ?></td>
                                    <td>
                                        <?php
                                            $statusClass = '';
                                            switch ($p['statut_validation'] ?? '') {
                                                case 'Validé': $statusClass = 'success'; break;
                                                case 'En cours': $statusClass = 'warning'; break;
                                                case 'Matché': $statusClass = 'info'; break;
                                                case 'Rejeté': $statusClass = 'danger'; break;
                                                default: $statusClass = 'secondary';
                                            }
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($p['statut_validation'] ?? '—') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paramètres de Garde d'Urgence -->
        <div class="data-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-user-gear"></i> Paramètres de Garde d'Urgence</h2>
            </div>

            <div class="garde-grid">
                <div class="garde-item">
                    <h3><i class="fa-solid fa-stethoscope"></i> Chirurgien d'Astreinte</h3>
                    <p class="garde-value">Dr. Amédée BIYA</p>
                    <small>Responsable des interventions d'urgence</small>
                </div>

                <div class="garde-item">
                    <h3><i class="fa-solid fa-phone"></i> Téléphone Coordination</h3>
                    <p class="garde-value">+237 693 12 34 56</p>
                    <small>Contact prioritaire 24/7</small>
                </div>

                <div class="garde-item">
                    <h3><i class="fa-solid fa-bed"></i> Capacité USI</h3>
                    <p class="garde-value">Disponible</p>
                    <small>Lits d'urgence en disponibilité</small>
                </div>

                <div class="garde-item">
                    <h3><i class="fa-solid fa-hourglass-end"></i> Dernière Mise à Jour</h3>
                    <p class="garde-value"><?= date('d/m/Y H:i') ?></p>
                    <small>Paramètres actualisés en temps réel</small>
                </div>
            </div>
        </div>
    </main>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e3f2fd;
            border-radius: 10px;
            color: #1976d2;
            font-size: 24px;
        }

        .stat-icon.green { background: #e8f5e9; color: #388e3c; }
        .stat-icon.warning { background: #fff3e0; color: #f57c00; }
        .stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }

        .stat-data h3 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .stat-data p {
            font-size: 12px;
            color: #666;
            margin: 5px 0 0 0;
        }

        .data-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .card-header h2 {
            font-size: 18px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
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
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
            font-size: 13px;
        }

        td {
            padding: 12px;
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

        .status-badge.success {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-badge.danger {
            background: #f8d7da;
            color: #721c24;
        }

        .garde-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .garde-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
        }

        .garde-item h3 {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .garde-value {
            font-size: 18px;
            font-weight: 700;
            margin: 10px 0;
        }

        .garde-item small {
            font-size: 12px;
            opacity: 0.9;
        }
    </style>
    <script src="navigation.js"></script>
</body>
</html>
