<?php
session_start();

if (!isset($_SESSION['id_admin'])) {
    header('Location: ./../login/logpage.php');
    exit();
}

$id_admin = (int) $_SESSION['id_admin'];
$adminName = isset($_SESSION['name']) ? (string) $_SESSION['name'] : 'Admin';
?>