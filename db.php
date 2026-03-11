<?php
$host = 'localhost';
$user = 'root';
$pass = 'root';
$db = 'projecta';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_errno) {
    die('Erreur MySQL : ' . $mysqli->connect_error);
}

$mysqli->set_charset('utf8mb4');


// Hash commun à tous les clients
// $hash = password_hash('devpassword1', PASSWORD_DEFAULT);
// echo $hash;

?>