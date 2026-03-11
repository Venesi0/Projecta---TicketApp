<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifie la session client
require_once __DIR__ . '/../auth-user.php';

// Chargement des helpers et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Récupération des infos de session et de la recherche.
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$projects = [];

// Traitement de création projet (simple et persistant).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? null;

  if ($action === "create") {
    $name = trim((string) ($_POST["name"] ?? ""));
    $totalHours = (int) ($_POST["totalHours"] ?? 0);

    if ($name !== '' && $totalHours > 0) {
      $createStmt = $mysqli->prepare("
        INSERT INTO projects (name, client_id, status, contractHours, usedHours, openTickets)
        VALUES (?, ?, 'Wait of approval', ?, 0, 0)
      ");
      if ($createStmt) {
        $createStmt->bind_param('sii', $name, $id_client, $totalHours);
        $createStmt->execute();
        $createStmt->close();
      }
    }

    $redirect = './user-projects.php';
    if ($searchQuery !== '') {
      $redirect .= '?q=' . urlencode($searchQuery);
    }
    header('Location: ' . $redirect);
    exit();
  }
}

// Chargement des projets du client (avec ou sans filtre de recherche).
if ($searchQuery !== '') {
  $stmt = $mysqli->prepare("
    SELECT id, name, status, usedHours, contractHours, openTickets
    FROM projects
    WHERE client_id = ? AND (name LIKE ? OR status LIKE ?)
    ORDER BY id DESC
  ");
  if ($stmt) {
    $like = '%' . $searchQuery . '%';
    $stmt->bind_param('iss', $id_client, $like, $like);
  }
} else {
  $stmt = $mysqli->prepare("
    SELECT id, name, status, usedHours, contractHours, openTickets
    FROM projects
    WHERE client_id = ?
    ORDER BY id DESC
  ");
  if ($stmt) {
    $stmt->bind_param('i', $id_client);
  }
}

if (isset($stmt) && $stmt) {
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $projects[] = [
      'id' => (int) ($row['id'] ?? 0),
      'status' => (string) ($row['status'] ?? ''),
      'name' => (string) ($row['name'] ?? ''),
      'usedHours' => (int) ($row['usedHours'] ?? 0),
      'contractHours' => (int) ($row['contractHours'] ?? 0),
      'openTickets' => (int) ($row['openTickets'] ?? 0),
    ];
  }
  $stmt->close();
}

?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>Projects - Projecta</title>
  <link rel="icon" type="image/x-icon" sizes="32x32" href="./../img/planet_logo_32x32.png" />
</head>

<body>
  <!-- Navigation latérale utilisateur. -->
  <nav id="sidebar">
    <div class="sidebar-logo">
      <img src="./../img/planet_logo.png" alt="Logo" />
      <span>Projecta</span>
    </div>
    <ul>
      <li><a href="./user-projects.php" class="active">My Projects</a></li>
      <li><a href="./user-tickets.php">My Tickets</a></li>
      <li><a href="./user-contact.php">Contact Support</a></li>
      <li><a href="./user-profile.html">Profile</a></li>
      <li><a href="./user-settings.html">Settings</a></li>
      <li><a href="./../logout.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer user">
      <span>Connected as CLIENT</span>
    </div>
  </nav>

  <!-- En-tête avec message client + barre de recherche. -->
  <header class="header">
    <div class="client-welcome">Welcome, <strong><?= e($clientName) ?></strong></div>
    <form method="GET" action="./user-projects.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search for a project..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal avec cartes projets. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard">Projects</h1>
      <!-- <button class="btn-primary">+ New Project</button> -->
      <a href="#projectModal" class="btn-primary">+ New Project</a>
    </div>

    <section class="projects-grid">
      <?php foreach ($projects as $project): ?>
        <?php
        // Calcul de progression à partir des vraies heures.
        $used = (int) $project["usedHours"];
        $total = max((int) $project["contractHours"], 1);
        $clampedPct = getProjectProgressPercent($total, $used);
        $statusClass = match ($project["status"]) {
          "Archived" => "archived",
          "Waiting for ticket" => "wait",
          "Wait of approval" => "wait",
          default => "",
        };
        $barClass = $used > $total ? "progress-bar alert" : "progress-bar";
        $fillClass = $used > $total ? "progress-fill warning" : "progress-fill";
        $ticketLabel = ((int) $project["openTickets"] === 1) ? "Open Ticket" : "Open Tickets";
        ?>
        <div class="project-card">
          <div class="project-status-tag <?= e($statusClass) ?>"><?= e($project["status"]) ?></div>
          <div class="project-info">
            <h2><?= e($project["name"]) ?></h2>
          </div>
          <div class="project-stats">
            <div class="progress-container">
              <div class="progress-labels">
                <span>Contract Hours</span>
                <span><?= e((string) $used) ?>/<?= e((string) $total) ?>h</span>
              </div>
              <div class="<?= e($barClass) ?>">
                <div class="<?= e($fillClass) ?>" style="width: <?= e((string) $clampedPct) ?>%"></div>
              </div>
            </div>
            <div class="card-footer">
              <span><?= e((string) $project["openTickets"]) ?>   <?= e($ticketLabel) ?></span>
              <a href="./user-project-details.php?id_project=<?= (int) $project['id'] ?>" class="btn-outline">
                View Details
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </main>

  <!-- Modale de création de projet. -->
  <div id="projectModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create new project</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>
      <form id="createProjectForm" method="POST" action="./user-projects.php">
        <input type="hidden" name="action" value="create" />
        <div class="form-group">
          <label for="projectName">Project name</label>
          <input type="text" name="name" id="projectName" placeholder="Ex: Design refactoring" required />
          <div id="userProjectNameError" class="error-text titanic">
            Project name must be at least 6 characters
          </div>
        </div>

        <div class="form-group desc">
          <label for="projectDesc">Project description</label>
          <textarea name="projectDesc" id="projectDesc"
            placeholder="Ex : A simple web redesign for our super useful website"></textarea>
          <div id="userProjectDescError" class="error-text titanic">
            Description must be at least 30 characters
          </div>
        </div>

        <div class="form-group">
          <label for="projectClient">Client</label>
          <input type="text" id="projectClient" value="<?= e($clientName) ?>" readonly />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="contractHours">Total Hours</label>
            <input type="number" name="totalHours" id="contractHours" placeholder="50" required />
            <div id="userProjectHoursError" class="error-text titanic">
              Minimum 5 hours required
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary close-modal">
            Cancel
          </button>
          <button type="submit" class="btn-primary">Send Request</button>
        </div>
      </form>
    </div>
  </div>
  <script src="./../script.js"></script>
</body>

</html>