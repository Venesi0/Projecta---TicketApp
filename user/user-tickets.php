<?php
// Vérifie la session client via le guard commun.
require_once __DIR__ . '/../auth-user.php';

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Initialisation des variables de page.
$idClient = (int) $id_client;
$searchQuery = trim((string) ($_GET['q'] ?? ''));

$tickets = [];
$projects = [];
$projectOptions = [];

// Mapping des collaborateurs (id -> nom) pour l'affichage des avatars.
$collaboratorNameById = [];
$collabResult = $mysqli->query("SELECT id_collab, full_name FROM collaborators");
if ($collabResult) {
  while ($collabRow = $collabResult->fetch_assoc()) {
    $collaboratorNameById[(int) ($collabRow['id_collab'] ?? 0)] = (string) ($collabRow['full_name'] ?? '');
  }
}

// Chargement des projets du client (utilisés dans le formulaire de création).
$projectIdsOwned = [];
$projectOptionsStmt = $mysqli->prepare("
  SELECT id, name, collaborator_ids
  FROM projects
  WHERE client_id = ?
  ORDER BY id ASC
");
if ($projectOptionsStmt) {
  $projectOptionsStmt->bind_param('i', $idClient);
  $projectOptionsStmt->execute();
  $projectOptionsResult = $projectOptionsStmt->get_result();
  while ($projectRow = $projectOptionsResult->fetch_assoc()) {
    $projectOptions[] = [
      'id' => (int) ($projectRow['id'] ?? 0),
      'name' => (string) ($projectRow['name'] ?? ''),
      'collaborator_ids' => (string) ($projectRow['collaborator_ids'] ?? '[]'),
    ];
    $projectIdsOwned[] = (int) ($projectRow['id'] ?? 0);
  }
  $projectOptionsStmt->close();
}

// Traitement du formulaire de création de ticket.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? null;
  if ($action === "create") {
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['desc'] ?? ''));
    $priority = trim((string) ($_POST['priority'] ?? 'Medium'));
    $type = trim((string) ($_POST['type'] ?? 'Included'));
    $timeEstRaw = trim((string) ($_POST['time_est'] ?? '0'));
    $timeEst = ($timeEstRaw === '' ? '0' : $timeEstRaw) . 'h';

    if ($title !== '' && in_array($projectId, $projectIdsOwned, true)) {
      $nextCodeResult = $mysqli->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)), 2000) AS max_code
        FROM tickets
        WHERE code REGEXP '^TK-[0-9]+$'
      ");
      $maxCode = 2000;
      if ($nextCodeResult) {
        $nextCodeRow = $nextCodeResult->fetch_assoc();
        $maxCode = (int) ($nextCodeRow['max_code'] ?? 2000);
      }
      $newCode = 'TK-' . (string) ($maxCode + 1);

      $createStmt = $mysqli->prepare("
        INSERT INTO tickets (code, title, description, client_id, project_id, status, priority, type, time_est, time_real, created_at)
        VALUES (?, ?, ?, ?, ?, 'Opened', ?, ?, ?, '0h', CURDATE())
      ");
      if ($createStmt) {
        $createStmt->bind_param('sssiisss', $newCode, $title, $description, $idClient, $projectId, $priority, $type, $timeEst);
        $createStmt->execute();
        $createStmt->close();
      }
    }

    $redirect = './user-tickets.php';
    if ($searchQuery !== '') {
      $redirect .= '?q=' . urlencode($searchQuery);
    }
    header('Location: ' . $redirect);
    exit();
  }
}

// Requête de base pour charger les tickets du client.
$ticketsSql = "
  SELECT
    t.code,
    t.title,
    t.description,
    t.status,
    t.priority,
    t.type,
    t.time_est,
    t.time_real,
    t.created_at,
    p.name AS project_name,
    p.collaborator_ids,
    c.name AS client_name
  FROM tickets t
  INNER JOIN projects p ON p.id = t.project_id
  LEFT JOIN clients c ON c.id_client = t.client_id
  WHERE p.client_id = ?
";

// Chargement des tickets (avec ou sans recherche).
if ($searchQuery !== '') {
  $ticketsStmt = $mysqli->prepare($ticketsSql . "
    AND (t.code LIKE ? OR t.title LIKE ? OR t.description LIKE ? OR p.name LIKE ?)
    ORDER BY t.id_ticket DESC
  ");
  if ($ticketsStmt) {
    $like = '%' . $searchQuery . '%';
    $ticketsStmt->bind_param('issss', $idClient, $like, $like, $like, $like);
  }
} else {
  $ticketsStmt = $mysqli->prepare($ticketsSql . " ORDER BY t.id_ticket DESC");
  if ($ticketsStmt) {
    $ticketsStmt->bind_param('i', $idClient);
  }
}

if (isset($ticketsStmt) && $ticketsStmt) {
  $ticketsStmt->execute();
  $ticketsResult = $ticketsStmt->get_result();
  while ($row = $ticketsResult->fetch_assoc()) {
    $assignedTo = [];
    $collabIds = json_decode((string) ($row['collaborator_ids'] ?? '[]'), true);
    if (is_array($collabIds)) {
      foreach ($collabIds as $cidRaw) {
        $cid = (int) $cidRaw;
        if (isset($collaboratorNameById[$cid]) && $collaboratorNameById[$cid] !== '') {
          $assignedTo[] = $collaboratorNameById[$cid];
        }
      }
    }

    $createdAt = (string) ($row['created_at'] ?? '');
    $createdDisplay = $createdAt !== '' ? date('M d, Y', strtotime($createdAt)) : '-';

    $tickets[] = [
      'id' => (string) ($row['code'] ?? ''),
      'title' => (string) ($row['title'] ?? ''),
      'project' => (string) ($row['project_name'] ?? ''),
      'status' => (string) ($row['status'] ?? ''),
      'priority' => (string) ($row['priority'] ?? ''),
      'type' => (string) ($row['type'] ?? ''),
      'created' => $createdDisplay,
      'updated' => 'Updated on ' . $createdDisplay,
      'client' => (string) ($row['client_name'] ?? $clientName),
      'description' => (string) ($row['description'] ?? ''),
      'assigned_to' => $assignedTo,
      'time_real' => (string) ($row['time_real'] ?? '0h'),
      'time_est' => (string) ($row['time_est'] ?? '0h'),
    ];
  }
  $ticketsStmt->close();
}

$statusClassMap = [
  'opened' => 'opened',
  'open' => 'opened',
  'in progress' => 'in-progress',
  'wait for client' => 'pending',
  'to validate' => 'done',
  'archived' => 'archived',
];

$priorityClassMap = [
  'low' => 'low',
  'medium' => 'medium',
  'high' => 'high',
  'urgent' => 'urgent',
];

// Calcul des statistiques de statut affichées en haut.
$stats = [
  ['label' => 'Open', 'count' => 0, 'id' => 'blueTicket', 'style' => ''],
  ['label' => 'In Progress', 'count' => 0, 'id' => 'orangeTicket', 'style' => ''],
  ['label' => 'Awaiting Validation', 'count' => 0, 'id' => 'greenTicket', 'style' => ''],
  ['label' => 'Archived', 'count' => 0, 'id' => '', 'style' => 'color: #64748b; font-size: 1.75rem; font-weight: 700'],
];

foreach ($tickets as $ticket) {
  $statusKey = strtolower(trim((string) ($ticket['status'] ?? '')));

  if ($statusKey === 'open' || $statusKey === 'opened') {
    $stats[0]['count']++;
  } elseif ($statusKey === 'in progress') {
    $stats[1]['count']++;
  } elseif ($statusKey === 'to validate') {
    $stats[2]['count']++;
  } elseif ($statusKey === 'archived') {
    $stats[3]['count']++;
  }
}

// Préparation de la liste de projets pour les filtres front.
$projects = [];
foreach ($tickets as $ticket) {
  $projectName = (string) ($ticket['project'] ?? '');
  if ($projectName !== '' && !in_array($projectName, $projects, true)) {
    $projects[] = $projectName;
  }
}

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>My Tickets - Projecta</title>
  <link rel="icon" type="image/x-icon" sizes="32x32" href="./../img/planet_logo_32x32.png" />
</head>

<body>
  <!-- Navigation latérale de l'espace client. -->
  <nav id="sidebar">
    <div class="sidebar-logo">
      <img src="./../img/planet_logo.png" alt="Logo" />
      <span>Projecta</span>
    </div>
    <ul>
      <li><a href="./user-projects.php">My Projects</a></li>
      <li><a href="./user-tickets.php" class="active">My Tickets</a></li>
      <li><a href="./user-contact.php">Contact Support</a></li>
      <li><a href="./user-profile.html">Profile</a></li>
      <li><a href="./user-settings.html">Settings</a></li>
      <li><a href="./../logout.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer user">
      <span>Connected as CLIENT</span>
    </div>
  </nav>

  <!-- En-tête avec searchbar tickets. -->
  <header class="header">
    <div class="client-welcome">My Tickets Overview</div>
    <form method="GET" action="./user-tickets.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search by ticket ID or subject..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal: stats + tableau des tickets. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard">My Tickets</h1>
      <a href="#newTicketModal" class="btn-primary">+ New Ticket Request</a>
    </div>

    <section id="board">
      <div id="stats">
        <ul>
          <?php foreach ($stats as $stat): ?>
            <li class="statdiv">
              <h2><?= e($stat['label']) ?></h2>
              <span<?= $stat['id'] !== '' ? ' id="' . e($stat['id']) . '"' : '' ?><?= $stat['style'] !== '' ? ' style="' . e($stat['style']) . '"' : '' ?>><?= e((string) $stat['count']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="overview-card">
      <div class="card-header-flex">
        <h3>All My Tickets</h3>
        <div class="filter-group">
          <select id="projectFilter">
            <option>All Projects</option>
            <?php foreach ($projects as $project): ?>
              <option><?= e($project) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="statusFilter">
            <option>All Statuses</option>
            <option>Open</option>
            <option>In Progress</option>
            <option>Wait for Client</option>
            <option>To validate</option>
            <option>Archived</option>
          </select>
        </div>
      </div>

      <table class="tickets-table">
        <thead>
          <tr>
            <th>Ticket</th>
            <th>Project</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Type</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $ticket): ?>
            <?php
            $statusKey = strtolower(trim((string) ($ticket['status'] ?? '')));
            $statusClass = $statusClassMap[$statusKey] ?? 'pending';

            $priorityKey = strtolower(trim((string) ($ticket['priority'] ?? '')));
            $priorityClass = $priorityClassMap[$priorityKey] ?? 'medium';

            $typeKey = strtolower(trim((string) ($ticket['type'] ?? '')));
            $typeClass = $typeKey === 'included' ? 'included' : 'extra';
            $rowClass = $typeKey === 'billable' ? 'billable-row' : '';

            $ticketSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($ticket['id'] ?? '')));
            $ticketModalId = 'viewTicketModal-' . $ticketSlug;
            ?>
            <tr class="<?= e($rowClass) ?>">
              <td>
                <div class="ticket-info-cell">
                  <strong>#<?= e((string) $ticket['id']) ?> - <?= e((string) $ticket['title']) ?></strong>
                  <span><?= e((string) $ticket['updated']) ?></span>
                </div>
              </td>
              <td><?= e((string) $ticket['project']) ?></td>
              <td><span class="status-tag <?= e($statusClass) ?>"><?= e((string) $ticket['status']) ?></span></td>
              <td><span class="badge-priority <?= e($priorityClass) ?>"><?= e((string) $ticket['priority']) ?></span></td>
              <td><span class="billing-tag <?= e($typeClass) ?>"><?= e((string) $ticket['type']) ?></span></td>
              <td><?= e((string) $ticket['created']) ?></td>
              <td>
                <a href="#<?= e($ticketModalId) ?>" class="btn-icon-settings">&#128065;</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <?php foreach ($tickets as $ticket): ?>
    <?php
    $statusKey = strtolower(trim((string) ($ticket['status'] ?? '')));
    $statusClass = $statusClassMap[$statusKey] ?? 'pending';

    $priorityKey = strtolower(trim((string) ($ticket['priority'] ?? '')));
    $priorityClass = $priorityClassMap[$priorityKey] ?? 'medium';

    $typeKey = strtolower(trim((string) ($ticket['type'] ?? '')));
    $typeClass = $typeKey === 'included' ? 'included' : 'extra';

    $ticketSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($ticket['id'] ?? '')));
    $ticketModalId = 'viewTicketModal-' . $ticketSlug;

    $timeRealNum = (float) str_replace(',', '.', preg_replace('/[^0-9.]/', '', (string) ($ticket['time_real'] ?? '0')));
    $timeEstNum = (float) str_replace(',', '.', preg_replace('/[^0-9.]/', '', (string) ($ticket['time_est'] ?? '0')));
    $progress = 0;
    if ($timeEstNum > 0) {
      $progress = (int) round(($timeRealNum / $timeEstNum) * 100);
      if ($progress < 0) {
        $progress = 0;
      }
      if ($progress > 100) {
        $progress = 100;
      }
    }
    ?>
    <div id="<?= e($ticketModalId) ?>" class="modal-overlay">
      <div class="modal-content modal-ticket">
        <div class="modal-header modal-ticket-header">
          <div class="ticket-title-header">
            <span class="badge-priority <?= e($priorityClass) ?>"><?= e((string) $ticket['priority']) ?> Priority</span>
            <h2>#<?= e((string) $ticket['id']) ?> - <?= e((string) $ticket['title']) ?></h2>
          </div>
          <a href="#" class="close-modal modal-close-icon">&times;</a>
        </div>

        <div class="ticket-meta-bar">
          <div>
            <span class="ticket-meta-label">Project:</span>
            <strong><?= e((string) $ticket['project']) ?></strong>
          </div>
          <div>
            <span class="ticket-meta-label">By:</span>
            <strong>Me (<?= e((string) $ticket['client']) ?>)</strong>
          </div>
          <div>
            <span class="ticket-meta-label">Date:</span>
            <strong><?= e((string) $ticket['created']) ?></strong>
          </div>
          <div class="ticket-meta-status">
            <span class="status-tag <?= e($statusClass) ?> status-tag-small"><?= e((string) $ticket['status']) ?></span>
          </div>
        </div>

        <div class="ticket-details-grid">
          <div class="info-card">
            <h3 class="info-card-title">Description</h3>
            <div class="info-card-description"><?= nl2br(e((string) $ticket['description'])) ?></div>
          </div>

          <div class="info-card info-card-side">
            <div class="info-row">
              <span class="ticket-meta-label">Type:</span>
              <span class="billing-tag <?= e($typeClass) ?> billing-tag-small"><?= e((string) $ticket['type']) ?></span>
            </div>

            <div class="info-row">
              <span class="ticket-meta-label">Time:</span>
              <span class="ticket-time-strong"><?= e((string) $ticket['time_real']) ?> /
                <?= e((string) $ticket['time_est']) ?></span>
            </div>

            <div class="progress-container progress-container-small">
              <div class="progress-bar progress-bar-small">
                <div class="progress-fill" style="width: <?= e((string) $progress) ?>%"></div>
              </div>
            </div>

            <div class="info-row info-row-team">
              <span class="ticket-meta-label">Team:</span>
              <div class="avatar-group">
                <?php foreach (($ticket['assigned_to'] ?? []) as $assignee): ?>
                  <div class="avatar avatar-small" title="<?= e((string) $assignee) ?>">
                    <?= e(getInitials((string) $assignee)) ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer modal-ticket-footer">
          <a href="./user-contact.php" class="btn-outline btn-small">Support</a>
          <a href="#" class="btn-secondary btn-small">Close</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div id="newTicketModal" class="modal-overlay">
    <div class="modal-content modal-ticket modal-ticket-create">
      <div class="modal-header">
        <h2>Create New Ticket Request</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>

      <form class="ticket-form" id="userTicketsForm" method="POST" action="./user-tickets.php">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label>Project</label>
          <select class="custom-select-styled" name="project_id" required>
            <option value="">-- Select a project --</option>
            <?php foreach ($projectOptions as $project): ?>
              <option value="<?= e((string) $project['id']) ?>"><?= e((string) $project['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Subject / Title</label>
          <input type="text" name="title" placeholder="e.g., Login page is not responsive on mobile"
            id="userTicketsTitle" required />
        </div>
        <div id="userTicketsTitleError" class="error-text titanic">
          Title should be at least 6 characters long.
        </div>

        <div class="form-group">
          <label>Priority Level</label>
          <select class="custom-select-styled" name="priority" required>
            <option value="" selected disabled>Select priority...</option>
            <option value="Low">Low - Can wait</option>
            <option value="Medium">Medium - Normal priority</option>
            <option value="High">High - Important</option>
            <option value="Urgent">Urgent - Blocking issue</option>
          </select>
        </div>

        <div class="form-group">
          <label>Description</label>
          <textarea name="desc" placeholder="Please describe your request in detail. Include:
- What is the issue or feature request?
- Steps to reproduce (if applicable)
- Expected behavior
- Any additional context or screenshots" id="userTicketsDesc" required></textarea>
        </div>
        <div id="userTicketsDescError" class="error-text titanic">
          Description should be at least 50 characters long.
        </div>

        <div class="form-row ticket-form-row">
          <div class="form-group form-group-inline">
            <label>Type of Request</label>
            <select class="custom-select-styled" name="type">
              <option value="Included" selected>Included in Contract</option>
              <option value="Billable">Additional Service (Billable)</option>
            </select>
          </div>
          <div class="form-group form-group-inline">
            <label>Estimated Hours (Optional)</label>
            <input type="number" name="time_est" placeholder="How long do you think this will take?" step="0.5"
              id="userTicketsHours" />
          </div>
        </div>
        <div id="userTicketsHoursError" class="error-text titanic">
          Should be at least 3h.
        </div>

        <div class="ticket-warning-box">
          <p>
            <strong>Info:</strong>
            If you select "Additional Service", you will be charged for each
            hour on this ticket.
          </p>
        </div>

        <div class="modal-footer modal-ticket-footer modal-ticket-footer-create">
          <a href="#" class="btn-secondary">Cancel</a>
          <button type="submit" class="btn-primary">
            Submit Ticket Request
          </button>
        </div>
      </form>
    </div>
  </div>

  <div id="userTicketToast" class="toast toast-success">
    Ticket submitted successfully.
  </div>
  <script src="./../script.js"></script>
</body>

</html>
