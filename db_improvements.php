<?php
require_once 'db.php';

try {
    // 1. Ajouter des colonnes manquantes à greffons si nécessaire
    $pdo->exec("ALTER TABLE greffons ADD COLUMN IF NOT EXISTS donor_contact VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE greffons ADD COLUMN IF NOT EXISTS donor_age INT NULL");
    $pdo->exec("ALTER TABLE greffons ADD COLUMN IF NOT EXISTS donor_weight DECIMAL(5,2) NULL");
    $pdo->exec("ALTER TABLE greffons ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

    // 2. Créer la table des hôpitaux partenaires si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS hôpitaux_partenaires (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nom VARCHAR(255) NOT NULL UNIQUE,
        code VARCHAR(10) NOT NULL UNIQUE,
        adresse VARCHAR(255),
        telephone VARCHAR(20),
        email VARCHAR(100),
        responsable VARCHAR(100),
        lits_usi INT DEFAULT 5,
        capacite_urgence INT DEFAULT 10,
        statut ENUM('actif', 'inactif') DEFAULT 'actif',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. Créer la table des analyses médicales
    $pdo->exec("CREATE TABLE IF NOT EXISTS analyses_medicales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        greffon_id INT NOT NULL,
        date_analyse DATETIME NOT NULL,
        type_analyse VARCHAR(100),
        resultat VARCHAR(255),
        statut ENUM('en_attente', 'effectuee', 'rejete') DEFAULT 'en_attente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (greffon_id) REFERENCES greffons(id) ON DELETE CASCADE,
        INDEX idx_greffon (greffon_id),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Créer la table des matchings
    $pdo->exec("CREATE TABLE IF NOT EXISTS matchings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        greffon_id INT NOT NULL,
        patient_id INT NOT NULL,
        score_compatibilite INT DEFAULT 0,
        statut ENUM('propose', 'accepte', 'rejete', 'transplante') DEFAULT 'propose',
        date_proposition DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_acceptation DATETIME NULL,
        date_transplantation DATETIME NULL,
        FOREIGN KEY (greffon_id) REFERENCES greffons(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id) REFERENCES liste_attente(id) ON DELETE CASCADE,
        INDEX idx_greffon (greffon_id),
        INDEX idx_patient (patient_id),
        INDEX idx_statut (statut)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Créer la table d'audit pour traçabilité
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        greffon_id INT,
        patient_id INT,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_greffon (greffon_id),
        INDEX idx_patient (patient_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Créer la table des rapports éthiques
    $pdo->exec("CREATE TABLE IF NOT EXISTS rapports_ethiques (
        id INT AUTO_INCREMENT PRIMARY KEY,
        greffon_id INT NOT NULL,
        type_rapport VARCHAR(100),
        contenu TEXT,
        approuve_par INT,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_approbation DATETIME NULL,
        FOREIGN KEY (greffon_id) REFERENCES greffons(id) ON DELETE CASCADE,
        FOREIGN KEY (approuve_par) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_greffon (greffon_id),
        INDEX idx_approuve (approuve_par)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. Créer la table de statut d'expédition
    $pdo->exec("CREATE TABLE IF NOT EXISTS expeditions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        greffon_id INT NOT NULL UNIQUE,
        hôpital_source VARCHAR(100),
        hôpital_destination VARCHAR(100),
        transporteur VARCHAR(100),
        temperature_consigne DECIMAL(4,1),
        date_depart DATETIME,
        date_arrivee_estime DATETIME,
        date_arrivee_reel DATETIME NULL,
        statut ENUM('preparation', 'en_transit', 'recu', 'expire') DEFAULT 'preparation',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (greffon_id) REFERENCES greffons(id) ON DELETE CASCADE,
        INDEX idx_greffon (greffon_id),
        INDEX idx_statut (statut),
        INDEX idx_destination (hôpital_destination)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. Ajouter des index pour optimiser les requêtes
    $pdo->exec("ALTER TABLE greffons ADD INDEX IF NOT EXISTS idx_statut (statut_validation)");
    $pdo->exec("ALTER TABLE greffons ADD INDEX IF NOT EXISTS idx_hopital_source (hopital_source)");
    $pdo->exec("ALTER TABLE greffons ADD INDEX IF NOT EXISTS idx_organe (organe)");
    $pdo->exec("ALTER TABLE greffons ADD INDEX IF NOT EXISTS idx_groupe_sanguin (groupe_sanguin)");
    $pdo->exec("ALTER TABLE greffons ADD INDEX IF NOT EXISTS idx_code_greffon (code_greffon)");
    $pdo->exec("ALTER TABLE greffons ADD INDEX IF NOT EXISTS idx_created (created_at)");

    $pdo->exec("ALTER TABLE users ADD INDEX IF NOT EXISTS idx_email (email)");
    $pdo->exec("ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role (role)");

    $pdo->exec("ALTER TABLE donor_cards ADD INDEX IF NOT EXISTS idx_user (user_id)");
    $pdo->exec("ALTER TABLE donor_consentements ADD INDEX IF NOT EXISTS idx_user (user_id)");

    $pdo->exec("ALTER TABLE liste_attente ADD INDEX IF NOT EXISTS idx_code (code_patient)");
    $pdo->exec("ALTER TABLE liste_attente ADD INDEX IF NOT EXISTS idx_organe (type_organe_requis)");
    $pdo->exec("ALTER TABLE liste_attente ADD INDEX IF NOT EXISTS idx_statut (statut)");
    $pdo->exec("ALTER TABLE liste_attente ADD INDEX IF NOT EXISTS idx_urgence (score_urgence)");

    $pdo->exec("ALTER TABLE activity_logs ADD INDEX IF NOT EXISTS idx_user (user_id)");
    $pdo->exec("ALTER TABLE activity_logs ADD INDEX IF NOT EXISTS idx_created (created_at)");

    // 9. Les hôpitaux seront créés automatiquement quand ils se connectent
    // Pas de données de test insérées automatiquement

    echo "✅ Base de données améliorée avec succès !";
} catch (Exception $e) {
    echo "⚠️ Erreur lors de l'amélioration : " . $e->getMessage();
}
?>
