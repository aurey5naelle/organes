<?php
require_once 'db.php';

try {
    // Créer la table des alertes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alertes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            greffon_id INT NOT NULL,
            type_alerte VARCHAR(100) NOT NULL,
            message TEXT,
            severite ENUM('info', 'warning', 'danger') DEFAULT 'info',
            statut ENUM('active', 'resolved', 'dismissed') DEFAULT 'active',
            date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_resolution DATETIME NULL,
            FOREIGN KEY (greffon_id) REFERENCES greffons(id) ON DELETE CASCADE,
            INDEX idx_statut (statut),
            INDEX idx_type (type_alerte),
            INDEX idx_greffon (greffon_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "✅ Table alertes créée avec succès !\n\n";

    // Créer des alertes de test
    $greffons = $pdo->query("SELECT id, code_greffon, heure_clampage, duree_viabilite_heures FROM greffons LIMIT 3")->fetchAll();
    
    foreach ($greffons as $greffon) {
        $deadline = strtotime($greffon['heure_clampage']) + ($greffon['duree_viabilite_heures'] * 3600);
        $hoursRemaining = ($deadline - time()) / 3600;

        // Alerte si moins de 6 heures de viabilité
        if ($hoursRemaining < 6 && $hoursRemaining > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO alertes (greffon_id, type_alerte, message, severite, statut)
                VALUES (:greffon_id, :type, :message, :severite, 'active')
            ");
            $stmt->execute([
                ':greffon_id' => $greffon['id'],
                ':type' => 'Viabilité faible',
                ':message' => "L'organe " . $greffon['code_greffon'] . " expire dans " . round($hoursRemaining) . " heures",
                ':severite' => 'warning'
            ]);
            echo "⚠️  Alerte créée pour " . $greffon['code_greffon'] . "\n";
        }

        // Alerte si expiré
        if ($hoursRemaining <= 0) {
            $stmt = $pdo->prepare("
                INSERT INTO alertes (greffon_id, type_alerte, message, severite, statut)
                VALUES (:greffon_id, :type, :message, :severite, 'active')
            ");
            $stmt->execute([
                ':greffon_id' => $greffon['id'],
                ':type' => 'Organe expiré',
                ':message' => "L'organe " . $greffon['code_greffon'] . " a expiré",
                ':severite' => 'danger'
            ]);
            echo "🚨 Alerte CRITIQUE créée pour " . $greffon['code_greffon'] . "\n";
        }
    }

    echo "\n✅ Alertes initialisées !\n";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>
