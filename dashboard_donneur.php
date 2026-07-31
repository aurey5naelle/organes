<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Donneur';

// Redirection si l'utilisateur n'est pas connecté
if (!$user_id) {
    header("Location: connexion.html");
    exit();
}

// 1. Récupération de la carte de donneur
$stmt = $pdo->prepare("
    SELECT prenom, nom, birth_year, blood_group, consented_organs
    FROM donor_cards
    WHERE user_id = :user_id
    LIMIT 1
");
$stmt->execute([':user_id' => $user_id]);
$donor_card = $stmt->fetch();

// Sécurisation si la carte n'existe pas encore
if (!$donor_card) {
    $space_pos = strpos(trim($user_name), ' ');
    $donor_card = [
        'prenom' => $space_pos !== false ? substr($user_name, 0, $space_pos) : $user_name,
        'nom' => $space_pos !== false ? substr($user_name, $space_pos + 1) : '',
        'birth_year' => null,
        'blood_group' => null,
        'consented_organs' => ''
    ];
}

// 2. Compte des greffons/organes associés (Sécurisé avec fetchColumn)
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM greffons 
    WHERE (user_id = :user_id OR donor_name = :donor_name) 
      AND statut_validation != 'Rejeté'
");
$stmt->execute([
    ':user_id' => $user_id,
    ':donor_name' => $user_name
]);
$my_organes = (int) $stmt->fetchColumn();

// 3. Récupération des derniers logs d'activité
$stmt = $pdo->prepare("
    SELECT action, details, created_at 
    FROM activity_logs
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([':user_id' => $user_id]);
$activities = $stmt->fetchAll();

// 4. Statut de consentement
$stmt = $pdo->prepare("
    SELECT date_consente 
    FROM donor_consentements
    WHERE user_id = :user_id
    LIMIT 1
");
$stmt->execute([':user_id' => $user_id]);
$consent = $stmt->fetch();

// 5. Convocation médicale
$convocation = null;
try {
    $stmt = $pdo->prepare("
        SELECT date_rdv, lieu, statut, instructions 
        FROM convocations 
        WHERE user_id = :user_id 
        ORDER BY date_rdv DESC 
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $user_id]);
    $convocation = $stmt->fetch();
} catch (PDOException $e) {
    $convocation = null;
}

// Préparation des variables d'affichage
$nomComplet = trim(($donor_card['prenom'] ?? '') . ' ' . ($donor_card['nom'] ?? ''));
$raw_organs = trim($donor_card['consented_organs'] ?? '');
$countOrgansToGive = $raw_organs !== '' ? count(array_filter(explode(',', $raw_organs))) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Donneur - BioÉthique CM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard_donneur.css">
    <link rel="stylesheet" href="navigation.css">
</head>
<body>

    <!-- Barre latérale -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>BioÉthique CM</span>
        </div>
        
        <nav class="sidebar-menu">
            <a href="dashboard_donneur.php" class="menu-item active">
                <i class="fa-solid fa-chart-line"></i> Vue d'ensemble
            </a>
            <a href="carte_donneur.php" class="menu-item">
                <i class="fa-solid fa-id-card"></i> Ma Carte de Donneur
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <p class="user-role">Donneur</p>
                <p class="user-mail"><?= htmlspecialchars($_SESSION['user_email'] ?? $user_name) ?></p>
            </div>
            <!-- Modifié vers deconnexion.php pour détruire la session correctement -->
            <a href="deconnexion.php" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
            </a>
        </div>
    </aside>

    <!-- Contenu principal -->
    <main class="main-content">
        
        <header class="main-header">
            <div>
                <h1>Bienvenue, <?= htmlspecialchars($donor_card['prenom'] ?? 'Donneur') ?></h1>
                <p class="subtitle">Votre statut de donneur est actif et suivi sur le réseau BioÉthique CM.</p>
            </div>
            <div class="header-actions" style="margin-left: auto;">
                <button class="btn-primary" onclick="window.print();">
                    <i class="fa-solid fa-download"></i> Imprimer / PDF
                </button>
            </div>
        </header>

        <!-- Cartes de statistiques (KPIs) -->
        <div class="stats-grid">
            <!-- Statut Consentement -->
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= $consent ? 'Validé' : 'En attente' ?></span>
                    <span class="stat-label">Statut Consentement</span>
                </div>
            </div>

            <!-- Groupe Sanguin -->
            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fa-solid fa-droplet"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= htmlspecialchars($donor_card['blood_group'] ?? '—') ?></span>
                    <span class="stat-label">Groupe Sanguin</span>
                </div>
            </div>

            <!-- Organes à Donner -->
            <div class="stat-card">
                <div class="stat-icon icon-purple">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>
                <div class="stat-data">
                    <span class="stat-value"><?= sprintf('%02d', $countOrgansToGive) ?></span>
                    <span class="stat-label">Organes à Donner</span>
                </div>
            </div>
        </div>

        <!-- Section 2 Colonnes -->
        <div class="grid-two-columns">
            
            <!-- Carte Numérique -->
            <div class="data-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-address-card"></i> Votre Carte Numérique</h2>
                    <span class="priority-badge success">Officielle</span>
                </div>
                
                <div class="card-display-wrapper">
                    <div class="donor-digital-card">
                        <div class="card-top-bar">
                            <span class="country-name"><i class="fa-solid fa-heart-pulse"></i> BIOÉTHIQUE CAMEROUN</span>
                            <span class="card-title-tag">CARTE DE DONNEUR</span>
                        </div>

                        <div class="card-middle-row">
                            <div class="card-chip"></div>
                            <i class="fa-solid fa-shield-heart emergency-icon"></i>
                        </div>

                        <div class="card-body-details">
                            <div class="card-meta">
                                <span class="card-label">Titulaire</span>
                                <span class="card-value"><?= strtoupper(htmlspecialchars($nomComplet ?: 'NON RENSEIGNÉ')) ?></span>
                            </div>
                            
                            <div class="card-meta-row">
                                <div class="card-meta">
                                    <span class="card-label">Né(e) en</span>
                                    <span class="card-value"><?= htmlspecialchars($donor_card['birth_year'] ?? '-') ?></span>
                                </div>
                                <div class="card-meta">
                                    <span class="card-label">Groupe Sanguin</span>
                                    <span class="card-value"><?= htmlspecialchars($donor_card['blood_group'] ?? '-') ?></span>
                                </div>
                            </div>

                            <div class="card-meta">
                                <span class="card-label">Organes consentis</span>
                                <span class="card-value txt-small"><?= htmlspecialchars($donor_card['consented_organs'] ?: 'Aucun') ?></span>
                            </div>

                            <div class="card-meta">
                                <span class="card-label">Email</span>
                                <span class="card-value txt-small"><?= htmlspecialchars($_SESSION['user_email'] ?? '—') ?></span>
                            </div>
                        </div>

                        <div class="card-watermark">
                            <i class="fa-solid fa-heart-pulse"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Directives & Statut Consentement -->
            <div class="data-card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-file-signature"></i> Information & Consentement</h2>
                </div>
                
                <div class="info-content-stack">
                    <div class="info-box-status">
                        <i class="fa-solid fa-scale-balanced icon-status"></i>
                        <div>
                            <h4>Loi sur le consentement présumé</h4>
                            <p>Votre carte fait foi de preuve directe. Elle évite tout doute ou conflit familial au sein du corps médical lors d'une urgence critique.</p>
                        </div>
                    </div>

                    <div class="trusted-person-box">
                        <div class="trusted-header">
                            <h5>État du consentement</h5>
                            <?php if ($consent): ?>
                                <span class="priority-badge success">Validé</span>
                            <?php else: ?>
                                <span class="priority-badge info" style="background-color: #ffc107; color: #000;">En attente</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($consent): ?>
                            <p class="trusted-name"><i class="fa-solid fa-circle-check text-success"></i> Consentement Officiel</p>
                            <p class="trusted-note">Validé le <?= date('d/m/Y à H:i', strtotime($consent['date_consente'])) ?></p>
                            <ul style="margin-top: 10px; padding-left: 20px; font-size: 0.85rem; color: #555;">
                                <li>Libre volonté confirmée</li>
                                <li>Examens médicaux acceptés</li>
                                <li>Conditions légales acceptées</li>
                            </ul>
                        <?php else: ?>
                            <p class="trusted-note" style="margin: 10px 0;">Vous n'avez pas encore validé formellement votre document de consentement.</p>
                            <a href="consentement_donneur.php" class="btn-primary" style="display: inline-block; text-align: center;">
                                <i class="fa-solid fa-file-signature"></i> Valider mon consentement
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Bloc Convocation Médicale -->
        <div class="data-card" style="margin-top: 24px;">
            <div class="card-header">
                <h2><i class="fa-solid fa-calendar-check"></i> Convocation Médicale / Rendez-vous</h2>
                <?php if ($convocation): ?>
                    <span class="priority-badge info"><?= htmlspecialchars($convocation['statut'] ?? 'Planifié') ?></span>
                <?php endif; ?>
            </div>

            <div class="card-body" style="padding: 20px;">
                <?php if ($convocation): ?>
                    <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <div style="font-size: 2rem; color: #2563eb; text-align: center; min-width: 80px;">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 8px 0; font-size: 1.1rem; color: #1e293b;">
                                Rendez-vous médical pré-don
                            </h4>
                            <p style="margin: 4px 0; color: #64748b; font-size: 0.95rem;">
                                <i class="fa-solid fa-calendar-day" style="margin-right: 6px;"></i>
                                <strong>Date & Heure :</strong> <?= date('d/m/Y à H:i', strtotime($convocation['date_rdv'])) ?>
                            </p>
                            <p style="margin: 4px 0; color: #64748b; font-size: 0.95rem;">
                                <i class="fa-solid fa-location-dot" style="margin-right: 6px;"></i>
                                <strong>Lieu :</strong> <?= htmlspecialchars($convocation['lieu'] ?? 'Établissement Médical') ?>
                            </p>
                            <?php if (!empty($convocation['instructions'])): ?>
                                <p style="margin: 8px 0 0 0; background-color: #f8fafc; padding: 10px; border-left: 3px solid #2563eb; border-radius: 4px; font-size: 0.88rem; color: #475569;">
                                    <i class="fa-solid fa-circle-info" style="margin-right: 4px;"></i>
                                    <strong>Instructions :</strong> <?= htmlspecialchars($convocation['instructions']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #64748b; margin: 10px 0;">
                        <i class="fa-solid fa-calendar-xmark" style="margin-right: 8px;"></i>
                        Aucune convocation médicale enregistrée pour le moment.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tableau d'activité -->
        <div class="data-card table-card" style="margin-top: 24px;">
            <div class="card-header">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> Journal d'activité</h2>
                <span class="badge-security"><i class="fa-solid fa-lock"></i> Chiffré</span>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date / Heure</th>
                            <th>Action</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activities)): ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td><strong><?= date('d/m/Y à H:i', strtotime($activity['created_at'])) ?></strong></td>
                                    <td><?= htmlspecialchars($activity['action']) ?></td>
                                    <td><?= htmlspecialchars($activity['details'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align: center;">Aucune activité récente.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script src="navigation.js"></script>
</body>
</html>
