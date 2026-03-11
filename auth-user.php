<?php
session_start();

if (!isset($_SESSION['id_client'])) {
    header('Location: ./../login/logpage.php');
    exit();
}

$id_client = (int) $_SESSION['id_client'];
$clientName = isset($_SESSION['name']) ? (string) $_SESSION['name'] : 'Unknown client';
?>