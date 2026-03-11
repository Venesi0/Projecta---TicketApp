<?php

// Chargement des fonctions utilitaires.
require_once __DIR__ . '/../utils.php';

// Récupération des champs du formulaire.
$username = $_POST["username"] ?? "";

// Recomposition du code à 6 chiffres.
$digits = [];
for ($i = 1; $i <= 6; $i++) {
  $digits[$i] = $_POST["code{$i}"] ?? "";
}

$fullCode = implode("", $digits);

// Vérification simple du code, puis redirection.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($fullCode === "222222") {
    header("Location: ./reset-password.php");
    exit();
  }
}

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>Forgot Password</title>
  <link rel="icon" type="image/x-icon" sizes="32x32" href="./../img/planet_logo_32x32.png" />
  <style>
    body {
      min-height: 100vh;
      height: 100vh;
      background: linear-gradient(135deg, #f5f7fa, #e4ebf5);
      background-image: url("./../img/bg_login.jpg");
      background-size: cover;
      background-position: center;
      display: flex;
      justify-content: center;
      align-items: center;
    }
  </style>
</head>

<body>
  <!-- Formulaire de demande/réinitialisation de mot de passe. -->
  <form action="./forgot-password.php" method="POST" id="ForgotPasswordForm">
    <img src="./../img/logo_projecta.png" alt="logo de TicketFlow" />
    <h1>Enter your email to receive a code</h1>

    <label for="usernameInput">Email :</label>
    <input type="email" name="username" id="usernameInput" placeholder="john@gmail.com" value="<?= e($username) ?>" required />
    <div id="mailError" class="error-text titanic">
      Email format should be "johndoe@gmail.com"
    </div>

    <button type="button">Send code</button>

    <div class="code-inputs">
      <label for="code1">Enter code :</label>
      <div class="code-group">
        <?php foreach (range(1, 6) as $i): ?>
          <input type="text" id="code<?= $i ?>" name="code<?= $i ?>" maxlength="1" inputmode="numeric" pattern="[0-9]"
            value="<?= e($digits[$i]) ?>" required />
        <?php endforeach; ?>
      </div>
      <div id="codeError" class="error-text titanic">
        Code should be '222222'
      </div>
    </div>
    <button type="submit">Reset Password</button>
  </form>
  <!-- Script front global (validation/comportement UI). -->
  <script src="./../script.js"></script>
</body>

</html>
