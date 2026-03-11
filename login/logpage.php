<?php

// Demarre la session pour stocker l'utilisateur connecte.
session_start();

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Valeurs d'initialisation utilisees par le formulaire.
$username = $_POST["username"] ?? "";
$role = $_POST["role"] ?? "user";
$message_success = null;

// Message d'erreur de login.
$login_error = null;

// Traitement de la tentative de connexion.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $role = $_POST['role'] ?? "user";
  $email = trim((string) ($_POST['username'] ?? ''));
  $password = (string) ($_POST['password'] ?? '');

  // Verifie si le compte existe cote clients.
  $client = null;
  $clientStmt = $mysqli->prepare("SELECT id_client, email, name, password_hash FROM clients WHERE email = ? LIMIT 1");
  if ($clientStmt) {
    $clientStmt->bind_param('s', $email);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    $client = $clientResult ? $clientResult->fetch_assoc() : null;
    $clientStmt->close();
  }

  // Verifie si le compte existe cote admins (table admins ou admin).
  $admin = null;
  $adminStmt = $mysqli->prepare("SELECT id_admin, email, name, password_hash FROM admins WHERE email = ? LIMIT 1");
  if ($adminStmt) {
    $adminStmt->bind_param('s', $email);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $admin = $adminResult ? $adminResult->fetch_assoc() : null;
    $adminStmt->close();
  }

  // Si compte client valide.
  if ($client && password_verify($password, (string) $client['password_hash'])) {
    if ($role === "admin") {
      $login_error = "This account is a client account.";
    } else {
      $_SESSION['id_client'] = (int) $client['id_client'];
      $_SESSION['email'] = (string) $client['email'];
      $_SESSION['name'] = (string) $client['name'];
      header("Location: ./../user/user-projects.php");
      exit();
    }
  }
  // Si compte admin valide.
  elseif ($admin && password_verify($password, (string) $admin['password_hash'])) {
    if ($role !== "admin") {
      $login_error = "This account is an admin account.";
    } else {
      $_SESSION['id_admin'] = (int) ($admin['id_admin'] ?? 0);
      $_SESSION['email'] = (string) ($admin['email'] ?? '');
      $_SESSION['name'] = (string) ($admin['name'] ?? 'Admin');
      header("Location: ./../admin/dashboard.php");
      exit();
    }
  } else {
    // Identifiants invalides.
    $login_error = "Invalid email or password.";
  }
}

// Affiche un message de succes apres signup/reset.
if ($_SERVER["REQUEST_METHOD"] == "GET") {
  if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message_success = "Signed Up successfully";
  }

  if (isset($_GET["reset-success"]) && $_GET["reset-success"] == '1') {
    $message_success = "Password reseted successfully";
  }
}
?>





<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>Login Page</title>
  <link rel="icon" type="image/x-icon" sizes="32x32" href="./../img/planet_logo_32x32.png" />
  <style>
    body {
      min-height: 100vh;
      height: 100vh;
      background: linear-gradient(135deg, #f5f7fa, #e4ebf5);
      background-image: url("./../img/bg_login.jpg");
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      display: flex;
      justify-content: center;
      align-items: center;
    }
  </style>
</head>

<body>
  <!-- Toast de confirmation en cas de succes. -->
  <?php if ($message_success !== null): ?>
    <div id="successToast" class="toast" role="status" aria-live="polite">
      <?= e($message_success) ?>
    </div>
  <?php endif; ?>

  <!-- Formulaire principal de connexion. -->
  <form action="./logpage.php" method="POST" id="loginForm">
    <img src="./../img/logo_projecta.png" alt="logo de TicketFlow" />
    <h1>
      <span>Log in</span> or
      <span><a href="./signup.php">Sign Up</a></span> to create your account
    </h1>

    <div class="role">
      <label for="roleSelect" id="roleLabel">I am :</label>
      <select name="role" id="roleSelect">
        <option value="user" <?= $role === "user" ? "selected" : "" ?>>a user</option>
        <option value="admin" <?= $role === "admin" ? "selected" : "" ?>>an admin</option>
      </select>
    </div>

    <label for="usernameInput">Email :</label>
    <input type="email" name="username" id="usernameInput" placeholder="Enter your email" value="<?= e($username) ?>"
      required />
    <div id="mailError" class="error-text titanic">
      Email format should be "johndoe@gmail.com"
    </div>

    <label for="passwordInput">Password :</label>
    <input type="password" name="password" id="passwordInput" placeholder="Enter your password" required />
    <div id="passwordError" class="error-text titanic">
      Password must be at least 6 characters long and contain at least one
      letter and one number
    </div>


    <button type="submit">Connect</button>

    <?php if ($login_error !== null): ?>
      <div class="error-text">
        <?= e($login_error) ?>
      </div>
    <?php endif; ?>

    <div id="forgotPasswd">
      <a href="./forgot-password.php" class="linkPassword">forgot password ?</a>
    </div>
  </form>
  <!-- Script front global (validation/comportement UI). -->
  <script src="./../script.js"></script>
  <?php if ($message_success !== null): ?>
    <script>
      const toast = document.getElementById("successToast");
      if (toast) {
        requestAnimationFrame(() => toast.classList.add("show"));
        setTimeout(() => toast.classList.remove("show"), 3000);
      }
    </script>
  <?php endif; ?>
</body>

</html>