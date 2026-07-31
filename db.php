<?php
// On essaie de lire les variables système, sinon on prend les valeurs de Railway
$host     = $_ENV['mysql.railway.internal']     ?? $_SERVER['mysql.railway.internal']     ?? getenv('mysql.railway.internal')     ?: null;
$port     = $_ENV['3306']     ?? $_SERVER['3306']     ?? getenv('3306')     ?: '3306';
$dbname   = $_ENV['railway'] ?? $_SERVER['railway'] ?? getenv('railway') ?: null;
$user     = $_ENV['root']     ?? $_SERVER['root']     ?? getenv('root')     ?: null;
$password = $_ENV['sABgKYFMXbouPClzlIBQlDrxLZogXxEL'] ?? $_SERVER['sABgKYFMXbouPClzlIBQlDrxLZogXxEL'] ?? getenv('sABgKYFMXbouPClzlIBQlDrxLZogXxEL') ?: null;

// Sécurité si la valeur host est toujours manquante
if ($host === 'Mettre_Ici_La_Valeur_De_MYSQLHOST') {
    die("<b>Erreur :</b> Pensez à remplacer 'Mettre_Ici_La_Valeur_De_MYSQLHOST' dans db.php par le vrai nom d'hôte copié sur Railway.");
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
