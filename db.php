<?php
$host     = 'localhost';
$dbname   = 'bioethique_cm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('donor', 'hospital', 'ethic-board') NOT NULL DEFAULT 'donor',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS donor_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        prenom VARCHAR(100) NOT NULL,
        nom VARCHAR(100) NOT NULL,
        birth_year INT NOT NULL,
        blood_group VARCHAR(5) NOT NULL,
        consented_organs TEXT,
        emergency_contact VARCHAR(255),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_donor_cards_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS donor_consentements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        consentement_free TINYINT(1) DEFAULT 0,
        consentement_medical TINYINT(1) DEFAULT 0,
        consentement_legal TINYINT(1) DEFAULT 0,
        date_consente DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_donor_consent_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $consentColumns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM donor_consentements") as $col) {
        $consentColumns[$col['Field']] = true;
    }

    if (!isset($consentColumns['consentement_free']) && isset($consentColumns['consentement_livre'])) {
        $pdo->exec("ALTER TABLE donor_consentements ADD COLUMN consentement_free TINYINT(1) DEFAULT 0");
        $pdo->exec("UPDATE donor_consentements SET consentement_free = consentement_livre");
    } elseif (!isset($consentColumns['consentement_free'])) {
        $pdo->exec("ALTER TABLE donor_consentements ADD COLUMN consentement_free TINYINT(1) DEFAULT 0");
    }

    if (!isset($consentColumns['consentement_medical'])) {
        $pdo->exec("ALTER TABLE donor_consentements ADD COLUMN consentement_medical TINYINT(1) DEFAULT 0");
    }

    if (!isset($consentColumns['consentement_legal'])) {
        $pdo->exec("ALTER TABLE donor_consentements ADD COLUMN consentement_legal TINYINT(1) DEFAULT 0");
    }

    if (!isset($consentColumns['date_consente']) && isset($consentColumns['date_consentement'])) {
        $pdo->exec("ALTER TABLE donor_consentements ADD COLUMN date_consente DATETIME DEFAULT CURRENT_TIMESTAMP");
        $pdo->exec("UPDATE donor_consentements SET date_consente = date_consentement WHERE date_consentement IS NOT NULL");
    } elseif (!isset($consentColumns['date_consente'])) {
        $pdo->exec("ALTER TABLE donor_consentements ADD COLUMN date_consente DATETIME DEFAULT CURRENT_TIMESTAMP");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS greffons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        code_greffon VARCHAR(50) NOT NULL UNIQUE,
        donor_name VARCHAR(150) NULL,
        donor_contact VARCHAR(50) NULL,
        organe VARCHAR(100) NOT NULL,
        groupe_sanguin VARCHAR(5) NULL,
        donor_age INT NULL,
        donor_weight DECIMAL(5,2) NULL,
        heure_clampage DATETIME NOT NULL,
        duree_viabilite_heures INT DEFAULT 12,
        compatibilites_count INT DEFAULT 0,
        statut_validation ENUM('En cours', 'Validé', 'Matché', 'Rejeté', 'Approuvé par l\'État', 'Attribution en cours') DEFAULT 'En cours',
        consentement_valide TINYINT(1) DEFAULT 1,
        expedition_destination VARCHAR(255) DEFAULT 'En attente',
        hopital_source VARCHAR(100) NOT NULL DEFAULT 'HGY',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rendez_vous_analyses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        greffon_id INT NOT NULL,
        date_rdv DATETIME NOT NULL,
        laboratoire VARCHAR(200) NOT NULL,
        statut_examen ENUM('Programmé', 'Terminé', 'Annulé') DEFAULT 'Programmé',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossiers_ethique (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code_dossier VARCHAR(20) UNIQUE NOT NULL,
        donneur_id INT NOT NULL,
        type_organe VARCHAR(50) NOT NULL,
        hopital_id INT NOT NULL,
        consentement_signe TINYINT(1) DEFAULT 0,
        statut ENUM('en_cours', 'valide', 'matche', 'rejete') DEFAULT 'en_cours',
        patient_matche_id INT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        date_validation DATETIME NULL,
        date_matching DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS convocations_analyses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dossier_id INT NOT NULL,
        canal ENUM('sms', 'email', 'app') NOT NULL,
        date_rdv DATETIME NOT NULL,
        centre_analyse VARCHAR(150) NOT NULL,
        statut_rdv ENUM('programme', 'effectue', 'annule') DEFAULT 'programme',
        FOREIGN KEY (dossier_id) REFERENCES dossiers_ethique(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS liste_attente (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code_patient VARCHAR(20) UNIQUE NOT NULL,
        nom_patient VARCHAR(100) NOT NULL,
        type_organe_requis VARCHAR(50) NOT NULL,
        groupe_sanguin VARCHAR(5) NOT NULL,
        score_urgence INT NOT NULL,
        statut ENUM('en_attente', 'transplante') DEFAULT 'en_attente'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensemencement automatique de données réelles pour l'alignement BDD / Frontend
    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        $pdo->exec("INSERT INTO users (id, email, password, role) VALUES 
            (1, 'jean.dupont@email.cm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor'),
            (2, 'p.mbida@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor'),
            (3, 'alphonse.nkodo@yahoo.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor')
        ");
        $pdo->exec("INSERT INTO donor_cards (id, user_id, prenom, nom, birth_year, blood_group, consented_organs, emergency_contact) VALUES
            (1, 1, 'Jean', 'Dupont', 1992, 'O+', 'Foie, Rein', 'Marie Dupont (699-00-11-22)'),
            (2, 2, 'Pauline', 'MBIDA', 1997, 'A+', 'Rein', 'M. MBIDA (677-11-22-33)'),
            (3, 3, 'Alphonse', 'NKODO', 1984, 'B+', 'Cornée', 'Paul NKODO (690-33-44-55)')
        ");
        $pdo->exec("INSERT INTO donor_consentements (user_id, consentement_free, consentement_medical, consentement_legal) VALUES
            (1, 1, 1, 1),
            (2, 1, 1, 1),
            (3, 1, 1, 1)
        ");
    }

    $dossierCount = (int)$pdo->query("SELECT COUNT(*) FROM dossiers_ethique")->fetchColumn();
    if ($dossierCount === 0) {
        $pdo->exec("INSERT INTO dossiers_ethique (id, code_dossier, donneur_id, type_organe, hopital_id, consentement_signe, statut) VALUES
            (1, 'TX-2026-91', 1, 'Foie', 1, 1, 'en_cours'),
            (2, 'TX-2026-92', 2, 'Rein', 2, 1, 'valide'),
            (3, 'TX-2026-89', 3, 'Cornée', 3, 1, 'matche')
        ");
    }

    $patientCount = (int)$pdo->query("SELECT COUNT(*) FROM liste_attente")->fetchColumn();
    if ($patientCount === 0) {
        $pdo->exec("INSERT INTO liste_attente (code_patient, nom_patient, type_organe_requis, groupe_sanguin, score_urgence, statut) VALUES
            ('P-104', 'Samuel ETOO', 'Cornée', 'B+', 95, 'transplante'),
            ('P-309', 'Chantal BELLA', 'Foie', 'O+', 98, 'en_attente'),
            ('P-512', 'Marc VAMBA', 'Rein', 'A+', 88, 'en_attente')
        ");
    }
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>