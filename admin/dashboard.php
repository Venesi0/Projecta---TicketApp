<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Vérifie la session admin.
require_once __DIR__ . '/../auth-admin.php';

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Initialisation des compteurs affichés dans le dashboard.
$openedTickets = 0;
$inProgTickets = 0;
$toValidateTickets = 0;

// $tickets = [
//   [
//     "subject" => "Login bug",
//     "project" => "Website Redesign",
//     "status" => "In progress",
//     "status_class" => "in-progress",
//     "type" => "Included",
//     "client" => "ACME Corp",
//   ],
//   [
//     "subject" => "Invoice export",
//     "project" => "CRM Tool",
//     "status" => "To validate",
//     "status_class" => "done",
//     "type" => "Billable",
//     "client" => "Beta Solutions",
//   ],
//   [
//     "subject" => "Contact form error",
//     "project" => "Marketing Website",
//     "status" => "Wait for client",
//     "status_class" => "pending",
//     "type" => "Included",
//     "client" => "Gamma Studio",
//   ],
//   [
//     "subject" => "Performance issue",
//     "project" => "Mobile App",
//     "status" => "In progress",
//     "status_class" => "in-progress",
//     "type" => "Billable",
//     "client" => "Delta Mobile",
//   ],
// ];

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$tickets = [];

// Calcul des statistiques globales de tickets.
$statsResult = $mysqli->query("
  SELECT
    SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) AS opened_count,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
    SUM(CASE WHEN status = 'To Validate' THEN 1 ELSE 0 END) AS to_validate_count
  FROM tickets
");

if ($statsResult) {
  $statsRow = $statsResult->fetch_assoc();
  $openedTickets = (int) ($statsRow['opened_count'] ?? 0);
  $inProgTickets = (int) ($statsRow['in_progress_count'] ?? 0);
  $toValidateTickets = (int) ($statsRow['to_validate_count'] ?? 0);
}

// Requête de base pour lister les derniers tickets.
$baseSql = "
  SELECT
    t.code,
    t.title,
    p.name AS project_name,
    c.name AS client_name,
    t.status,
    t.type,
    CASE LOWER(TRIM(t.status))
      WHEN 'in progress' THEN 'in-progress'
      WHEN 'opened' THEN 'opened'
      WHEN 'wait for client' THEN 'pending'
      WHEN 'completed' THEN 'done'
      WHEN 'to validate' THEN 'to-validate'
      WHEN 'archived' THEN 'archived'
      ELSE 'pending'
    END AS status_class
  FROM tickets t
  LEFT JOIN clients c ON c.id_client = t.client_id
  LEFT JOIN projects p ON p.id = t.project_id
";

// Chargement des tickets récents.
if ($searchQuery !== '') {
  $stmt = $mysqli->prepare($baseSql . "
    WHERE t.code LIKE ? OR t.title LIKE ? OR c.name LIKE ? OR p.name LIKE ?
    ORDER BY t.id_ticket DESC
    LIMIT 5
  ");

  if ($stmt) {
    $like = '%' . $searchQuery . '%';
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $tickets[] = $row;
    }
    $stmt->close();
  }
} else {
  $result = $mysqli->query($baseSql . " ORDER BY t.id_ticket DESC LIMIT 5");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $tickets[] = $row;
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
  <title>Dashboard</title>
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
      <li><a href="./dashboard.php" class="active">Dashboard</a></li>
      <li><a href="./projects.php">Projects</a></li>
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

  <!-- En-tête avec searchbar tickets. -->
  <header class="header">
    <form method="GET" action="./dashboard.php" style="padding: 0; margin-left: 25%; box-shadow: none;">
      <input type="text" name="q" placeholder="Search for a ticket..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal du dashboard. -->
  <main>
    <h1 id="titleboard">Dashboard</h1>

    <section id="board">
      <div id="stats">
        <ul>
          <li>
            <div class="statdiv">
              <h2 class="stat-title">Open Tickets</h2>
              <p id="blueTicket"><?= e($openedTickets) ?></p>
            </div>
          </li>
          <li>
            <div class="statdiv">
              <h2 class="stat-title">In Progress</h2>
              <p id="orangeTicket"><?= e($inProgTickets) ?></p>
            </div>
          </li>
          <li>
            <div class="statdiv">
              <h2 class="stat-title">To validate</h2>
              <p id="greenTicket"><?= e($toValidateTickets) ?></p>
            </div>
          </li>
        </ul>
      </div>
    </section>

    <section id="overview">
      <div class="overview-card">
        <h3>Latest tickets</h3>
        <table class="tickets-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Project</th>
              <th>
                <select name="status" class="ticket-status">
                  <option selected>Status</option>
                  <option>Opened</option>
                  <option>In progress</option>
                  <option>Wait for client</option>
                  <option>Completed</option>
                  <option>To validate</option>
                  <option>Closed</option>
                </select>
              </th>
              <th>
                <select name="type" class="ticket-status">
                  <option selected>Type</option>
                  <option>Included</option>
                  <option>Billable</option>
                </select>
              </th>
              <th>Client</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($tickets) === 0): ?>
              <tr>
                <td colspan="5">
                  <?= $searchQuery !== '' ? 'No ticket found for "' . e($searchQuery) . '".' : 'No ticket found.' ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($tickets as $ticket): ?>
                <tr class="<?= $ticket["type"] === "Billable" ? "billable-row" : "" ?>">
                  <td><strong>#<?= e($ticket["code"]) ?></strong> - <?= e($ticket["title"]) ?></td>
                  <td><?= e($ticket["project_name"]) ?></td>
                  <td><span class="status-tag <?= e($ticket["status_class"]) ?>"><?= e($ticket["status"]) ?></span></td>
                  <td class="type">
                    <span
                      class="billing-tag <?= strtolower((string) $ticket["type"]) === 'included' ? 'included' : 'extra' ?>">
                      <?= e($ticket["type"]) ?>
                    </span>
                  </td>
                  <td><?= e($ticket["client_name"]) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <script src="./../script.js"></script>
</body>

</html>