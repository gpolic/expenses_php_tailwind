<?php
$servername = "server";
$username = "user_name";
$password = "password";
$dbname = "db_name";
$port = 12345;

try {
    $pdo = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password, array(
        PDO::MYSQL_ATTR_SSL_CA => '/home/user_name/ca.pem',
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
    ));
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

