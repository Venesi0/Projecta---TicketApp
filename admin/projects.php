<?php

// Vérifie la session admin.
require_once __DIR__ . '/../auth-admin.php';

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Initialisation des états de page.
$requestsNb = 3;
$searchQuery = trim((string) ($_GET['q'] ?? ''));

// $projects = [
//   [
//     "status" => "Active",
//     "name" => "Project 1",
//     "client" => "Client 1",
//     "contractHours" => "50",
//     "openTickets" => "8",
//   ],
//   [
//     "status" => "Ready",
//     "name" => "Project 2",
//     "client" => "Client 2",
//     "contractHours" => "90",
//     "openTickets" => "0",
//   ],
//   [
//     "status" => "Active",
//     "name" => "Project 3",
//     "client" => "Client 3",
//     "contractHours" => "20",
//     "openTickets" => "3",
//   ],
//   [
//     "status" => "Archived",
//     "name" => "Project 4",
//     "client" => "Client 4",
//     "contractHours" => "70",
//     "openTickets" => "9",
//   ],
// ];

$projects = [];

// Liste des demandes projet affichées dans la modale.
$requestedItems = [
  [
    "status" => "Ready",
    "name" => "E-commerce Redesign",
    "client" => "TechCorp",
    "contractHours" => "50",
    "openTickets" => "0",
  ],
  [
    "status" => "Ready",
    "name" => "candies Selling",
    "client" => "RC-LAB",
    "contractHours" => "70",
    "openTickets" => "0",
  ],
  [
    "status" => "Ready",
    "name" => "Water Colling",
    "client" => "RTX-9060",
    "contractHours" => "40",
    "openTickets" => "0",
  ],
];

// Traitement des actions POST (approve/create).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? null;

  if ($action === "approve") {
    $requestIndex = (int) ($_POST["request_index"] ?? -1);
    if (isset($requestedItems[$requestIndex])) {
      $approved = $requestedItems[$requestIndex];
      $projects[] = $approved;
      unset($requestedItems[$requestIndex]);
      $requestedItems = array_values($requestedItems);
    }
  }

  if ($action === "create") {
    $projectName = trim((string) ($_POST["projectName"] ?? ""));
    $projectClient = trim((string) ($_POST["projectClient"] ?? ""));
    $contractHours = (int) ($_POST["contractHours"] ?? 0);

    if ($projectName !== '' && $projectClient !== '' && $contractHours > 0) {
      $clientId = null;

      $findClientStmt = $mysqli->prepare("SELECT id_client FROM clients WHERE name = ? LIMIT 1");
      if ($findClientStmt) {
        $findClientStmt->bind_param("s", $projectClient);
        $findClientStmt->execute();
        $clientResult = $findClientStmt->get_result();
        $clientRow = $clientResult ? $clientResult->fetch_assoc() : null;
        if ($clientRow) {
          $clientId = (int) $clientRow['id_client'];
        }
        $findClientStmt->close();
      }

      if ($clientId === null) {
        $createClientStmt = $mysqli->prepare("
          INSERT INTO clients (name, status, date, projectsNb, openedTickets, totalHours, avatarColor)
          VALUES (?, 'Standard', CURDATE(), 0, 0, 0, '#919090')
        ");
        if ($createClientStmt) {
          $createClientStmt->bind_param("s", $projectClient);
          $createClientStmt->execute();
          $clientId = (int) $mysqli->insert_id;
          $createClientStmt->close();
        }
      }

      if ($clientId !== null) {
        $createProjectStmt = $mysqli->prepare("
          INSERT INTO projects (name, client_id, status, contractHours, openTickets)
          VALUES (?, ?, 'Active', ?, 0)
        ");
        if ($createProjectStmt) {
          $createProjectStmt->bind_param("sii", $projectName, $clientId, $contractHours);
          $createProjectStmt->execute();
          $createProjectStmt->close();
        }
      }
    }
  }
}

// Chargement des projets (avec ou sans filtre de recherche).
if ($searchQuery !== '') {
  $stmt = $mysqli->prepare("
    SELECT
      p.*,
      c.name AS client
    FROM projects p
    LEFT JOIN clients c ON c.id_client = p.client_id
    WHERE p.name LIKE ? OR c.name LIKE ? OR p.status LIKE ?
    ORDER BY p.id ASC
  ");
  if ($stmt) {
    $like = '%' . $searchQuery . '%';
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $projects[] = $row;
    }
    $stmt->close();
  }
} else {
  $result = $mysqli->query("
    SELECT
      p.*,
      c.name AS client
    FROM projects p
    LEFT JOIN clients c ON c.id_client = p.client_id
    ORDER BY p.id ASC
  ");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $projects[] = $row;
    }
  }
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
  <!-- Navigation latérale admin. -->
  <nav id="sidebar">
    <div class="sidebar-logo">
      <img src="./../img/planet_logo.png" alt="Logo" />
      <span>Projecta</span>
    </div>
    <ul>
      <li><a href="./dashboard.php">Dashboard</a></li>
      <li><a href="./projects.php" class="active">Projects</a></li>
      <li><a href="./tickets.php">Tickets</a></li>
      <li><a href="./admin-clients.php">Clients</a></li>
      <li><a href="./../collaborators.php">Collaborators</a></li>
      <li><a href="./profile.html">Profile</a></li>
      <li><a href="./settings.html">Settings</a></li>
      <li><a href="./../login/logpage.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer">
      <span>Connected as ADMIN</span>
    </div>
  </nav>

  <!-- En-tête avec searchbar projets. -->
  <header class="header">
    <form method="GET" action="./projects.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search for a project..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal avec grille des projets. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard">Projects</h1>
      <a href="#projectModal" class="btn-primary <?= $requestsNb > 0 ? 'alert' : 'btn-disabled'; ?>">
        +
        <?= e((string) $requestsNb); ?> Project Requests
      </a>
    </div>

    <section class="projects-grid">

      <?php foreach ($projects as $idx => $project): ?>
        <?php
        $statusClass = match ($project["status"]) {
          "Ready" => "ready",
          "Archived" => "archived",
          default => "",
        };
        $progressPercent = getProjectProgressPercent($project['contractHours'] ?? 0, $project['usedHours'] ?? 0);
        ?>
        <div class="project-card">
          <div class="project-status-tag <?= e($statusClass) ?>"><?= e($project['status']) ?></div>
          <div class="project-info">
            <h2> <?= e($project['name']) ?> </h2>
            <p class="client-name"><?= e($project['client']) ?></p>
          </div>
          <div class="project-stats">
            <div class="progress-container">
              <div class="progress-labels">
                <span>Contract Hours</span>
                <span>
                  <?= e($project['contractHours']) ?>h
                </span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" style="width: <?= e((string) $progressPercent) ?>%"></div>
              </div>
            </div>
            <div class="card-footer">
              <?php if ($project['status'] !== 'Ready'): ?>
                <span><?= e($project['openTickets']) ?> Opened tickets</span>
                <a href="./project-details.php?id_client=<?= (int) $project['client_id'] ?>&id_project=<?= (int) $project['id'] ?>"
                  class="btn-outline view-details-btn">View Details</a>
              <?php endif; ?>
              <?php if ($project['status'] === 'Ready'): ?>
                <a href="#" class="btn-outline confirm-project-btn"
                  data-open-tickets="<?= e($project['openTickets']) ?>">Confirm Project</a>
                <a href="./project-details.php?id_client=<?= (int) $project['client_id'] ?>&id_project=<?= (int) $project['id'] ?>"
                  class="btn-outline view-details-btn">View Details</a>
              <?php endif; ?>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    </section>

  </main>

  <!--Modal for project requests-->
  <div id="projectModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Project Requests</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>

      <div class="modal-body">

        <?php foreach ($requestedItems as $idx => $req): ?>
          <div class="request-item">
            <div class="request-info">
              <div class="title-with-info">
                <h3><?= e($req['name']) ?></h3>
                <a href="#descModal1" class="info-icon-btn">i</a>
              </div>
              <p><strong>Client:</strong> <?= e($req['client']) ?></p>
              <p>
                <strong>Hours:</strong> <?= e($req['contractHours']) ?> |
                <a href="#" class="contract-link">View Contract PDF</a>
              </p>
            </div>
            <div class="request-actions">
              <form method="POST" action="./projects.php"
                style="nmargin: auto; padding: 30px 0 0 0; width: 150%; box-shadow: none; border-radius: 5px;">
                <input type="hidden" name="action" value="approve" />
                <input type="hidden" name="request_index" value="<?= e((string) $idx) ?>" />
                <button class="btn-approve" type="submit">Approve</button>
              </form>
              <button class="btn-reject">Reject</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="modal-footer">
        <a href="#newProjectModal" class="btn-primary">Create Empty Project</a>
        <a href="#" class="btn-secondary">Close</a>
      </div>
    </div>
  </div>

  <!--Modal for project description-->
  <div id="descModal1" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Project Description</h2>
        <a href="#projectModal" class="close-modal">&times;</a>
      </div>
      <div class="form-group">
        <label>Detailed Overview</label>
        <textarea readonly>
This project involves a complete overhaul of the existing e-commerce platform, focusing on improving user experience, mobile responsiveness, and integrating a new payment gateway. The client expects a modern Indigo-based theme.</textarea>
      </div>
      <div class="modal-footer">
        <a href="#projectModal" class="btn-primary">Back to Requests</a>
      </div>
    </div>
  </div>

  <div id="newProjectModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create new project</h2>
        <!-- <span class="close-modal">&times;</span> -->
        <a href="#" class="close-modal">&times;</a>
      </div>
      <form id="createProjectForm" method="POST" action="./projects.php">
        <input type="hidden" name="action" value="create" />
        <div class="form-group">
          <label for="projectName">Project name</label>
          <input type="text" id="projectName" name="projectName" placeholder="Ex: Design refactoring" required />
        </div>
        <div id="nameError" class="error-text titanic">
          Project Name must be at least 6 characters long
        </div>

        <div class="form-group desc">
          <label for="projectDesc">Project description</label>
          <textarea name="projectDesc" id="projectDesc"
            placeholder="Ex : A simple web redesign for our super useful website" required></textarea>
        </div>

        <div class="form-group">
          <label for="projectClient">Client</label>
          <input type="text" id="projectClient" name="projectClient" placeholder="e.g ACME Corp" required />
        </div>
        <div id="clientError" class="error-text titanic">
          Client Name must be at least 4 characters long
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="contractHours">Total Hours</label>
            <input type="number" id="contractHours" name="contractHours" placeholder="50" required />
          </div>
        </div>
        <div id="hoursError" class="error-text titanic">
          Number of hours should be greater than 10h
        </div>

        <div class="modal-footer">
          <a href="#" type="button" class="btn-secondary close-modal">
            Cancel</a>
          <button type="submit" class="btn-primary">Create Project</button>
        </div>
      </form>
    </div>
  </div>
  <script src="./../script.js"></script>
</body>

</html>