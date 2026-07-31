<?php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Organe National';

if (!$user_id) {
    header("Location: connexion.html");
    exit();
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM greffons WHERE statut_validation != 'Rejeté'");
    $total_organes = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM alertes WHERE statut = 'active'");
    $total_alertes = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(DISTINCT hopital_source) AS total FROM greffons WHERE COALESCE(hopital_source, '') <> ''");
    $total_hopitaux = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM donor_consentements");
    $total_consentements = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $pdo->query("
        SELECT g.id, g.code_greffon, g.donor_name, g.organe, g.hopital_source, g.compatibilites_count, 
               g.heure_clampage, g.duree_viabilite_heures, g.statut_validation,
               COUNT(DISTINCT CASE WHEN l.statut = 'en_attente' AND l.type_organe_requis = g.organe THEN l.id END) AS pending_matches
        FROM greffons g
        LEFT JOIN liste_attente l ON l.type_organe_requis = g.organe AND l.statut = 'en_attente'
        GROUP BY g.id
        ORDER BY g.created_at DESC 
        LIMIT 6
    ");
    $organes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    $stmt = $pdo->query("SELECT action, details, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

} catch (Exception $e) {
    $organes = [];
    $logs = [];
    $total_organes = 0;
    $total_alertes = 0;
    $total_hopitaux = 0;
    $total_consentements = 0;
}

function formaterViabilite($heureClamping, $dureeViabiliteHeures) {
    if (!$heureClamping || !$dureeViabiliteHeures) {
        return '<span class="time-limit safe">—</span>';
    }
    
    $deadline = strtotime($heureClamping) + ($dureeViabiliteHeures * 3600);
    $minutes = max(0, (int)(($deadline - time()) / 60));

    if ($minutes <= 0) {
        return '<span class="time-limit danger">Expiré</span>';
    }

    $heures = floor($minutes / 60);
    $mins = $minutes % 60;
    $class = 'safe';

    if ($heures < 3) {
        $class = 'danger';
    } elseif ($heures < 6) {
        $class = 'warning';
    }

    return sprintf('<span class="time-limit %s">%dh %02dm</span>', $class, $heures, $mins);
}

function formaterStatut($statut, $pendingMatches, $compatibilitesCount) {
    $hasRealMatch = ((int)$pendingMatches > 0) && ((int)$compatibilitesCount > 0);

    if ($hasRealMatch) {
        return '<span class="status-pill success">Matché</span>';
    }

    switch ($statut) {
        case 'Rejeté':
            return '<span class="status-pill danger">Rejeté</span>';
        case 'Attribution en cours':
            return '<span class="status-pill warning">En cours</span>';
        default:
            return '<span class="status-pill warning">' . htmlspecialchars($statut, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

function tempsEcoule($datetime) {
    if (!$datetime) return '';
    
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "À l'instant";
    }
    if ($diff < 3600) {
        return 'Il y a ' . floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return 'Il y a ' . floor($diff / 3600) . ' heure(s)';
    }

    return 'Il y a ' . floor($diff / 86400) . ' jour(s)';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Organe National d'Éthique</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard_organe.css">
    <link rel="stylesheet" href="navigation.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>BioÉthique CM</span>
        </div>

        <nav class="sidebar-menu">
            <a href="dashboard_organe.php" class="menu-item active">
                <i class="fa-solid fa-chart-line"></i> Vue d'ensemble
            </a>
            <a href="add_organe.html" class="menu-item">
                <i class="fa-solid fa-dna"></i> Enregistrer un Organe
            </a>
            <a href="assigner_organe.html" class="menu-item">
                <i class="fa-solid fa-handshake"></i> Assigner un Organe
            </a>
            <a href="partenaire.html" class="menu-item">
                <i class="fa-solid fa-hospital"></i> Hôpitaux Partenaires
            </a>
            <a href="ethique.php" class="menu-item">
                <i class="fa-solid fa-scale-balanced"></i> Suivi Éthique
            </a>
            <a href="historique.html" class="menu-item">
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
                <h1>Tableau de Bord Centralisé</h1>
                <p class="subtitle">Régulation, compatibilité et éthique des transplantations au Cameroun</p>
            </div>
            <div class="header-actions">
                <a href="add_organe.html" class="btn-primary-action">
                    <i class="fa-solid fa-plus"></i> Déclarer un nouvel organe
                </a>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-box-tissue"></i></div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($total_organes, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p>Organes Disponibles</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-hospital-user"></i></div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($total_hopitaux, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p>Hôpitaux Actifs</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning"><i class="fa-solid fa-file-signature"></i></div>
                <div class="stat-data">
                    <h3><?= htmlspecialchars($total_consentements, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p>Consentements Enregistrés</p>
                </div>
            </div>
        </section>

        <section class="content-grid">
            <div class="data-card organes-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-dna"></i> Suivi des Organes Disponibles</h2>
                    <span class="badge badge-info">Données en temps réel</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Code Greffon</th>
                                <th>Donneur</th>
                                <th>Organe</th>
                                <th>Compatibilités</th>
                                <th>Viabilité Restante</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($organes)): ?>
                                <tr><td colspan="6">Aucune donnée disponible pour le moment.</td></tr>
                            <?php else: ?>
                                <?php foreach ($organes as $organe): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($organe['code_greffon'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars($organe['donor_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><strong><?= htmlspecialchars($organe['organe'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><span class="match-count"><?= htmlspecialchars($organe['compatibilites_count'] ?? 0, ENT_QUOTES, 'UTF-8') ?> receveur(s)</span></td>
                                        <td><?= formaterViabilite($organe['heure_clampage'] ?? null, (int)($organe['duree_viabilite_heures'] ?? 12)) ?></td>
                                        <td><?= formaterStatut($organe['statut_validation'] ?? 'En cours', $organe['pending_matches'] ?? 0, $organe['compatibilites_count'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="data-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-list-check"></i> Activités & Traçabilité</h2>
                </div>
                <div class="timeline">
                    <?php if (empty($logs)): ?>
                        <div class="timeline-item">
                            <div class="timeline-badge blue"><i class="fa-solid fa-info-circle"></i></div>
                            <div class="timeline-content">
                                <h4>Aucune activité disponible</h4>
                                <p>Les prochaines actions apparaîtront ici automatiquement.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-badge green"><i class="fa-solid fa-paper-plane"></i></div>
                                <div class="timeline-content">
                                    <h4><?= htmlspecialchars($log['action'] ?? 'Activité', ENT_QUOTES, 'UTF-8') ?></h4>
                                    <p><?= htmlspecialchars($log['details'] ?? 'Aucun détail', ENT_QUOTES, 'UTF-8') ?></p>
                                    <span class="timeline-time"><?= htmlspecialchars(tempsEcoule($log['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <script src="navigation.js"></script>
</body>
</html>
