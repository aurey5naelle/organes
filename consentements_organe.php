<?php
session_start();
require_once 'db.php';

try {
    $stmt = $pdo->prepare("SELECT dc.id, dc.user_id,
        COALESCE(dc.date_consente, dc.date_consentement) AS date_consente,
        COALESCE(dc.consentement_free, dc.consentement_livre, 0) AS consentement_free,
        COALESCE(dc.consentement_medical, 0) AS consentement_medical,
        COALESCE(dc.consentement_legal, 0) AS consentement_legal,
        u.email
        FROM donor_consentements dc
        LEFT JOIN users u ON u.id = dc.user_id
        ORDER BY dc.id DESC");
    $stmt->execute();
    $consents = $stmt->fetchAll();
} catch (Exception $e) {
    $consents = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consentements des Donneurs - BioÉthique CM</title>
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
            <a href="dashboard_organe.html" class="menu-item">
                <i class="fa-solid fa-chart-line"></i> Vue d'ensemble
            </a>
            <a href="add_organe.html" class="menu-item">
                <i class="fa-solid fa-dna"></i> Enregistrer un Organe
            </a>
            <a href="partenaire.php" class="menu-item">
                <i class="fa-solid fa-hospital"></i> Hôpitaux Partenaires
            </a>
            <a href="ethique.php" class="menu-item">
                <i class="fa-solid fa-scale-balanced"></i> Suivi Éthique
            </a>
            <a href="historique.php" class="menu-item">
                <i class="fa-solid fa-clock-rotate-left"></i> Historique / Traçabilité
            </a>
            <a href="consentements_organe.php" class="menu-item active">
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
                <h1>Consentements des donneurs</h1>
                <p class="subtitle">Liste des consentements soumis et enregistrés comme preuve éthique.</p>
            </div>
        </header>

        <section class="data-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-file-signature"></i> Recensement des consentements</h2>
                <span class="badge badge-info"><?= count($consents) ?> enregistrements</span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Donneur</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Libre volonté</th>
                            <th>Examen médical</th>
                            <th>Cadre légal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($consents)): ?>
                            <tr><td colspan="7">Aucun consentement enregistré pour le moment.</td></tr>
                        <?php else: ?>
                            <?php foreach ($consents as $row): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($row['id']) ?></td>
                                    <td>Donneur #<?= htmlspecialchars($row['user_id'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($row['date_consente'] ?? '—') ?></td>
                                    <td><?= !empty($row['consentement_free']) ? 'Oui' : 'Non' ?></td>
                                    <td><?= !empty($row['consentement_medical']) ? 'Oui' : 'Non' ?></td>
                                    <td><?= !empty($row['consentement_legal']) ? 'Oui' : 'Non' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script src="navigation.js"></script>
</body>
</html>
