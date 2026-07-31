<?php
require_once 'db.php';

try {
    // Vider la table des hôpitaux
    $pdo->exec("TRUNCATE TABLE hôpitaux_partenaires");
    
    echo "✅ Table hôpitaux_partenaires vidée avec succès !\n";
    echo "Les hôpitaux seront créés automatiquement lors de leur connexion.";

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>
