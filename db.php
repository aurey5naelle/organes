<?php
// On récupère les valeurs depuis n'importe quelle superglobale disponible
$host     = $_ENV['MYSQLHOST']     ?? $_SERVER['MYSQLHOST']     ?? getenv('MYSQLHOST')     ?: null;
$port     = $_ENV['MYSQLPORT']     ?? $_SERVER['MYSQLPORT']     ?? getenv('MYSQLPORT')     ?: '3306';
$dbname   = $_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: null;
$user     = $_ENV['MYSQLUSER']     ?? $_SERVER['MYSQLUSER']     ?? getenv('MYSQLUSER')     ?: null;
$password = $_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: null;

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
