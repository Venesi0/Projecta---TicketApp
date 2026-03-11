<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Vérifie la session admin via le guard commun.
require_once __DIR__ . '/../auth-admin.php';

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';
// Récupération du terme de recherche.
$searchQuery = trim((string) ($_GET['q'] ?? ''));


// Previous Static Data

// $clients = [
//   [
//     "name" => "ACME Corp",
//     "status" => "Premium",
//     "date" => "January 2022",
//     "projectsNb" => 3,
//     "openedTickets" => 13,
//     "totalHours" => 145,
//     "avatarColor" => '#10b981',
//   ],
//   [
//     "name" => "Beta Solutions",
//     "status" => "Standard",
//     "date" => "January 2022",
//     "projectsNb" => 1,
//     "openedTickets" => 2,
//     "totalHours" => 12,
//     "avatarColor" => '#3b82f6',
//   ],
//   [
//     "name" => "ESIEA Laval",
//     "status" => "Standard",
//     "date" => "January 2022",
//     "projectsNb" => 4,
//     "openedTickets" => 7,
//     "totalHours" => 121,
//     "avatarColor" => '#ef4444',
//   ],
// ];


$clients = [];

// Traitement des actions POST (édition/création client).
if ($_SERVER['REQUEST_METHOD'] === "POST") {
  $action = $_POST['action'] ?? null;
  //dd($_POST);

  if ($action === "edit") {
    //dd($_POST);
    $id = isset($_POST['id_client']) ? (int) $_POST['id_client'] : null;

    if ($id) {
      $name = $_POST['name'] ?? null;
      $status = $_POST['status'] ?? null;
      $date = $_POST['date'] ?? null;

      $stmt = $mysqli->prepare("
            UPDATE clients
            SET name = ?, status = ?, date = ?
            WHERE id_client = ?
        ");
      $stmt->bind_param("sssi", $name, $status, $date, $id);
      $stmt->execute();
    }

  } elseif ($action === "create") {
    //dd($_POST);

    if (!empty($_POST['name']) && strlen($_POST['name']) >= 4 && !empty($_POST['date'])) {

      // Add a client (static)  
      //   $newClient = [
      //   'name' => $_POST['name'],
      //   'status' => $_POST['status'],
      //   'date' => $_POST['date'],
      //   'projectsNb' => 0,
      //   'openedTickets' => 0,
      //   'totalHours' => 0,
      //   'avatarColor' => '#919090',_
      // ];

      // $clients[] = $newClient;

      $name = $_POST['name'];
      $status = $_POST['status'];
      $date = $_POST['date'];

      $stmt = $mysqli->prepare("
        INSERT INTO clients (name, status, date, projectsNb, openedTickets, totalHours, avatarColor)
        VALUES (?, ?, ?, 0, 0, 0, '#919090')
    ");
      $stmt->bind_param("sss", $name, $status, $date);
      $stmt->execute();
    }
  }
}

// Chargement des clients (avec ou sans filtre de recherche).
if ($searchQuery !== '') {
  $stmt = $mysqli->prepare("
    SELECT *
    FROM clients
    WHERE name LIKE ? OR status LIKE ? OR date LIKE ?
    ORDER BY id_client ASC
  ");
  if ($stmt) {
    $like = '%' . $searchQuery . '%';
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $clients[] = $row;
    }
    $stmt->close();
  }
} else {
  $result = $mysqli->query("SELECT * FROM clients ORDER BY id_client ASC");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $clients[] = $row;
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
  <title>Admin - Clients</title>
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
      <li><a href="./projects.php">Projects</a></li>
      <li><a href="./tickets.php">Tickets</a></li>
      <li><a href="./admin-clients.php" class="active">Clients</a></li>
      <li><a href="./../collaborators.php">Collaborators</a></li>
      <li><a href="./profile.html">Profile</a></li>
      <li><a href="./settings.html">Settings</a></li>
      <li><a href="./../login/logpage.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer">
      <span>Connected as ADMIN</span>
    </div>
  </nav>

  <!-- En-tête avec searchbar clients. -->
  <header class="header">
    <form method="GET" action="./admin-clients.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search for a client..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal avec cartes clients. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard">Clients Management</h1>
      <a href="#clientModal" class="btn-primary">+ New Client</a>
    </div>

    <section class="admin-clients-grid">

      <?php foreach ($clients as $idx => $client): ?>
        <div class="client-card">
          <div class="client-card-header">
            <div class="client-avatar" style="background: <?= e($client['avatarColor']) ?>">
              <?= getInitials($client['name']) ?>
            </div>
            <div class="client-meta">
              <h2><?= e($client['name']) ?></h2>
              <span>Registered since <?= e($client['date']) ?></span>
            </div>
            <span
              class="badge-status <?= strtolower(e($client['status'])) === "premium" ? "active" : "" ?>"><?= e($client['status']) ?></span>
          </div>

          <div class="client-stats-row">
            <div class="stat-item">
              <span class="stat-label">Projects</span>
              <span class="stat-value"><?= e($client['projectsNb']) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Open Tickets</span>
              <span class="stat-value"><?= e($client['openedTickets']) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Hours (Month)</span>
              <span class="stat-value"><?= e($client['totalHours']) ?>h</span>
            </div>
          </div>

          <div class="client-card-footer">
            <a href="./projects.php?id=<?= (int) $client['id_client'] ?>" class="btn-outline">See projects</a>
            <a href="#clientEditModal-<?= e($idx) ?>" class="btn-icon-settings">⚙️</a>
          </div>
        </div>
      <?php endforeach; ?>

      <div id="clientModal" class="modal-overlay">
        <div class="modal-content">
          <div class="modal-header">
            <h2>New Client</h2>
            <a href="#" class="close-modal">&times;</a>
          </div>
          <form id="createClientForm" method="POST">
            <div class="form-group">
              <label for="clientName">Client name</label>
              <input type="text" name="name" id="clientName" placeholder="Ex: ESIEA Ivry" required />
              <div id="clientNameError" class="error-text titanic">
                Client name must be at least 4 characters
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="projectStatus">Edit status</label>
                <select id="projectStatus" name="status">
                  <option value="Premium">Premium</option>
                  <option value="Standard">Standard</option>
                </select>
                <div id="clientStatusError" class="error-text titanic">
                  Please choose a status
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="clientDate">Date</label>
              <input type="date" name="date" id="clientDate" required />
              <div id="clientDateError" class="error-text titanic">
                Please choose a date
              </div>
            </div>

            <div class="form-group">
              <label>Contract file</label>
              <div class="file-drop-area" id="dropArea">
                <span class="fake-btn">Choose file</span>
                <span class="file-msg">or drag and drop here</span>
                <input class="file-input" type="file" id="contractFile" accept=".pdf,.doc,.docx" required />
              </div>
            </div>

            <div class="modal-footer">
              <a href="#" type="button" class="btn-secondary close-modal">
                Cancel
              </a>
              <input type="hidden" name="action" value="create">
              <button type="submit" class="btn-primary">Add Client</button>
            </div>
          </form>
        </div>
      </div>

      <?php foreach ($clients as $idx => $client): ?>
        <div id="clientEditModal-<?= e($idx) ?>" class="modal-overlay">
          <div class="modal-content">
            <div class="modal-header">
              <h2>Edit Client</h2>
              <a href="#" class="close-modal">&times;</a>
            </div>

            <form id="editClientForm" method="POST" action="./admin-clients.php">
              <input type="hidden" name="id_client" value="<?= (int) $client['id_client'] ?>">


              <div class="form-group">
                <label for="clientNameEdit-<?= e($idx) ?>">Client name</label>
                <input type="text" name="name" id="clientNameEdit-<?= e($idx) ?>" value="<?= e($client['name']) ?>"
                  required />
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="projectStatusEdit-<?= e($idx) ?>">Edit status</label>
                  <select id="projectStatusEdit-<?= e($idx) ?>" name="status">
                    <option value="Premium" <?= $client['status'] === 'Premium' ? 'selected' : '' ?>>Premium</option>
                    <option value="Standard" <?= $client['status'] === 'Standard' ? 'selected' : '' ?>>Standard</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label for="clientDateEdit-<?= e($idx) ?>">Date</label>
                <input type="date" name="date" id="clientDateEdit-<?= e($idx) ?>" required />
              </div>

              <div class="modal-footer">
                <a href="#" type="button" class="btn-secondary close-modal">Cancel</a>
                <input type="hidden" name="action" value="edit">
                <button type="submit" class="btn-primary">Edit Client</button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

  </main>
  <script src="./../script.js"></script>
  <script>
    document.querySelectorAll(".file-input").forEach((input) => {
      input.addEventListener("change", function () {
        const fileName = this.files[0] ? this.files[0].name : "";
        const label = this.parentElement.querySelector(".file-msg");
        if (label) label.textContent = fileName || "or drag and drop here";
      });
    });
  </script>
</body>

</html>