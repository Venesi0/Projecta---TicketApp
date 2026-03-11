<?php
// Vérifie la session client via le guard commun.
require_once __DIR__ . '/../auth-user.php';

// Chargement des utilitaires et de la BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Initialisation des données principales de page.
$idClient = (int) $id_client;
$projectId = (int) ($_GET['id_project'] ?? 0);
$searchQuery = trim((string) ($_GET['q'] ?? ''));

$currentProject = 'Project';
$currentProjectStatus = 'Active';
$currentProjectClientId = $idClient;
$currentContractHours = 0;
$currentUsedHours = 0;
$currentClient = $clientName;

// Chargement du projet demandé (uniquement si le projet appartient au client connecté).
if ($projectId > 0) {
  $projectStmt = $mysqli->prepare("
    SELECT id, name, client_id, status, contractHours, usedHours
    FROM projects
    WHERE id = ? AND client_id = ?
    LIMIT 1
  ");
  if ($projectStmt) {
    $projectStmt->bind_param('ii', $projectId, $idClient);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    $projectRow = $projectResult ? $projectResult->fetch_assoc() : null;
    $projectStmt->close();

    if ($projectRow) {
      $projectId = (int) $projectRow['id'];
      $currentProject = (string) ($projectRow['name'] ?? 'Project');
      $currentProjectClientId = (int) ($projectRow['client_id'] ?? $idClient);
      $currentProjectStatus = (string) ($projectRow['status'] ?? 'Active');
      $currentContractHours = (int) ($projectRow['contractHours'] ?? 0);
      $currentUsedHours = (int) ($projectRow['usedHours'] ?? 0);
    } else {
      $projectId = 0;
    }
  }
}

// Fallback: premier projet du client si aucun id valide.
if ($projectId <= 0) {
  $fallbackStmt = $mysqli->prepare("
    SELECT id, name, client_id, status, contractHours, usedHours
    FROM projects
    WHERE client_id = ?
    ORDER BY id ASC
    LIMIT 1
  ");
  if ($fallbackStmt) {
    $fallbackStmt->bind_param('i', $idClient);
    $fallbackStmt->execute();
    $fallbackResult = $fallbackStmt->get_result();
    $fallbackRow = $fallbackResult ? $fallbackResult->fetch_assoc() : null;
    $fallbackStmt->close();

    if ($fallbackRow) {
      $projectId = (int) $fallbackRow['id'];
      $currentProject = (string) ($fallbackRow['name'] ?? 'Project');
      $currentProjectClientId = (int) ($fallbackRow['client_id'] ?? $idClient);
      $currentProjectStatus = (string) ($fallbackRow['status'] ?? 'Active');
      $currentContractHours = (int) ($fallbackRow['contractHours'] ?? 0);
      $currentUsedHours = (int) ($fallbackRow['usedHours'] ?? 0);
    }
  }
}

// Calcul des métriques du contrat.
$remainingHours = max(0, $currentContractHours - $currentUsedHours);
$progressPercent = getProjectProgressPercent($currentContractHours, $currentUsedHours);

// Chargement simple du nom client réel.
$clientStmt = $mysqli->prepare("SELECT name FROM clients WHERE id_client = ? LIMIT 1");
if ($clientStmt) {
  $clientStmt->bind_param('i', $currentProjectClientId);
  $clientStmt->execute();
  $clientResult = $clientStmt->get_result();
  $clientRow = $clientResult ? $clientResult->fetch_assoc() : null;
  if ($clientRow) {
    $currentClient = (string) ($clientRow['name'] ?? $currentClient);
  }
  $clientStmt->close();
}

// Chargement des collaborateurs liés au projet via collaborator_ids (JSON).
$projectCollaborators = [];
$assignedCollabs = [];
if ($projectId > 0) {
  $idsJson = null;
  $idsStmt = $mysqli->prepare("SELECT collaborator_ids FROM projects WHERE id = ? LIMIT 1");
  if ($idsStmt) {
    $idsStmt->bind_param('i', $projectId);
    $idsStmt->execute();
    $idsResult = $idsStmt->get_result();
    $idsRow = $idsResult ? $idsResult->fetch_assoc() : null;
    if ($idsRow) {
      $idsJson = $idsRow['collaborator_ids'] ?? null;
    }
    $idsStmt->close();
  }

  $collabIds = json_decode((string) $idsJson, true);
  if (is_array($collabIds) && count($collabIds) > 0) {
    $collabIds = array_values(array_filter(array_map('intval', $collabIds), fn($id) => $id > 0));
    if (count($collabIds) > 0) {
      $placeholders = implode(',', array_fill(0, count($collabIds), '?'));
      $types = str_repeat('i', count($collabIds));
      $collabStmt = $mysqli->prepare("
        SELECT id_collab, full_name, avatar_color
        FROM collaborators
        WHERE id_collab IN ($placeholders)
      ");
      if ($collabStmt) {
        $collabStmt->bind_param($types, ...$collabIds);
        $collabStmt->execute();
        $collabResult = $collabStmt->get_result();
        while ($row = $collabResult->fetch_assoc()) {
          $projectCollaborators[] = $row;
          $fullName = trim((string) ($row['full_name'] ?? ''));
          if ($fullName !== '') {
            $assignedCollabs[] = $fullName;
          }
        }
        $collabStmt->close();
      }
    }
  }
}

// Génération du prochain code ticket.
$ticketIds = [];
$ticketIdsResult = $mysqli->query("SELECT code FROM tickets ORDER BY id_ticket ASC");
if ($ticketIdsResult) {
  while ($idRow = $ticketIdsResult->fetch_assoc()) {
    $ticketIds[] = ['id' => (string) ($idRow['code'] ?? '')];
  }
}
$newId = getNextTicketId($ticketIds);

// Traitement des actions (création ticket + validation/refus).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $projectId > 0) {
  $action = trim((string) ($_POST['action'] ?? ''));
  $shouldRedirect = false;

  if ($action === 'create') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $priority = trim((string) ($_POST['priority'] ?? 'Medium'));
    $type = trim((string) ($_POST['type'] ?? 'Included'));
    $timeEstRaw = trim((string) ($_POST['time_est'] ?? '0'));
    $timeEst = ($timeEstRaw === '' ? '0' : $timeEstRaw) . 'h';

    if ($title !== '') {
      $insertStmt = $mysqli->prepare("
        INSERT INTO tickets (code, title, description, client_id, project_id, status, priority, type, time_est, time_real, created_at)
        VALUES (?, ?, ?, ?, ?, 'Opened', ?, ?, ?, '0h', CURDATE())
      ");
      if ($insertStmt) {
        $insertStmt->bind_param('sssiisss', $newId, $title, $description, $currentProjectClientId, $projectId, $priority, $type, $timeEst);
        $insertStmt->execute();
        $insertStmt->close();
        $shouldRedirect = true;
      }
    }
  } elseif ($action === 'accept') {
    $ticketId = trim((string) ($_POST['ticket_id'] ?? ''));
    if ($ticketId !== '') {
      $updateStmt = $mysqli->prepare("UPDATE tickets SET status = 'Archived' WHERE code = ? AND project_id = ?");
      if ($updateStmt) {
        $updateStmt->bind_param('si', $ticketId, $projectId);
        $updateStmt->execute();
        $updateStmt->close();

        // Incrémente le nombre de tickets pour chaque collaborateur du projet.
        $collabIdsForUpdate = [];
        foreach ($projectCollaborators as $collab) {
          $cid = (int) ($collab['id_collab'] ?? 0);
          if ($cid > 0) {
            $collabIdsForUpdate[] = $cid;
          }
        }
        $collabIdsForUpdate = array_values(array_unique($collabIdsForUpdate));

        if (count($collabIdsForUpdate) > 0) {
          $placeholders = implode(',', array_fill(0, count($collabIdsForUpdate), '?'));
          $types = str_repeat('i', count($collabIdsForUpdate));
          $incStmt = $mysqli->prepare("UPDATE collaborators SET tickets_count = tickets_count + 1 WHERE id_collab IN ($placeholders)");
          if ($incStmt) {
            $incStmt->bind_param($types, ...$collabIdsForUpdate);
            $incStmt->execute();
            $incStmt->close();
          }
        }

        $shouldRedirect = true;
      }
    }
  } elseif ($action === 'refuse') {
    $ticketId = trim((string) ($_POST['ticket_id'] ?? ''));
    if ($ticketId !== '') {
      $updateStmt = $mysqli->prepare("UPDATE tickets SET status = 'In Progress' WHERE code = ? AND project_id = ?");
      if ($updateStmt) {
        $updateStmt->bind_param('si', $ticketId, $projectId);
        $updateStmt->execute();
        $updateStmt->close();
        $shouldRedirect = true;
      }
    }
  }

  if ($shouldRedirect) {
    $redirect = './user-project-details.php?id_project=' . $projectId;
    if ($searchQuery !== '') {
      $redirect .= '&q=' . urlencode($searchQuery);
    }
    header('Location: ' . $redirect);
    exit;
  }
}

// Chargement des tickets du projet (avec recherche simple).
$projectTickets = [];
if ($projectId > 0) {
  if ($searchQuery !== '') {
    $ticketsStmt = $mysqli->prepare("
      SELECT t.code, t.title, t.description, t.status, t.priority, t.type, t.time_est, t.time_real, t.created_at, c.name AS client_name
      FROM tickets t
      LEFT JOIN clients c ON c.id_client = t.client_id
      WHERE t.project_id = ? AND (t.title LIKE ? OR t.description LIKE ? OR t.status LIKE ? OR t.priority LIKE ?)
      ORDER BY t.id_ticket DESC
    ");
    if ($ticketsStmt) {
      $like = '%' . $searchQuery . '%';
      $ticketsStmt->bind_param('issss', $projectId, $like, $like, $like, $like);
    }
  } else {
    $ticketsStmt = $mysqli->prepare("
      SELECT t.code, t.title, t.description, t.status, t.priority, t.type, t.time_est, t.time_real, t.created_at, c.name AS client_name
      FROM tickets t
      LEFT JOIN clients c ON c.id_client = t.client_id
      WHERE t.project_id = ?
      ORDER BY t.id_ticket DESC
    ");
    if ($ticketsStmt) {
      $ticketsStmt->bind_param('i', $projectId);
    }
  }

  if (isset($ticketsStmt) && $ticketsStmt) {
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
        'created_at' => (string) ($row['created_at'] ?? ''),
      ];
    }
    $ticketsStmt->close();
  }
}

// Calcul du nombre d'actions de validation.
$ticketsToValidateCount = 0;
foreach ($projectTickets as $ticket) {
  if (strtolower(trim((string) ($ticket['status'] ?? ''))) === 'to validate') {
    $ticketsToValidateCount++;
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./../styles.css" />
  <title>My Space - Projecta</title>
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

  <!-- En-tête avec fil d'Ariane et recherche tickets/projet. -->
  <header class="header">
    <div class="breadcrumb">
      <a href="./user-projects.php">Projects</a> /
      <span><?= e($currentProject) ?> Details</span>
    </div>
    <form method="GET" action="./user-project-details.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="hidden" name="id_project" value="<?= e((string) $projectId) ?>" />
      <input type="text" name="q" placeholder="Search for a ticket or a project..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal de la page détail projet. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard"><?= e($currentProject) ?></h1>
      <a href="#newTicketModal" class="btn-primary">Create a ticket</a>
    </div>

    <!-- Bloc contrat + bloc équipe projet. -->
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
            <span class="file-icon">&#128196;</span>
            <span class="file-name">contract.pdf</span>
          </a>
          <div class="data-item extra">
            <span>Overtime rate</span>
            <span class="value">15$/h</span>
          </div>
        </div>
      </div>

      <div class="info-card collaborator-list">
        <div class="collaborators-header">
          <h2>Working on this project</h2>
        </div>
        <ul>
          <?php if (count($projectCollaborators) > 0): ?>
            <?php foreach ($projectCollaborators as $collab): ?>
              <?php
              $collabName = (string) ($collab['full_name'] ?? '');
              $collabColor = (string) ($collab['avatar_color'] ?? '#919090');
              ?>
              <li>
                <span class="avatar" style="background: <?= e($collabColor) ?>"><?= e(getInitials($collabName)) ?></span>
                <?= e($collabName) ?>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li>No collaborator assigned yet.</li>
          <?php endif; ?>
        </ul>
      </div>
    </section>

    <!-- Tableau des tickets du projet. -->
    <section class="project-tickets-section">
      <div class="section-header">
        <h2>Project Tickets</h2>
        <div class="filters">
          <select name="type" class="ticket-status">
            <option value="all">Type</option>
            <option value="included">Included in Contract</option>
            <option value="billable">Extra / Billable</option>
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
            if ($statusKey === 'in progress') {
              $statusClass = 'in-progress';
            } elseif ($statusKey === 'opened') {
              $statusClass = 'opened';
            } elseif ($statusKey === 'wait for client') {
              $statusClass = 'pending';
            } elseif ($statusKey === 'to validate') {
              $statusClass = 'done';
            } else {
              $statusClass = 'pending';
            }

            $priorityKey = strtolower(trim((string) $ticket['priority']));
            if ($priorityKey === 'low') {
              $priorityClass = 'low';
            } elseif ($priorityKey === 'medium') {
              $priorityClass = 'medium';
            } elseif ($priorityKey === 'high') {
              $priorityClass = 'high';
            } elseif ($priorityKey === 'urgent') {
              $priorityClass = 'urgent';
            } else {
              $priorityClass = 'medium';
            }

            $typeKey = strtolower(trim((string) $ticket['type']));
            $typeClass = $typeKey === 'included' ? 'included' : 'extra';
            $rowClass = $typeKey === 'billable' ? 'billable-row' : '';
            $assignees = is_array($ticket['assigned_to'] ?? null) ? $ticket['assigned_to'] : [];
            $ticketSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $ticket['id']));
            $ticketModalId = 'viewTicketModal-' . $ticketSlug;
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
                      <div class="avatar" title="<?= e($assignee) ?>"><?= e(getInitials((string) $assignee)) ?></div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <span>-</span>
                <?php endif; ?>
              </td>
              <td><?= e($ticket['time_est']) ?> / <strong><?= e($ticket['time_real']) ?></strong></td>
              <td>
                <a href="#<?= e($ticketModalId) ?>" class="btn-icon-settings">&#128065;</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- Bloc tickets à valider (accept/refuse). -->
    <section id="overview" class="noFilter" style="margin-top: 40px">
      <div class="overview-card">
        <div class="card-header-flex">
          <h2>Tickets to validate</h2>
          <span class="badge-alert"><?= e((string) $ticketsToValidateCount) ?> actions required</span>
        </div>

        <table class="tickets-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Subject</th>
              <th>Priority</th>
              <th>Status</th>
              <th style="text-align: right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projectTickets as $ticket): ?>
              <?php if (strtolower(trim((string) ($ticket["status"] ?? ''))) === "to validate"): ?>

                <?php
                $priorityKey = strtolower(trim((string) $ticket['priority']));
                if ($priorityKey === 'low') {
                  $priorityClass = 'low';
                } elseif ($priorityKey === 'medium') {
                  $priorityClass = 'medium';
                } elseif ($priorityKey === 'high') {
                  $priorityClass = 'high';
                } elseif ($priorityKey === 'urgent') {
                  $priorityClass = 'urgent';
                } else {
                  $priorityClass = 'medium';
                }
                ?>

                <tr>
                  <td>#<?= e($ticket["id"]) ?></td>
                  <td><?= e($ticket["title"]) ?></td>
                  <td><span class="badge-priority <?= e($priorityClass) ?>"><?= e($ticket['priority']) ?></span></td>
                  <td>
                    <span class="status-tag done">To validate</span>
                  </td>
                  <td style="text-align: right">
                    <form action="./user-project-details.php?id_project=<?= e((string) $projectId) ?>" method="POST"
                      style="box-shadow: none; padding: 0; margin: 0;">
                      <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>">
                      <button type="submit" name="action" value="accept" class="btn-action approve">Accept</button>
                      <button type="submit" name="action" value="refuse" class="btn-action decline">Refuse</button>
                    </form>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <!-- Modales de visualisation détaillée des tickets. -->
  <?php foreach ($projectTickets as $ticket): ?>
    <?php
    $statusKey = strtolower(trim((string) ($ticket['status'] ?? '')));
    if ($statusKey === 'in progress') {
      $statusClass = 'in-progress';
    } elseif ($statusKey === 'opened') {
      $statusClass = 'opened';
    } elseif ($statusKey === 'wait for client') {
      $statusClass = 'pending';
    } elseif ($statusKey === 'to validate') {
      $statusClass = 'done';
    } elseif ($statusKey === 'completed') {
      $statusClass = 'done';
    } else {
      $statusClass = 'pending';
    }

    $priorityKey = strtolower(trim((string) ($ticket['priority'] ?? '')));
    if ($priorityKey === 'low') {
      $priorityClass = 'low';
    } elseif ($priorityKey === 'medium') {
      $priorityClass = 'medium';
    } elseif ($priorityKey === 'high') {
      $priorityClass = 'high';
    } elseif ($priorityKey === 'urgent') {
      $priorityClass = 'urgent';
    } else {
      $priorityClass = 'medium';
    }

    $typeKey = strtolower(trim((string) ($ticket['type'] ?? '')));
    $typeClass = $typeKey === 'included' ? 'included' : 'extra';

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

    $assignees = is_array($ticket['assigned_to'] ?? null) ? $ticket['assigned_to'] : [];
    $ticketSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $ticket['id']));
    $ticketModalId = 'viewTicketModal-' . $ticketSlug;
    ?>
    <div id="<?= e($ticketModalId) ?>" class="modal-overlay">
      <div class="modal-content modal-ticket">
        <div class="modal-header modal-ticket-header">
          <div class="ticket-title-header">
            <span class="badge-priority <?= e($priorityClass) ?>"><?= e($ticket['priority']) ?> Priority</span>
            <h2>#<?= e($ticket['id']) ?> - <?= e($ticket['title']) ?></h2>
          </div>
          <a href="#" class="close-modal modal-close-icon">&times;</a>
        </div>

        <div class="ticket-meta-bar">
          <div>
            <span class="ticket-meta-label">Project:</span>
            <strong><?= e($ticket['project']) ?></strong>
          </div>
          <div>
            <span class="ticket-meta-label">By:</span>
            <strong>Me (<?= e($ticket['client']) ?>)</strong>
          </div>
          <div>
            <span class="ticket-meta-label">Date:</span>
            <strong>
              <?= e(($ticket['created_at'] ?? '') !== '' ? date('M d, Y', strtotime((string) $ticket['created_at'])) : '-') ?>
            </strong>
          </div>
          <div class="ticket-meta-status">
            <span class="status-tag <?= e($statusClass) ?> status-tag-small"><?= e($ticket['status']) ?></span>
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
              <span class="billing-tag <?= e($typeClass) ?> billing-tag-small"><?= e($ticket['type']) ?></span>
            </div>

            <div class="info-row">
              <span class="ticket-meta-label">Time:</span>
              <span class="ticket-time-strong"><?= e($ticket['time_real']) ?> / <?= e($ticket['time_est']) ?></span>
            </div>

            <div class="progress-container progress-container-small">
              <div class="progress-bar progress-bar-small">
                <div class="progress-fill" style="width: <?= e((string) $progress) ?>%"></div>
              </div>
            </div>

            <div class="info-row info-row-team">
              <span class="ticket-meta-label">Team:</span>
              <div class="avatar-group">
                <?php foreach ($assignees as $assignee): ?>
                  <div class="avatar avatar-small" title="<?= e($assignee) ?>"><?= e(getInitials((string) $assignee)) ?></div>
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

  <!-- Modale de création d'un ticket client. -->
  <div id="newTicketModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 700px">
      <div class="modal-header">
        <h2>Create New Ticket Request</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>

      <form style="padding: 0; box-shadow: none; max-width: 100%" id="userTicketForm" method="POST"
        action="./user-project-details.php?id_project=<?= e((string) $projectId) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
          <label>Ticket Number</label>
          <input type="text" value="#<?= e($newId) ?>" readonly />
        </div>

        <div class="form-group">
          <label>Project</label>
          <input type="text" value="<?= e($currentProject) ?>" readonly />
        </div>

        <div class="form-group">
          <label>Subject / Title</label>
          <input type="text" name="title" placeholder="e.g., Login page is not responsive on mobile"
            id="userTicketTitle" required />
        </div>
        <div id="userTicketTitleError" class="error-text titanic">
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
          <textarea name="description" placeholder="Please describe your request in detail. Include:
- What is the issue or feature request?
- Steps to reproduce (if applicable)
- Expected behavior
- Any additional context or screenshots" style="min-height: 150px" id="userTicketDesc" required></textarea>
        </div>
        <div id="userTicketDescError" class="error-text titanic">
          Description should be at least 50 characters long.
        </div>

        <div class="form-row" style="
              background: #f8fafc;
              padding: 15px;
              border-radius: 12px;
              margin-bottom: 20px;
              border: 1px solid #e2e8f0;
            ">
          <div class="form-group" style="margin-bottom: 0">
            <label>Type of Request</label>
            <select class="custom-select-styled" name="type">
              <option value="Included" selected>Included in Contract</option>
              <option value="Billable">Additional Service (Billable)</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom: 0">
            <label>Estimated Hours (Optional)</label>
            <input type="number" name="time_est" placeholder="How long do you think this will take?" step="0.5"
              id="userTicketHours" />
          </div>
        </div>
        <div id="userTicketHoursError" class="error-text titanic">
          Should be at least 3h.
        </div>

        <div style="
              background: #fef3c7;
              border: 1px solid #fbbf24;
              padding: 12px 16px;
              border-radius: 10px;
              margin-bottom: 20px;
            ">
          <p style="font-size: 0.85rem; color: #92400e; margin: 0">
            <strong>Info:</strong> If you select "Additional Service", you
            will be charged for each hour on this ticket.
          </p>
        </div>

        <div class="modal-footer" style="
              margin-top: 30px;
              border-top: 1px solid #f1f5f9;
              padding-top: 20px;
            ">
          <a href="#" class="btn-secondary">Cancel</a>
          <button type="submit" class="btn-primary">
            Submit Ticket Request
          </button>
        </div>
      </form>
    </div>
  </div>
  <script src="./../script.js"></script>
</body>

</html>
