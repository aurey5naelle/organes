<?php
require_once 'db.php';

class AlertSystem {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Générer une alerte pour un greffon
     */
    public function createAlert($greffonId, $type, $message, $severite = 'info') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO alertes (greffon_id, type_alerte, message, severite, statut)
                VALUES (:greffon_id, :type, :message, :severite, 'active')
            ");
            return $stmt->execute([
                ':greffon_id' => $greffonId,
                ':type' => $type,
                ':message' => $message,
                ':severite' => $severite
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Vérifier et créer les alertes de viabilité
     * À appeler régulièrement (cron job ou en temps réel)
     */
    public function checkViability() {
        try {
            $greffons = $this->pdo->query("
                SELECT id, code_greffon, heure_clampage, duree_viabilite_heures 
                FROM greffons 
                WHERE statut_validation != 'Rejeté'
            ")->fetchAll();

            foreach ($greffons as $g) {
                $deadline = strtotime($g['heure_clampage']) + ($g['duree_viabilite_heures'] * 3600);
                $hoursRemaining = ($deadline - time()) / 3600;

                // Vérifier s'il existe déjà une alerte pour ce greffon
                $existing = $this->pdo->prepare("
                    SELECT id FROM alertes 
                    WHERE greffon_id = :id AND type_alerte = :type AND statut = 'active'
                ")->execute([
                    ':id' => $g['id'],
                    ':type' => 'Viabilité faible'
                ]);

                // Créer alerte si viabilité < 6h
                if ($hoursRemaining < 6 && $hoursRemaining > 0) {
                    $this->createAlert(
                        $g['id'],
                        'Viabilité faible',
                        "L'organe {$g['code_greffon']} expire dans " . round($hoursRemaining) . " heures",
                        'warning'
                    );
                }
                
                // Créer alerte si expiré
                if ($hoursRemaining <= 0) {
                    $this->createAlert(
                        $g['id'],
                        'Organe expiré',
                        "L'organe {$g['code_greffon']} a expiré et ne peut plus être utilisé",
                        'danger'
                    );
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtenir toutes les alertes actives
     */
    public function getActiveAlerts($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT a.*, g.code_greffon, g.organe 
                FROM alertes a
                JOIN greffons g ON a.greffon_id = g.id
                WHERE a.statut = 'active'
                ORDER BY a.severite DESC, a.date_creation DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Marquer une alerte comme résolue
     */
    public function resolveAlert($alertId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE alertes 
                SET statut = 'resolved', date_resolution = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([':id' => $alertId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Compter les alertes actives
     */
    public function countActiveAlerts() {
        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM alertes WHERE statut = 'active'")->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Créer une alerte de non-compatibilité
     */
    public function createIncompatibilityAlert($greffonId, $patientsCount) {
        if ($patientsCount === 0) {
            $stmt = $this->pdo->prepare("SELECT code_greffon FROM greffons WHERE id = :id");
            $stmt->execute([':id' => $greffonId]);
            $greffon = $stmt->fetch();

            $this->createAlert(
                $greffonId,
                'Pas de patient compatible',
                "Aucun patient en attente n'est compatible avec l'organe {$greffon['code_greffon']}",
                'warning'
            );
        }
    }
}

// Utilisation :
$alertSystem = new AlertSystem($pdo);

// Exemple : vérifier les viabilités toutes les heures
// $alertSystem->checkViability();

// Exemple : obtenir les alertes
// $alerts = $alertSystem->getActiveAlerts();

// Exemple : créer une alerte manuelle
// $alertSystem->createAlert(1, 'Test', 'Ceci est un test', 'info');
?>
