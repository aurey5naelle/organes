SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. TABLE UTILISATEURS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('donor', 'hospital', 'ethic-board') NOT NULL DEFAULT 'donor',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. TABLE CARTES DE DONNEURS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `donor_cards`;
CREATE TABLE `donor_cards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `birth_year` INT NOT NULL,
    `blood_group` VARCHAR(5) NOT NULL,
    `consented_organs` TEXT,
    `emergency_contact` VARCHAR(255),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_donor_cards_users` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. TABLE DES GREFFONS & DONNEURS DÉCLARÉS
-- --------------------------------------------------------
DROP TABLE IF EXISTS `greffons`;
CREATE TABLE `greffons` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `code_greffon` VARCHAR(50) NOT NULL UNIQUE,
    `donor_name` VARCHAR(150) NULL,
    `donor_contact` VARCHAR(50) NULL,
    `organe` VARCHAR(100) NOT NULL,
    `groupe_sanguin` VARCHAR(5) NULL,
    `donor_age` INT NULL,
    `donor_weight` DECIMAL(5,2) NULL,
    `heure_clampage` DATETIME NOT NULL,
    `duree_viabilite_heures` INT DEFAULT 12,
    `compatibilites_count` INT DEFAULT 0,
    `statut_validation` ENUM('En cours', 'Validé', 'Matché', 'Rejeté', 'Approuvé par l\'État', 'Attribution en cours') DEFAULT 'En cours',
    `consentement_valide` TINYINT(1) DEFAULT 1,
    `expedition_destination` VARCHAR(255) DEFAULT 'En attente',
    `hopital_source` VARCHAR(100) NOT NULL DEFAULT 'HGY',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_greffons_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. TABLE DOSSIERS DE SUIVI ÉTHIQUE
-- --------------------------------------------------------
DROP TABLE IF EXISTS `dossiers_ethique`;
CREATE TABLE `dossiers_ethique` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code_dossier` VARCHAR(20) UNIQUE NOT NULL,
    `donneur_id` INT NOT NULL,
    `type_organe` VARCHAR(50) NOT NULL,
    `hopital_id` INT NULL,
    `consentement_signe` TINYINT(1) DEFAULT 0,
    `statut` ENUM('en_cours', 'valide', 'matche', 'rejete') DEFAULT 'en_cours',
    `patient_matche_id` INT NULL,
    `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `date_validation` DATETIME NULL,
    `date_matching` DATETIME NULL,
    CONSTRAINT `fk_ethique_user` FOREIGN KEY (`donneur_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. TABLE DES CONVOCATIONS & ANALYSES
-- --------------------------------------------------------
DROP TABLE IF EXISTS `convocations_analyses`;
CREATE TABLE `convocations_analyses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `dossier_id` INT NOT NULL,
    `canal` ENUM('sms', 'email', 'app') NOT NULL,
    `date_rdv` DATETIME NOT NULL,
    `centre_analyse` VARCHAR(150) NOT NULL,
    `statut_rdv` ENUM('programme', 'effectue', 'annule') DEFAULT 'programme',
    CONSTRAINT `fk_convocation_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers_ethique`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. TABLE DES RECEVEURS (LISTE D'ATTENTE DE TRANSPLANTATION)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `receveur`;
CREATE TABLE `receveur` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code_patient` VARCHAR(20) UNIQUE NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `organe_requis` VARCHAR(50) NOT NULL,
    `groupe_sanguin` VARCHAR(5) NOT NULL,
    `score_urgence` INT DEFAULT 0,
    `statut` ENUM('en_attente', 'transplante') DEFAULT 'en_attente',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. TABLES COMPLÉMENTAIRES (PARAMÈTRES, LOGS, ALERTES)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `rendez_vous_analyses`;
CREATE TABLE `rendez_vous_analyses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `greffon_id` INT NOT NULL,
    `date_rdv` DATETIME NOT NULL,
    `laboratoire` VARCHAR(200) NOT NULL,
    `statut_examen` ENUM('Programmé', 'Terminé', 'Annulé') DEFAULT 'Programmé',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rdv_greffon` FOREIGN KEY (`greffon_id`) REFERENCES `greffons`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `parametres_garde`;
CREATE TABLE `parametres_garde` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hopital_code` VARCHAR(50) NOT NULL UNIQUE DEFAULT 'HGY',
    `chirurgien_garde` VARCHAR(150) NOT NULL,
    `coordinateur_contact` VARCHAR(50) NOT NULL,
    `capacite_usi` ENUM('disponible', 'sature', 'restreint') DEFAULT 'disponible',
    `lits_occupes` INT DEFAULT 8,
    `lits_totaux` INT DEFAULT 12,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `logs_activite`;
CREATE TABLE `logs_activite` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `ip_adresse` VARCHAR(45) NOT NULL,
    `localisation` VARCHAR(100) DEFAULT 'Cameroun',
    `statut` VARCHAR(50) DEFAULT 'Succès',
    `date_action` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(255) NOT NULL,
    `details` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `alertes`;
CREATE TABLE `alertes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titre` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `niveau` ENUM('info', 'warning', 'danger', 'success') DEFAULT 'info',
    `statut` ENUM('active', 'resolue') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- INSERTION DES DONNÉES DE TEST & DÉMONSTRATION
-- ========================================================

-- Utilisateurs de démonstration
INSERT INTO `users` (`id`, `email`, `password`, `role`) VALUES
(1, 'donneur.test@gmail.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.yM.8J1eO1G2.', 'donor'),
(2, 'hopital.douala@sante.cm', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1X.wK.yM.8J1eO1G2.', 'hospital');

-- Cartes de donneur liées
INSERT INTO `donor_cards` (`user_id`, `prenom`, `nom`, `birth_year`, `blood_group`, `consented_organs`, `emergency_contact`) VALUES
(1, 'Emmanuel', 'Eboa', 1992, 'A+', 'Rein, Cornée', '+237 699 00 11 22');

-- Greffons
INSERT INTO `greffons` (
    `code_greffon`, `organe`, `groupe_sanguin`, `heure_clampage`, `duree_viabilite_heures`, `compatibilites_count`, `statut_validation`, `expedition_destination`, `hopital_source`
) VALUES
('#RN-2026-A1', 'Rein (Gauche)', 'A+', '2026-07-14 09:45:00', 4, 3, 'Approuvé par l\'État', 'En route → HGD (Douala)', 'Hôpital Général de Yaoundé'),
('#CO-2026-F9', 'Cornée', 'O+', '2026-07-14 11:30:00', 20, 7, 'Approuvé par l\'État', 'Reçu & Transplanté localement', 'Hôpital Central de Douala'),
('#FO-2026-L2', 'Foie (Lobe)', 'B+', '2026-07-13 22:10:00', 7, 1, 'En cours', 'En attente', 'CHU de Yaoundé');

-- Receveurs (Patients en attente de don)
INSERT INTO `receveur` (`code_patient`, `nom`, `prenom`, `organe_requis`, `groupe_sanguin`, `score_urgence`, `statut`) VALUES
('P-309', 'Nkodo', 'Jean-Paul', 'Rein (Gauche)', 'A+', 85, 'en_attente'),
('P-412', 'Mballa', 'Chantal', 'Cornée', 'O+', 60, 'en_attente');

-- Dossiers Éthiques de départ
INSERT INTO `dossiers_ethique` (`code_dossier`, `donneur_id`, `type_organe`, `hopital_id`, `consentement_signe`, `statut`) VALUES
('TX-2026-91', 1, 'Rein (Gauche)', 1, 1, 'en_cours');

-- Paramètres & Alertes
INSERT INTO `parametres_garde` (`hopital_code`, `chirurgien_garde`, `coordinateur_contact`, `capacite_usi`, `lits_occupes`, `lits_totaux`) 
VALUES ('HGY', 'Dr. Ndemba (Néphrologie)', '+237 677 88 99 00', 'disponible', 8, 12);

INSERT INTO `alertes` (`titre`, `message`, `niveau`, `statut`) VALUES
('Alerte de Viabilité critique', 'Le lobe de Foie arrive à expiration dans moins de 4 heures.', 'danger', 'active'),
('Nouveau greffon disponible', 'Un greffon de type Cornée est prêt pour attribution.', 'info', 'active');

CREATE TABLE IF NOT EXISTS `convocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `code_dossier` VARCHAR(50) NOT NULL,
  `date_rdv` DATETIME NOT NULL,
  `lieu` VARCHAR(255) NOT NULL,
  `instructions` TEXT NULL,
  `statut` VARCHAR(50) DEFAULT 'En attente',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `convocations`;
CREATE TABLE `convocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `code_dossier` VARCHAR(50) DEFAULT NULL,
  `date_rdv` DATETIME NOT NULL,
  `lieu` VARCHAR(255) NOT NULL,
  `instructions` TEXT NULL,
  `statut` VARCHAR(50) DEFAULT 'En attente',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_convocations_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE HÔPITAUX PARTENAIRES
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hopitaux_partenaires`;
CREATE TABLE `hopitaux_partenaires` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `adresse` VARCHAR(255) NOT NULL,
    `telephone` VARCHAR(50) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `responsable` VARCHAR(150) NOT NULL,
    `lits_usi` INT DEFAULT 0,
    `capacite_urgence` INT DEFAULT 0,
    `statut` ENUM('actif', 'inactif') DEFAULT 'actif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion de quelques hôpitaux partenaires de démonstration (Cameroun)
INSERT INTO `hopitaux_partenaires` 
(`nom`, `code`, `adresse`, `telephone`, `email`, `responsable`, `lits_usi`, `capacite_urgence`, `statut`) 
VALUES
('Hôpital Général de Yaoundé', 'HGY', 'Ngousso, Yaoundé', '+237 222 21 20 16', 'contact@hgy.cm', 'Pr. Arthur Essomba', 12, 45, 'actif'),
('Hôpital Général de Douala', 'HGD', 'Bépanda, Douala', '+237 233 42 22 05', 'contact@hgd.cm', 'Dr. Marie Noëlle', 15, 60, 'actif'),
('Hôpital Central de Yaoundé', 'HCY', 'Centre-ville, Yaoundé', '+237 222 23 40 20', 'info@hcy.cm', 'Dr. Joseph Mbanya', 8, 30, 'actif'),
('CHU de Yaoundé', 'CHUY', 'Melen, Yaoundé', '+237 222 23 11 12', 'contact@chuy.cm', 'Pr. Paul Koki', 10, 25, 'actif');


ALTER TABLE `greffons` 
ADD COLUMN `patient_id` INT NULL AFTER `user_id`,
ADD CONSTRAINT `fk_greffons_patient` 
    FOREIGN KEY (`patient_id`) REFERENCES `liste_attente`(`id`) ON DELETE SET NULL;