<?php
// Replace these 4 values with details from InfinityFree -> Control Panel -> MySQL Databases
$host = 'sql207.infinityfree.com'; // 1. MySQL Hostname
$db   = 'if0_42985977_baryolink_db';  // 2. Full MySQL Database Name
$user = 'if0_42985977';           // 3. MySQL Username
$pass = 'cfax0JnukXE98Vr';     // 4. vPDB / Account Password (Found in Client Area under Account Details)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>