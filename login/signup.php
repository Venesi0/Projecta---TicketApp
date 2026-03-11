<?php
// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Récupération des champs du formulaire.
$usernameName = $_POST["usernameName"] ?? "";
$username = $_POST["username"] ?? "";
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";
$signupError = null;

// Traitement de l'inscription.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($password !== $confirmPassword) {
    $signupError = "Passwords do not match.";
  } else {
    $name = trim((string) $usernameName);
    $email = trim((string) $username);
    $hashed_passwd = password_hash($password, PASSWORD_DEFAULT);
    $today = date('Y-m-d');

    $addClientStmt = $mysqli->prepare("
      INSERT INTO clients (name, email, password_hash, status, date, projectsNb, openedTickets, totalHours, avatarColor)
      VALUES (?, ?, ?, 'Standard', ?, 0, 0, 0, '#919090')
    ");

    if ($addClientStmt) {
      $addClientStmt->bind_param("ssss", $name, $email, $hashed_passwd, $today);
      if ($addClientStmt->execute()) {
        $addClientStmt->close();
        header("Location: ./logpage.php?success=1");
        exit();
      }
      $addClientStmt->close();
    }
    $signupError = "Unable to create account.";
  }
}

?>




<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>SignUp Page</title>
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
  <!-- Formulaire de création de compte. -->
  <form action="./signup.php" method="POST" id="signUpForm">
    <div id="leftSignUp">
      <img src="./../img/logo_projecta.png" alt="logo de TicketFlow" />
      <h1><span>Sign Up</span> to create your account</h1>
    </div>

    <div class="vertical-divider"></div>

    <div id="rightSignUp">

      <label for="usernameNameInput">Name :</label>
      <input type="text" name="usernameName" id="usernameNameInput" value="<?= e($usernameName) ?>" required />
      <div id="nameError" class="error-text titanic">
        Name should be at least 4 characters long
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

      <label for="passwordInput">Confirm Password :</label>
      <input type="password" name="confirmPassword" id="confirmPasswordInput" placeholder="Confirm your password"
        required />
      <div id=" confirmedPasswordError" class="error-text titanic">
        Confirmed password should be the same as the previous field
      </div>

      <?php if ($signupError !== null): ?>
        <div class="error-text"><?= e($signupError) ?></div>
      <?php endif; ?>

      <button type="submit">SignUp</button>
    </div>
  </form>
  <!-- Script front global (validation/comportement UI). -->
  <script src="./../script.js"></script>
</body>

</html>