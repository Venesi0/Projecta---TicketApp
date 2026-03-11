<?php

// Démarre la session active.
session_start();
// Nettoie toutes les données de session côté serveur.
session_unset();
session_destroy();

// Redirige vers la page de connexion.
header('Location: ./login/logpage.php');
exit();
?>
