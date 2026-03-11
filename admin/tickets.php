<?php

// Vérifie la session admin.
require_once __DIR__ . '/../auth-admin.php';

require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

$openedTickets = 0;
$inProgTickets = 0;
$toValidateTickets = 0;
$completedTickets = 0;

// Structures de données utilisées pour le rendu de la page.
$projects = [];
$tickets = [];
$newId = 'TK-2001';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$createClientError = '';
$showCreateModal = false;

// Détection de la colonne d'identifiant des collaborateurs.
$collabIdColumn = null;
$collabIdColumnCheck = $mysqli->query("SHOW COLUMNS FROM collaborators LIKE 'id_collab'");
if ($collabIdColumnCheck && $collabIdColumnCheck->num_rows > 0) {
  $collabIdColumn = 'id_collab';
}

// Construction d'un mapping id collaborateur -> nom complet.
$collaboratorNameById = [];
if ($collabIdColumn !== null) {
  $collabResult = $mysqli->query("SELECT {$collabIdColumn} AS cid, full_name FROM collaborators");
  if ($collabResult) {
    while ($collabRow = $collabResult->fetch_assoc()) {
      $collaboratorNameById[(int) $collabRow['cid']] = (string) ($collabRow['full_name'] ?? '');
    }
  }
}

// Vérifie si le projet stocke un tableau JSON d'IDs collaborateurs.
$hasProjectCollaboratorIds = false;
$projectCollaboratorIdsCheck = $mysqli->query("SHOW COLUMNS FROM projects LIKE 'collaborator_ids'");
if ($projectCollaboratorIdsCheck && $projectCollaboratorIdsCheck->num_rows > 0) {
  $hasProjectCollaboratorIds = true;
}

// Traitement des actions POST (update, archive, create).
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $action = $_POST["action"] ?? null;
  $ticketId = trim((string) ($_POST["ticket_id"] ?? ""));
  $status = trim((string) ($_POST["status"] ?? ""));
  $spentTime = trim((string) ($_POST["spentTime"] ?? ""));
  $shouldRedirect = false;

  // Mise à jour du statut/temps dépensé d'un ticket.
  if ($action === 'update' && $ticketId !== '' && $status !== '') {
    $updateStmt = $mysqli->prepare("UPDATE tickets SET status = ?, time_real = ? WHERE code = ?");
    if ($updateStmt) {
      $timeReal = $spentTime === '' ? '0h' : ($spentTime . 'h');
      $updateStmt->bind_param('sss', $status, $timeReal, $ticketId);
      $updateStmt->execute();
      $updateStmt->close();
      $shouldRedirect = true;
    }
    // Archivage d'un ticket.
  } elseif ($action === 'archive' && $ticketId !== '') {
    $archiveStmt = $mysqli->prepare("UPDATE tickets SET status = 'Archived' WHERE code = ?");
    if ($archiveStmt) {
      $archiveStmt->bind_param('s', $ticketId);
      $archiveStmt->execute();
      $archiveStmt->close();
      $shouldRedirect = true;
    }
    // Création d'un ticket 
  } elseif ($action === 'create') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['desc'] ?? ''));
    $clientName = trim((string) ($_POST['client'] ?? ''));
    $priority = trim((string) ($_POST['priority'] ?? 'Medium'));
    $type = trim((string) ($_POST['type'] ?? 'Included'));
    $projectId = (int) ($_POST['project_id'] ?? 0);
    $timeEstInput = trim((string) ($_POST['time_est'] ?? '0'));
    $timeEst = $timeEstInput === '' ? '0h' : ($timeEstInput . 'h');

    if ($title !== '' && $projectId > 0) {
      $clientId = null;
      $projectClientStmt = $mysqli->prepare("SELECT client_id FROM projects WHERE id = ? LIMIT 1");
      if ($projectClientStmt) {
        $projectClientStmt->bind_param('i', $projectId);
        $projectClientStmt->execute();
        $projectClientResult = $projectClientStmt->get_result();
        $projectClientRow = $projectClientResult ? $projectClientResult->fetch_assoc() : null;
        if ($projectClientRow) {
          $clientId = (int) $projectClientRow['client_id'];
        }
        $projectClientStmt->close();
      }

      // Vérifie que le client saisi existe et correspond bien au projet choisi.
      $typedClientId = null;
      if ($clientName === '') {
        $createClientError = 'Please enter a client name.';
      } else {
        $clientLookupStmt = $mysqli->prepare("SELECT id_client FROM clients WHERE name = ? LIMIT 1");
        if ($clientLookupStmt) {
          $clientLookupStmt->bind_param('s', $clientName);
          $clientLookupStmt->execute();
          $clientLookupResult = $clientLookupStmt->get_result();
          $clientLookupRow = $clientLookupResult ? $clientLookupResult->fetch_assoc() : null;
          if ($clientLookupRow) {
            $typedClientId = (int) $clientLookupRow['id_client'];
          }
          $clientLookupStmt->close();
        }
        if ($typedClientId === null) {
          $createClientError = 'Client not found. Please choose an existing client.';
        } elseif ($clientId !== null && $typedClientId !== $clientId) {
          $createClientError = 'Selected client does not match the selected project.';
        }
      }

      // Génère le prochain code ticket et insère la ligne en base.
      if ($createClientError === '' && $clientId !== null) {
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
          $createStmt->bind_param('sssiisss', $newCode, $title, $description, $clientId, $projectId, $priority, $type, $timeEst);
          $createStmt->execute();
          $createStmt->close();
          $shouldRedirect = true;
        }
      } else {
        $showCreateModal = true;
      }
    }
  }

  // Redirection pour éviter les doubles soumissions.
  if ($shouldRedirect) {
    header('Location: ./tickets.php');
    exit;
  }
}

// Chargement de la liste des projets pour le formulaire de création.
$projectListResult = $mysqli->query("
  SELECT p.id, p.name, c.name AS client_name
  FROM projects p
  LEFT JOIN clients c ON c.id_client = p.client_id
  ORDER BY p.id ASC
");
if ($projectListResult) {
  while ($projectRow = $projectListResult->fetch_assoc()) {
    $projects[] = $projectRow;
  }
}

// Calcul des statistiques globales affichées en haut de page.
$statsResult = $mysqli->query("
  SELECT
    SUM(CASE WHEN status = 'Opened' THEN 1 ELSE 0 END) AS opened_count,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress_count,
    SUM(CASE WHEN status = 'To Validate' THEN 1 ELSE 0 END) AS to_validate_count,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
  FROM tickets
");
if ($statsResult) {
  $statsRow = $statsResult->fetch_assoc();
  $openedTickets = (int) ($statsRow['opened_count'] ?? 0);
  $inProgTickets = (int) ($statsRow['in_progress_count'] ?? 0);
  $toValidateTickets = (int) ($statsRow['to_validate_count'] ?? 0);
  $completedTickets = (int) ($statsRow['completed_count'] ?? 0);
}

// Construction de la requête tickets (+ jointures client/projet).
$collaboratorIdsSelect = $hasProjectCollaboratorIds ? ", p.collaborator_ids AS collaborator_ids" : "";
$ticketsSql = "
  SELECT
    t.code,
    t.title,
    t.description,
    t.created_at,
    t.status,
    t.priority,
    t.type,
    t.time_est,
    t.time_real,
    p.name AS project_name$collaboratorIdsSelect,
    c.name AS client_name
  FROM tickets t
  LEFT JOIN projects p ON p.id = t.project_id
  LEFT JOIN clients c ON c.id_client = t.client_id
";

// Application du filtre de recherche (GET q) si présent.
$ticketsResult = null;
if ($searchQuery !== '') {
  $ticketsStmt = $mysqli->prepare($ticketsSql . "
    WHERE t.code LIKE ? OR t.title LIKE ? OR c.name LIKE ? OR p.name LIKE ?
    ORDER BY t.id_ticket DESC
  ");
  if ($ticketsStmt) {
    $like = '%' . $searchQuery . '%';
    $ticketsStmt->bind_param('ssss', $like, $like, $like, $like);
    $ticketsStmt->execute();
    $ticketsResult = $ticketsStmt->get_result();
  }
} else {
  $ticketsResult = $mysqli->query($ticketsSql . " ORDER BY t.id_ticket DESC");
}

// Normalisation des lignes SQL vers la structure attendue par le rendu.
if ($ticketsResult) {
  while ($row = $ticketsResult->fetch_assoc()) {
    $assignedTo = [];
    if ($hasProjectCollaboratorIds) {
      $collaboratorIds = json_decode((string) ($row['collaborator_ids'] ?? ''), true);
      if (is_array($collaboratorIds)) {
        foreach ($collaboratorIds as $collaboratorId) {
          $cid = (int) $collaboratorId;
          if (isset($collaboratorNameById[$cid]) && $collaboratorNameById[$cid] !== '') {
            $assignedTo[] = $collaboratorNameById[$cid];
          }
        }
      }
    }

    $tickets[] = [
      'id' => (string) ($row['code'] ?? ''),
      'title' => (string) ($row['title'] ?? ''),
      'description' => (string) ($row['description'] ?? ''),
      'created_at' => (string) ($row['created_at'] ?? ''),
      'client' => (string) ($row['client_name'] ?? ''),
      'project' => (string) ($row['project_name'] ?? ''),
      'status' => (string) ($row['status'] ?? ''),
      'priority' => (string) ($row['priority'] ?? ''),
      'type' => (string) ($row['type'] ?? ''),
      'assigned_to' => $assignedTo,
      'time_est' => (string) ($row['time_est'] ?? '0h'),
      'time_real' => (string) ($row['time_real'] ?? '0h'),
    ];
  }
}

// Pré-calcul de l'ID ticket suivant pour l'affichage dans le modal de création.
$newId = getNextTicketId($tickets);
?>

<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>Tickets Management - Projecta</title>
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
      <li><a href="#" class="active">Tickets</a></li>
      <li><a href="./admin-clients.php">Clients</a></li>
      <li><a href="./../collaborators.php">Collaborators</a></li>
      <li><a href="./profile.html">Profile</a></li>
      <li><a href="./settings.html">Settings</a></li>
      <li><a href="./../logout.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer">
      <span>Connected as ADMIN</span>
    </div>
  </nav>

  <!-- En-tête avec barre de recherche tickets. -->
  <header class="header">
    <div class="client-welcome">Global Ticket Overview</div>
    <form method="GET" action="./tickets.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search by ticket ID, title or client..."
        value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <main>
    <!-- Titre de page et action de création. -->
    <div class="page-header">
      <h1 id="titleboard">Tickets Pipeline</h1>
      <div class="header-actions" style="position: relative">
        <a href="#newTicketModal" class="btn-primary">+ Create Ticket</a>
      </div>
    </div>

    <!-- Cartes de statistiques globales. -->
    <section id="board">
      <div id="stats">
        <ul>
          <li class="statdiv">
            <h2>New</h2>
            <span id="blueTicket"><?= e($openedTickets) ?></span>
          </li>
          <li class="statdiv">
            <h2>In Progress</h2>
            <span id="orangeTicket"><?= e($inProgTickets) ?></span>
          </li>
          <li class="statdiv">
            <h2>To Validate</h2>
            <span id="greenTicket"><?= e($toValidateTickets) ?></span>
          </li>
          <li class="statdiv">
            <h2>Completed</h2>
            <span style="color: #64748b; font-size: 1.75rem; font-weight: 700"><?= e($completedTickets) ?></span>
          </li>
        </ul>
      </div>
    </section>

    <!-- Tableau principal des tickets avec filtres front. -->
    <section class="overview-card">
      <div class="card-header-flex">
        <h3>Recent Tickets</h3>
        <div class="filter-group">
          <select id="projectSelect">
            <option>All Projects</option>
            <?php foreach ($projects as $project): ?>
              <option><?= e((string) ($project['name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <table class="tickets-table tickets-only">
        <thead>
          <tr>
            <th>Ticket</th>
            <th>
              <select name="status" class="ticket-status">
                <option selected>Status</option>
                <option>Opened</option>
                <option>In progress</option>
                <option>Wait for client</option>
                <option>Completed</option>
                <option>To validate</option>
                <option>Archived</option>
              </select>
            </th>
            <th>
              <select name="priority" class="ticket-status">
                <option selected>Priority</option>
                <option>Low</option>
                <option>Medium</option>
                <option>High</option>
                <option>Urgent</option>
              </select>
            </th>
            <th>
              <select name="type" class="ticket-status">
                <option selected>Type</option>
                <option>Included</option>
                <option>Billable</option>
              </select>
            </th>
            <th>Assigned To</th>
            <th>Time (Est/Real)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $ticket): ?>
            <?php
            $statusKey = strtolower(trim((string) $ticket['status']));
            $statusClass = match ($statusKey) {
              'in progress' => 'in-progress',
              'opened' => 'opened',
              'wait for client' => 'pending',
              'completed' => 'done',
              'to validate' => 'to-validate',
              'archived' => 'archived',
              default => 'pending',
            };

            $priorityKey = strtolower(trim((string) $ticket['priority']));
            $priorityClass = match ($priorityKey) {
              'low' => 'low',
              'medium' => 'medium',
              'high' => 'high',
              'urgent' => 'urgent',
              default => 'medium',
            };

            $typeKey = strtolower(trim((string) $ticket['type']));
            $typeClass = $typeKey === 'included' ? 'included' : 'extra';
            $isArchived = strtolower(trim((string) $ticket["status"])) === "archived";
            $rowClass = trim(string: ($typeKey === 'billable' ? 'billable-row ' : '') . ($isArchived ? 'archived-row' : ''));

            $assignees = is_array($ticket['assigned_to'] ?? null) ? $ticket['assigned_to'] : [];
            $isMultiAssignees = count($assignees) > 1;
            $isSingleAssignee = count($assignees) === 1;
            $avatarColors = [null, '#10b981', '#f59e0b', '#3b82f6', '#ef4444'];
            ?>
            <tr class="<?= e($rowClass) ?>">
              <td>
                <div class="ticket-info-cell">
                  <strong>#<?= e($ticket['id']) ?> - <?= e($ticket['title']) ?></strong>
                  <span>Client: <?= e($ticket['client']) ?> | <?= e($ticket['project']) ?></span>
                </div>
              </td>
              <td>
                <span class="status-tag <?= e($statusClass) ?>"><?= e($ticket['status']) ?></span>
              </td>
              <td>
                <span class="badge-priority <?= e($priorityClass) ?>"><?= e($ticket['priority']) ?></span>
              </td>
              <td class="type">
                <span class="billing-tag <?= e($typeClass) ?>"><?= e($ticket['type']) ?></span>
              </td>
              <td>
                <?php if ($isMultiAssignees): ?>
                  <div class="avatar-group">
                    <?php foreach ($assignees as $idx => $assignee): ?>
                      <?php
                      $initials = getInitials($assignee);
                      $color = $avatarColors[$idx] ?? '#64748b';
                      ?>
                      <div class="avatar" <?= $color ? ' style="background: ' . e($color) . '"' : '' ?>
                        title="<?= e($assignee) ?>">
                        <?= e($initials) ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php elseif ($isSingleAssignee): ?>
                  <?php
                  $assignee = $assignees[0];
                  $initials = getInitials($assignee);
                  ?>
                  <div class="avatar-group">
                    <div class="avatar" title="<?= e($assignee) ?>"><?= e($initials) ?></div>
                  </div>
                <?php else: ?>
                  <span>-</span>
                <?php endif; ?>
              </td>
              <td><?= e($ticket['time_est']) ?> / <strong><?= e($ticket['time_real']) ?></strong></td>
              <td>
                <?php
                $ticketSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $ticket['id']);
                $ticketModalId = 'viewTicketModal-' . strtolower((string) $ticketSlug);
                ?>
                <a href="#<?= e($ticketModalId) ?>" class="btn-icon-settings">&#128065;</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Modales de consultation/gestion, une par ticket. -->
  <?php foreach ($tickets as $ticket): ?>
    <?php
    $ticketSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $ticket['id']);
    $ticketModalId = 'viewTicketModal-' . strtolower((string) $ticketSlug);
    $ticketFormId = 'adminTicketUpdateForm-' . strtolower((string) $ticketSlug);
    $statusValue = strtolower(trim((string) $ticket['status']));
    $timeRealValue = rtrim((string) $ticket['time_real'], 'h');
    $createdAtDisplay = 'N/A';
    if (!empty($ticket['created_at'])) {
      $createdAtTs = strtotime((string) $ticket['created_at']);
      if ($createdAtTs !== false) {
        $createdAtDisplay = date('M d, Y', $createdAtTs);
      }
    }
    ?>
    <div id="<?= e($ticketModalId) ?>" class="modal-overlay">
      <div class="modal-content" style="max-width: 800px">
        <div class="modal-header">
          <h2>Ticket #<?= e($ticket['id']) ?> - <?= e($ticket['title']) ?></h2>
          <a href="#" class="close-modal">&times;</a>
        </div>

        <div class="details-grid">
          <div class="info-card">
            <h3>Description</h3>
            <textarea readonly style="min-height: 120px"><?= e($ticket["description"]) ?></textarea>

            <div class="contract-data" style="margin-top: 20px">
              <div class="data-item">
                <span>Created By</span>
                <div class="value" style="font-size: 1rem"><?= e($ticket['client']) ?></div>
              </div>
              <div class="data-item">
                <span>Date</span>
                <div class="value" style="font-size: 1rem"><?= e($createdAtDisplay) ?></div>
              </div>
            </div>
          </div>

          <div class="info-card">
            <h3>Management</h3>
            <form style="padding: 0; box-shadow: none" id="<?= e($ticketFormId) ?>" class="admin-ticket-update-form"
              method="POST" action="./tickets.php">
              <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>" />
              <div class="form-group">
                <label>Status Cycle</label>
                <select style="width: 100%" name="status">
                  <option value="Opened" <?= $statusValue === 'opened' ? 'selected' : '' ?>>Opened</option>
                  <option value="In Progress" <?= $statusValue === 'in progress' ? 'selected' : '' ?>>In Progress</option>
                  <option value="Wait for Client" <?= $statusValue === 'wait for client' ? 'selected' : '' ?>>Wait for Client
                  </option>
                  <option value="To Validate" <?= $statusValue === 'to validate' ? 'selected' : '' ?>>To Validate (Client)
                  </option>
                  <option value="Completed" <?= $statusValue === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
              </div>
              <div class="form-group">
                <label>Actual Time Spent (Hours)</label>
                <input type="number" name="spentTime" value="<?= e($timeRealValue) ?>" step="0.5" />
              </div>
              <button type="submit" name="action" value="update" class="btn-primary">Update Ticket</button>
            </form>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" name="action" value="archive" form="<?= e($ticketFormId) ?>"
            class="btn-reject archive-ticket-btn" style="margin-right: auto">
            Archive Ticket
          </button>
          <a href="#" class="btn-secondary">Close</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Modal de création de ticket. -->
  <div id="newTicketModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 900px">
      <div class="modal-header">
        <h2>Create Ticket</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>

      <form style="padding: 0; box-shadow: none; max-width: 100%" id="ticketForm" method="POST"
        action="./tickets.php#newTicketModal">
        <div class="form-row" style="
              background: #f8fafc;
              padding: 15px;
              border-radius: 12px;
              margin-bottom: 25px;
              border: 1px solid #e2e8f0;
            ">
          <div class="form-group" style="margin-bottom: 0">
            <label>Ticket Number</label>
            <input type="text" name="id" value="#<?= e($newId) ?>" readonly class="readonly-input" />
          </div>
        </div>

        <div style="gap: 30px">
          <div class="form-section">
            <div class="form-group">
              <label>Ticket Title</label>
              <input type="text" name="title" id="ticketTitle" placeholder="Enter ticket title..." required />
            </div>
            <div id="titleError" class="error-text titanic">
              ⚠️Title should be at least 15 characters long.
            </div>

            <div class="form-group">
              <label>Description</label>
              <textarea id="ticketDesc" name="desc" placeholder="Technical details and requirements..."
                style="min-height: 120px"></textarea>
            </div>
            <div id="descError" class="error-text titanic">
              ⚠️Description should be at least 30 characters long.
            </div>
            <div class="form-group">
              <label>Client</label>
              <input type="text" name="client" id="ticketClient" placeholder="Client name" />
            </div>
            <div id="clientError" class="error-text <?= $createClientError !== '' ? '' : 'titanic' ?>">
              <?= e($createClientError !== '' ? $createClientError : 'Client name should be at least 4 characters long.') ?>
            </div>
          </div>

          <div class="form-section">
            <div class="form-row">
              <div class="form-group">
                <label>Project</label>
                <select name="project_id" class="custom-select-styled">
                  <?php foreach ($projects as $project): ?>
                    <option value="<?= e((string) ($project['id'] ?? '')) ?>"
                      data-client="<?= e((string) ($project['client_name'] ?? '')) ?>">
                      #<?= e((string) ($project['id'] ?? '')) ?> - <?= e((string) ($project['name'] ?? '')) ?>
                      <?php if (!empty($project['client_name'])): ?>
                        (<?= e((string) $project['client_name']) ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Priority</label>
                <select class="custom-select-styled" name="priority">
                  <option>Low</option>
                  <option selected>Medium</option>
                  <option>High</option>
                  <option>Urgent</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Type</label>
                <select class="custom-select-styled" name="type">
                  <option>Included</option>
                  <option>Billable</option>
                </select>
              </div>
              <div class="form-group">
                <label>Est. Hours</label>
                <input name="time_est" type="number" placeholder="0" id="ticketHours" />
              </div>
              <div id="hourError" class="error-text titanic">
                ⚠️Should be at least 3h.
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer" style="
              margin-top: 30px;
              border-top: 1px solid #f1f5f9;
              padding-top: 20px;
            ">
          <a href="#" class="btn-secondary">Cancel</a>
          <input type="hidden" name="action" value="create">
          <button type="submit" name="action" value="create" class="btn-primary">Generate Ticket</button>
        </div>
      </form>
    </div>
  </div>
  <!-- Réouvre le modal de création en cas d'erreur serveur sur create. -->
  <?php if ($showCreateModal): ?>
    <script>
      window.location.hash = 'newTicketModal';
    </script>
  <?php endif; ?>
  <!-- Préremplissage automatique du client selon le projet sélectionné. -->
  <script>
    (function () {
      const projectSelect = document.querySelector('select[name="project_id"]');
      const clientInput = document.querySelector('#ticketClient');
      if (!projectSelect || !clientInput) return;

      const syncClient = () => {
        const selected = projectSelect.options[projectSelect.selectedIndex];
        if (!selected) return;
        clientInput.value = selected.dataset.client || '';
      };

      projectSelect.addEventListener('change', syncClient);
      if (clientInput.value.trim() === '') syncClient();
    })();
  </script>
  <script src="./../script.js"></script>
</body>

</html>