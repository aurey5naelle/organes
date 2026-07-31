<?php
session_start();

require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Donneur';
$user_email = $_SESSION['user_email'] ?? '';

if (!$user_id) {
    header("Location: connexion.html");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consent_free = isset($_POST['consent_free']) ? 1 : 0;
    $consent_medical = isset($_POST['consent_medical']) ? 1 : 0;
    $consent_legal = isset($_POST['consent_legal']) ? 1 : 0;
    
    // Récupération sécurisée du tableau d'organes
    $organs_array = isset($_POST['organs_consent']) && is_array($_POST['organs_consent']) ? $_POST['organs_consent'] : [];
    $organs_consent = implode(', ', $organs_array);

    if ($consent_free && $consent_medical && $consent_legal) {
        try {
            $pdo->beginTransaction();

            // 1. Enregistrement ou mise à jour des consentements (VALUES(...) résout le bug HY093)
            $stmt = $pdo->prepare("
                INSERT INTO donor_consentements (user_id, consentement_free, consentement_medical, consentement_legal, date_consente)
                VALUES (:user_id, :free, :medical, :legal, NOW())
                ON DUPLICATE KEY UPDATE
                    consentement_free = VALUES(consentement_free),
                    consentement_medical = VALUES(consentement_medical),
                    consentement_legal = VALUES(consentement_legal),
                    date_consente = NOW()
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':free' => $consent_free,
                ':medical' => $consent_medical,
                ':legal' => $consent_legal
            ]);

            // 2. Insertion ou Mise à jour de la carte de donneur
            $stmt = $pdo->prepare("
                INSERT INTO donor_cards (user_id, consented_organs)
                VALUES (:user_id, :organs)
                ON DUPLICATE KEY UPDATE
                    consented_organs = VALUES(consented_organs)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':organs' => $organs_consent
            ]);

            // 3. Journal d'activités
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at)
                VALUES (:user_id, :action, :details, NOW())
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':action' => 'Consentement de don validé',
                ':details' => "Le donneur a validé son consentement pour les organes: $organs_consent"
            ]);

            $pdo->commit();

            $_SESSION['consentement_valide'] = 1;
            $_SESSION['organs_consented'] = $organs_consent;

            // Redirection vers la carte de donneur
            header("Location: carte_donneur.php");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur lors de l'enregistrement : " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Vous devez cocher tous les consentements obligatoires.";
        $message_type = 'error';
    }
}

// Récupération des données existantes
$stmt = $pdo->prepare("SELECT * FROM donor_consentements WHERE user_id = :user_id LIMIT 1");
$stmt->execute([':user_id' => $user_id]);
$existing_consent = $stmt->fetch();

$stmt = $pdo->prepare("SELECT consented_organs FROM donor_cards WHERE user_id = :user_id LIMIT 1");
$stmt->execute([':user_id' => $user_id]);
$card = $stmt->fetch();
$consented_organs = $card['consented_organs'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consentement de Don - BioÉthique CM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="consentement_donneur.css">
    
</head>
<body>

    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-file-signature"></i> Consentement de Don d'Organe</h1>
            <p>Bienvenue <?= htmlspecialchars($user_name) ?></p>
        </div>

        <?php if ($message): ?>
            <div class="message show <?= $message_type ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong><i class="fa-solid fa-info-circle"></i> Important :</strong> En validant votre consentement, vous acceptez de participer au programme national de don d'organes et de tissus dans le respect de la loi camerounaise.
        </div>

        <form method="POST" action="">
            <!-- Section: Choix des Organes -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fa-solid fa-heart"></i> Sélectionnez les organes que vous souhaitez donner
                </h3>

                <div class="organs-grid">
                    <div class="organ-option">
                        <input type="checkbox" id="organ-rein" name="organs_consent[]" value="Rein"
                            <?= strpos($consented_organs, 'Rein') !== false ? 'checked' : '' ?>>
                        <label for="organ-rein">Rein</label>
                    </div>
                    <div class="organ-option">
                        <input type="checkbox" id="organ-foie" name="organs_consent[]" value="Foie"
                            <?= strpos($consented_organs, 'Foie') !== false ? 'checked' : '' ?>>
                        <label for="organ-foie">Foie</label>
                    </div>
                    <div class="organ-option">
                        <input type="checkbox" id="organ-coeur" name="organs_consent[]" value="Cœur"
                            <?= strpos($consented_organs, 'Cœur') !== false ? 'checked' : '' ?>>
                        <label for="organ-coeur">Cœur</label>
                    </div>
                    <div class="organ-option">
                        <input type="checkbox" id="organ-poumon" name="organs_consent[]" value="Poumon"
                            <?= strpos($consented_organs, 'Poumon') !== false ? 'checked' : '' ?>>
                        <label for="organ-poumon">Poumon</label>
                    </div>
                    <div class="organ-option">
                        <input type="checkbox" id="organ-cornee" name="organs_consent[]" value="Cornée"
                            <?= strpos($consented_organs, 'Cornée') !== false ? 'checked' : '' ?>>
                        <label for="organ-cornee">Cornée</label>
                    </div>
                    <div class="organ-option">
                        <input type="checkbox" id="organ-moelle" name="organs_consent[]" value="Moelle osseuse"
                            <?= strpos($consented_organs, 'Moelle osseuse') !== false ? 'checked' : '' ?>>
                        <label for="organ-moelle">Moelle osseuse</label>
                    </div>
                </div>
            </div>

            <!-- Section: Consentements Obligatoires -->
            <div class="form-section">
                <h3 class="section-title">
                    <i class="fa-solid fa-check-circle"></i> Vérifications et Consentements
                </h3>

                <div class="checkbox-group">
                    <input type="checkbox" id="consent-free" name="consent_free" required
                        <?= ($existing_consent && $existing_consent['consentement_free']) ? 'checked' : '' ?>>
                    <label for="consent-free" class="checkbox-label">
                        <strong>Libre volonté :</strong> Je confirme que ce don est effectué de manière entièrement libre, sans aucune pression physique, morale ou financière.
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="consent-medical" name="consent_medical" required
                        <?= ($existing_consent && $existing_consent['consentement_medical']) ? 'checked' : '' ?>>
                    <label for="consent-medical" class="checkbox-label">
                        <strong>Examens médicaux :</strong> J'accepte de me soumettre aux examens médicaux obligatoires pour vérifier la compatibilité et l'absence de maladies transmissibles.
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="consent-legal" name="consent_legal" required
                        <?= ($existing_consent && $existing_consent['consentement_legal']) ? 'checked' : '' ?>>
                    <label for="consent-legal" class="checkbox-label">
                        <strong>Cadre légal :</strong> Je certifie que j'ai lu et compris les conditions légales du don d'organes au Cameroun et j'y adhère pleinement.
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="reset" class="btn-cancel">
                    <i class="fa-solid fa-times"></i> Annuler
                </button>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-check"></i> Valider mon consentement
                </button>
            </div>
        </form>
    </div>

</body>
</html>