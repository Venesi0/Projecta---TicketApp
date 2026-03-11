<?php

// Récupération des deux mots de passe saisis.
$newPassword = $_POST["newPassword"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";

// Redirection vers login si les deux champs correspondent.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if ($newPassword === $confirmPassword) {
    header("Location: ./logpage.php?reset-success=1");
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
      background-repeat: no-repeat;
      display: flex;
      justify-content: center;
      align-items: center;
    }
  </style>
</head>

<body>
  <!-- Formulaire de changement de mot de passe. -->
  <form action="./reset-password.php" method="POST" id="resetForm">
    <img src="./../img/logo_projecta.png" alt="logo de TicketFlow" />
    <h1>Enter your new password to connect</h1>

    <label for="newPasswordInput">New Password :</label>
    <input type="password" name="newPassword" id="newPasswordInput" placeholder="Enter a new password" required />
    <div id="passwordError" class="error-text titanic">
      Password must be at least 6 characters long and contain at least one
      letter and one number
    </div>

    <label for="confirmPasswordInput">Confirm Password :</label>
    <input type="password" name="confirmPassword" id="confirmPasswordInput" placeholder="Enter the new password"
      required />
    <div id="confirmedPasswordError" class="error-text titanic">
      Confirmed password should be the same as the previous field
    </div>

    <button type="submit">Connect</button>
  </form>
  <!-- Script front global (validation/comportement UI). -->
  <script src="./../script.js"></script>
</body>

</html>
