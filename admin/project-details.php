<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifie la session admin.
require_once __DIR__ . '/../auth-admin.php';

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Initialisation des données principales de page.
$projectId = (int) ($_GET['id_project'] ?? 0);
$currentProject = 'Unknown Project';
$currentProjectClientId = null;
$currentProjectStatus = 'Active';
$currentContractHours = 0;
$currentUsedHours = 0;
$remainingHours = 0;
$progressPercent = 0;

$currentClient = '';

// Chargement du projet demandé.
if ($projectId > 0) {
  $projectStmt = $mysqli->prepare("SELECT id, name, client_id, status, contractHours, usedHours FROM projects WHERE id = ? LIMIT 1");
  if ($projectStmt) {
    $projectStmt->bind_param('i', $projectId);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    $projectRow = $projectResult ? $projectResult->fetch_assoc() : null;
    $projectStmt->close();

    if ($projectRow) {
      $projectId = (int) $projectRow['id'];
      $currentProject = (string) $projectRow['name'];
      $currentProjectClientId = (int) $projectRow['client_id'];
      $currentProjectStatus = (string) ($projectRow['status'] ?? 'Active');
      $currentContractHours = (int) ($projectRow['contractHours'] ?? 0);
      $currentUsedHours = (int) ($projectRow['usedHours'] ?? 0);
    }
  }
}

// Fallback: charge un projet par défaut si l'id est invalide.
if ($projectId <= 0) {
  $fallbackResult = $mysqli->query("SELECT id, name, client_id, status, contractHours, usedHours FROM projects ORDER BY id ASC LIMIT 1");
  if ($fallbackResult) {
    $fallbackRow = $fallbackResult->fetch_assoc();
    if ($fallbackRow) {
      $projectId = (int) $fallbackRow['id'];
      $currentProject = (string) $fallbackRow['name'];
      $currentProjectClientId = (int) $fallbackRow['client_id'];
      $currentProjectStatus = (string) ($fallbackRow['status'] ?? 'Active');
      $currentContractHours = (int) ($fallbackRow['contractHours'] ?? 0);
      $currentUsedHours = (int) ($fallbackRow['usedHours'] ?? 0);
    }
  }
}

// Calcul des métriques contrat/progression.
$remainingHours = max(0, $currentContractHours - $currentUsedHours);
$progressPercent = getProjectProgressPercent($currentContractHours, $currentUsedHours);
$editProjectNameError = false;
$editProjectHoursError = false;
$editProjectNameValue = $currentProject;
$editProjectHoursValue = (string) $currentContractHours;
$editProjectStatusValue = $currentProjectStatus;


// Chargement du nom du client du projet.
$clientNameStmt = $mysqli->prepare("SELECT name FROM clients WHERE id_client = ? LIMIT 1");
$clientNameStmt->bind_param('i', $currentProjectClientId);
$clientNameStmt->execute();
$clientNameResult = $clientNameStmt->get_result();
if ($clientNameRow = $clientNameResult->fetch_assoc()) {
  $currentClient = (string) $clientNameRow['name'];
}
$clientNameStmt->close();


// Chargement des collaborateurs associés au projet.
$projectCollaborators = [];
if ($projectId > 0) {
  $collabIdsJson = null;
  $collabIdsStmt = $mysqli->prepare("SELECT collaborator_ids FROM projects WHERE id = ? LIMIT 1");
  if ($collabIdsStmt) {
    $collabIdsStmt->bind_param('i', $projectId);
    $collabIdsStmt->execute();
    $collabIdsResult = $collabIdsStmt->get_result();
    $collabIdsRow = $collabIdsResult ? $collabIdsResult->fetch_assoc() : null;
    if ($collabIdsRow) {
      $collabIdsJson = $collabIdsRow['collaborator_ids'] ?? null;
    }
    $collabIdsStmt->close();
  }

  $collabIds = json_decode((string) $collabIdsJson, true);
  if (is_array($collabIds) && count($collabIds) > 0) {
    $collabIds = array_values(array_filter(array_map('intval', $collabIds), fn($id) => $id > 0));
    if (count($collabIds) > 0) {
      $placeholders = implode(',', array_fill(0, count($collabIds), '?'));
      $types = str_repeat('i', count($collabIds));
      $collabStmt = $mysqli->prepare("
        SELECT id_collab, full_name, position, email, avatar_color
        FROM collaborators
        WHERE id_collab IN ($placeholders)
      ");
      if ($collabStmt) {
        $collabStmt->bind_param($types, ...$collabIds);
        $collabStmt->execute();
        $collabResult = $collabStmt->get_result();
        while ($row = $collabResult->fetch_assoc()) {
          $projectCollaborators[] = $row;
        }
        $collabStmt->close();
      }
    }
  }
}


// Préparation du prochain code ticket.
$ticketIds = [];
$ticketIdsResult = $mysqli->query("SELECT code FROM tickets ORDER BY id_ticket ASC");
if ($ticketIdsResult) {
  while ($idRow = $ticketIdsResult->fetch_assoc()) {
    $ticketIds[] = ['id' => (string) ($idRow['code'] ?? '')];
  }
}
$newId = getNextTicketId($ticketIds);

// Traitement des actions POST (edit projet, update/archive/create ticket).
if ($_SERVER["REQUEST_METHOD"] === "POST" && $projectId > 0) {
  $shouldRedirect = false;
  $action = $_POST["action"] ?? null;
  $ticketId = trim((string) ($_POST["ticket_id"] ?? ""));
  $status = trim((string) ($_POST["status"] ?? ""));
  $spentTime = trim((string) ($_POST["spentTime"] ?? ""));

  if ($action === 'edit_project') {
    $editedName = trim((string) ($_POST['projectName'] ?? ''));
    $editedHours = (int) ($_POST['contractHours'] ?? 0);
    $editedStatus = trim((string) ($_POST['projectStatus'] ?? $currentProjectStatus));
    $allowedStatuses = ['Active', 'Ready', 'Archived'];
    $editProjectNameValue = $editedName;
    $editProjectHoursValue = (string) $editedHours;
    $editProjectStatusValue = $editedStatus;
    $editProjectNameError = strlen($editedName) < 6;
    $editProjectHoursError = $editedHours < $currentUsedHours;

    if (!$editProjectNameError && !$editProjectHoursError && in_array($editedStatus, $allowedStatuses, true)) {
      $editStmt = $mysqli->prepare("UPDATE projects SET name = ?, status = ?, contractHours = ? WHERE id = ?");
      if ($editStmt) {
        $editStmt->bind_param('ssii', $editedName, $editedStatus, $editedHours, $projectId);
        $editStmt->execute();
        $editStmt->close();
        $shouldRedirect = true;
      }
    }
  } elseif ($action === 'update' && $ticketId !== '' && $status !== '') {
    $updateStmt = $mysqli->prepare("UPDATE tickets SET status = ?, time_real = ? WHERE code = ?");
    if ($updateStmt) {
      $timeReal = $spentTime === '' ? '0h' : ($spentTime . 'h');
      $updateStmt->bind_param('sss', $status, $timeReal, $ticketId);
      $updateStmt->execute();
      $updateStmt->close();
      $shouldRedirect = true;
    }
  } elseif ($action === 'archive' && $ticketId !== '') {
    $archiveStmt = $mysqli->prepare("UPDATE tickets SET status = 'Archived' WHERE code = ?");
    if ($archiveStmt) {
      $archiveStmt->bind_param('s', $ticketId);
      $archiveStmt->execute();
      $archiveStmt->close();
      $shouldRedirect = true;
    }
  } elseif ($action === 'create') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['desc'] ?? ''));
    $clientName = trim((string) ($_POST['client'] ?? ''));
    $priority = trim((string) ($_POST['priority'] ?? 'Medium'));
    $type = trim((string) ($_POST['type'] ?? 'Included'));
    $timeEstInput = trim((string) ($_POST['time_est'] ?? '0'));
    $timeEst = $timeEstInput === '' ? '0h' : ($timeEstInput . 'h');

    if ($title !== '') {
      $clientId = $currentProjectClientId;

      if ($clientName !== '') {
        $clientStmt = $mysqli->prepare("SELECT id_client FROM clients WHERE name = ? LIMIT 1");
        if ($clientStmt) {
          $clientStmt->bind_param('s', $clientName);
          $clientStmt->execute();
          $clientResult = $clientStmt->get_result();
          $clientRow = $clientResult ? $clientResult->fetch_assoc() : null;
          if ($clientRow) {
            $clientId = (int) $clientRow['id_client'];
          }
          $clientStmt->close();
        }
      }

      if ($clientId !== null) {
        $createStmt = $mysqli->prepare("
          INSERT INTO tickets (code, title, description, client_id, project_id, status, priority, type, time_est, time_real, created_at)
          VALUES (?, ?, ?, ?, ?, 'Opened', ?, ?, ?, '0h', CURDATE())
        ");
        if ($createStmt) {
          $createStmt->bind_param('sssiisss', $newId, $title, $description, $clientId, $projectId, $priority, $type, $timeEst);
          $createStmt->execute();
          $createStmt->close();
          $shouldRedirect = true;
        }
      }
    }
  }

  if ($shouldRedirect) {
    header('Location: ./project-details.php?id_project=' . $projectId);
    exit;
  }
}


// Construction de la liste des collaborateurs assignés.
$assignedCollabs = [];
$collaboratorColorByName = [];
foreach ($projectCollaborators as $collab) {
  $fullName = trim((string) ($collab['full_name'] ?? ''));
  if ($fullName === '') {
    continue;
  }
  $assignedCollabs[] = $fullName;
  $collaboratorColorByName[strtolower($fullName)] = (string) ($collab['avatar_color'] ?? '#64748b');
}

// Chargement des tickets du projet.
$projectTickets = [];
if ($projectId > 0) {
  $ticketsStmt = $mysqli->prepare("
    SELECT t.code, t.title, t.description, t.status, t.priority, t.type, t.time_est, t.time_real, c.name AS client_name
    FROM tickets t
    LEFT JOIN clients c ON c.id_client = t.client_id
    WHERE t.project_id = ?
    ORDER BY t.id_ticket DESC
  ");

  if ($ticketsStmt) {
    $ticketsStmt->bind_param('i', $projectId);
    $ticketsStmt->execute();
    $ticketsResult = $ticketsStmt->get_result();

    while ($row = $ticketsResult->fetch_assoc()) {
      $projectTickets[] = [
        'id' => (string) ($row['code'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'client' => (string) ($row['client_name'] ?? ''),
        'project' => $currentProject,
        'status' => (string) ($row['status'] ?? ''),
        'priority' => (string) ($row['priority'] ?? ''),
        'type' => (string) ($row['type'] ?? ''),
        'assigned_to' => $assignedCollabs,
        'time_est' => (string) ($row['time_est'] ?? '0h'),
        'time_real' => (string) ($row['time_real'] ?? '0h'),
      ];
    }
    $ticketsStmt->close();
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>Project Details - Projecta</title>
  <link rel="icon" type="image/x-icon" sizes="32x32" href="./../img/planet_logo_32x32.png" />
</head>

<body>
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

  <header class="header">
    <div class="breadcrumb">
      <a href="./projects.php">Projects</a> / <span>Project <?= e($projectId) ?> Details</span>
    </div>
    <input type="text" placeholder="Search in this project..." />
  </header>

  <main>
    <div class="page-header">
      <div>
        <h1 id="titleboard">Project <?= e($projectId) ?> : <?= e($currentProject) ?></h1>
        <p class="client-subtitle">Client: <strong><?= e($currentClient) ?></strong></p>
      </div>
      <div class="action-buttons">
        <!-- <button class="btn-outline">Edit Project</button> -->
        <a href="#projectModal" class="btn-outline">Edit Project</a>
        <!-- <button class="btn-primary">+ Add Ticket</button> -->
        <a href="#newTicketModal" class="btn-primary">+ add ticket</a>
      </div>
    </div>

    <section class="details-grid">
      <div class="info-card contract-summary">
        <h2>Contract Status</h2>
        <div class="contract-data">
          <div class="data-item">
            <span>Included Hours</span>
            <span class="value"><?= e((string) $currentContractHours) ?>h</span>
          </div>
          <div class="data-item">
            <span>Consumed</span>
            <span class="value"><?= e((string) $currentUsedHours) ?>h</span>
          </div>
          <div class="data-item highlight">
            <span>Remaining</span>
            <span class="value"><?= e((string) $remainingHours) ?>h</span>
          </div>
        </div>
        <div class="progress-container large">
          <div class="progress-bar">
            <div class="progress-fill" style="width: <?= e((string) $progressPercent) ?>%"></div>
          </div>
          <p class="progress-text"><?= e((string) $progressPercent) ?>% of the budget used</p>
        </div>
        <div class="info-card-footer">
          <a href="contract.pdf" class="file-link" target="_blank">
            See contract :
            <span class="file-icon">📄</span>
            <span class="file-name">contract.pdf</span>
          </a>
          <div class="data-item extra">
            <span>Overtime rate</span>
            <span class="value">$15/h</span>
          </div>
        </div>
      </div>

      <div class="info-card collaborator-list">
        <div class="collaborators-header">
          <h2>Collaborators</h2>
          <span class="avatar"><a href="#projectModalcollab">+</a></span>
        </div>
        <ul>
          <?php foreach ($projectCollaborators as $collab): ?>
            <?php
            $collabName = (string) ($collab['full_name'] ?? '');
            $collabColor = (string) ($collab['avatar_color'] ?? '#64748b');
            ?>
            <li>
              <span class="avatar" style="background: <?= e($collabColor) ?>"><?= getInitials($collabName) ?></span>
              <?= e($collabName) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="project-tickets-section">
      <div class="section-header">
        <h2>Project Tickets</h2>
        <div class="filters">
          <select name="type" class="ticket-status">
            <option value="all">Type</option>
            <option value="included">Included</option>
            <option value="billable">Billable</option>
          </select>
        </div>
      </div>

      <table class="tickets-table tickets-only">
        <thead>
          <tr>
            <th>Ticket</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Type</th>
            <th>Assigned To</th>
            <th>Time (Est/Real)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projectTickets as $ticket): ?>
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
            ?>
            <tr class="<?= e($rowClass) ?>">
              <td>
                <div class="ticket-info-cell">
                  <strong>#<?= e($ticket['id']) ?> - <?= e($ticket['title']) ?></strong>
                  <span>Client: <?= e($ticket['client']) ?></span>
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
                <?php if (count($assignees) > 0): ?>
                  <div class="avatar-group">
                    <?php foreach ($assignees as $assignee): ?>
                      <?php
                      $assigneeName = trim((string) $assignee);
                      $assigneeColor = $collaboratorColorByName[strtolower($assigneeName)] ?? '#64748b';
                      ?>
                      <div class="avatar" style="background: <?= e($assigneeColor) ?>" title="<?= e($assigneeName) ?>">
                        <?= getInitials($assigneeName) ?>
                      </div>
                    <?php endforeach; ?>
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

    <?php foreach ($projectTickets as $ticket): ?>
      <?php
      $ticketSlug = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $ticket['id']);
      $ticketModalId = 'viewTicketModal-' . strtolower((string) $ticketSlug);
      $ticketFormId = 'adminTicketUpdateForm-' . strtolower((string) $ticketSlug);
      $statusValue = strtolower(trim((string) $ticket['status']));
      $timeRealValue = rtrim((string) $ticket['time_real'], 'h');
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
                  <div class="value" style="font-size: 1rem">Oct 24, 2025</div>
                </div>
              </div>
            </div>

            <div class="info-card">
              <h3>Management</h3>
              <form style="padding: 0; box-shadow: none" id="<?= e($ticketFormId) ?>" class="admin-ticket-update-form"
                method="POST" action="./project-details.php?id_project=<?= e((string) $projectId) ?>">
                <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>" />
                <div class="form-group">
                  <label>Status Cycle</label>
                  <select style="width: 100%" name="status">
                    <option value="Opened" <?= $statusValue === 'opened' ? 'selected' : '' ?>>Opened</option>
                    <option value="In Progress" <?= $statusValue === 'in progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Wait for Client" <?= $statusValue === 'wait for client' ? 'selected' : '' ?>>Wait for
                      Client</option>
                    <option value="To Validate" <?= $statusValue === 'to validate' ? 'selected' : '' ?>>To Validate
                      (Client)</option>
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

    <div id="projectModal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Edit project</h2>
          <!-- <span class="close-modal">&times;</span> -->
          <a href="#" class="close-modal">&times;</a>
        </div>
        <form id="editProjectForm" method="POST"
          action="./project-details.php?id_project=<?= e((string) $projectId) ?>#projectModal">
          <input type="hidden" name="action" value="edit_project" />
          <div class="form-group">
            <label for="projectName">Project name</label>
            <input type="text" id="projectName" name="projectName" value="<?= e($editProjectNameValue) ?>" required />
          </div>
          <div id="nameError" class="error-text <?= $editProjectNameError ? '' : 'titanic' ?>">
            Project Name must be at least 6 characters long
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="contractHours">Redefine Hours</label>
              <input type="number" id="contractHours" name="contractHours" value="<?= e($editProjectHoursValue) ?>"
                required />
            </div>
            <div id="hoursError" class="error-text <?= $editProjectHoursError ? '' : 'titanic' ?>">
              Must be greater than or equal to consumed hours (<?= e((string) $currentUsedHours) ?>h)
            </div>

            <div class="form-group">
              <label for="projectStatus">Edit status</label>
              <select id="projectStatus" name="projectStatus">
                <option value="Active" <?= $editProjectStatusValue === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Ready" <?= $editProjectStatusValue === 'Ready' ? 'selected' : '' ?>>Ready</option>
                <option value="Archived" <?= $editProjectStatusValue === 'Archived' ? 'selected' : '' ?>>Archived</option>
              </select>
            </div>
          </div>

          <div class="modal-footer">
            <a href="#" type="button" class="btn-secondary close-modal">
              Cancel
            </a>
            <button type="submit" class="btn-primary">Update project</button>
          </div>
        </form>
      </div>
    </div>

    <div id="projectModalcollab" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Add a collaborator</h2>
          <!-- <span class="close-modal">&times;</span> -->
          <a href="#" class="close-modal">&times;</a>
        </div>
        <form id="createCollabForm">
          <div class="form-group">
            <label for="collabName">Search by name</label>
            <input type="text" id="collabName" placeholder="Ex: Baptiste Rault" required />
          </div>

          <div class="modal-footer">
            <a href="#" type="button" class="btn-secondary close-modal">
              Cancel
            </a>
            <button type="submit" class="btn-primary">Add</button>
          </div>
        </form>
      </div>
    </div>
    <div id="newTicketModal" class="modal-overlay">
      <div class="modal-content" style="max-width: 900px">
        <div class="modal-header">
          <h2>Create Ticket</h2>
          <a href="#" class="close-modal">&times;</a>
        </div>

        <form style="padding: 0; box-shadow: none; max-width: 100%" id="ticketForm" method="POST"
          action="./project-details.php?id_project=<?= e((string) $projectId) ?>">
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
            <div class="form-group" style="margin-bottom: 0">
              <label>Project</label>
              <input type="text" value="<?= e($currentProject) ?>" readonly class="readonly-input" />
            </div>
          </div>

          <div style="gap: 30px">
            <div class="form-section">
              <div class="form-group">
                <label>Ticket Title</label>
                <input type="text" name="title" id="ticketTitle" placeholder="Enter title..." required />
              </div>
              <div id="titleError" class="error-text titanic">
                Title should be at least 15 characters long.
              </div>

              <div class="form-group">
                <label>Description</label>
                <textarea id="ticketDesc" name="desc" placeholder="Technical details and requirements..."
                  style="min-height: 120px"></textarea>
              </div>
              <div id="descError" class="error-text titanic">
                Description should be at least 30 characters long.
              </div>

              <div class="form-group">
                <label>Client</label>
                <input type="text" name="client" value="<?= e($currentClient) ?>" id="ticketClient" readonly
                  class="readonly-input" />
              </div>
              <div id="clientError" class="error-text titanic">
                Client name should be at least 4 characters long.
              </div>
            </div>

            <div class="form-section">
              <div class="form-row">
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
                  Should be at least 3h.
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
            <input type="hidden" name="action" value="create" />
            <input type="hidden" name="project" value="<?= e($currentProject) ?>" />
            <button type="submit" name="action" value="create" class="btn-primary">Generate Ticket</button>
          </div>
        </form>
      </div>
    </div>
  </main>
  <script src="./../script.js"></script>
</body>

</html>