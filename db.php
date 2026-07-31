<?php
// On récupère les valeurs depuis n'importe quelle superglobale disponible
$host     = $_ENV['mysql.railway.internal']     ?? $_SERVER['mysql.railway.internal']     ?? getenv('mysql.railway.internal')     ?: null;
$port     = $_ENV['3306']     ?? $_SERVER['3306']     ?? getenv('3306')     ?: '3306';
$dbname   = $_ENV['railway'] ?? $_SERVER['railway'] ?? getenv('railway') ?: null;
$user     = $_ENV['root']     ?? $_SERVER['root']     ?? getenv('root')     ?: null;
$password = $_ENV['sABgKYFMXbouPClzlIBQlDrxLZogXxEL'] ?? $_SERVER['sABgKYFMXbouPClzlIBQlDrxLZogXxEL'] ?? getenv('sABgKYFMXbouPClzlIBQlDrxLZogXxEL') ?: null;

// Si Railway n'a pas transmis MYSQLHOST, on affiche un message d'aide clair au lieu de planter sur localhost
if (!$host) {
    die("<b>Erreur de configuration :</b> Les variables d'environnement MySQL ne sont pas reçues par le service PHP.<br>Vérifiez l'onglet Variables dans Railway.");
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
