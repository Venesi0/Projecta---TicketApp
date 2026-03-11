<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/db.php';

// Initialisation des données d'affichage.
$collaborators = [];
$searchQuery = trim((string) ($_GET['q'] ?? ''));

// Traitement des actions POST (create / edit / delete).
if ($_SERVER['REQUEST_METHOD'] === "POST") {
  $action = $_POST['action'] ?? null;
  $collabId = (int) ($_POST['collab_id'] ?? 0);
  $shouldRedirect = false;

  if ($action === "create") {
    $fullName = trim((string) ($_POST['name'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($fullName !== '' && $position !== '' && $email !== '') {
      $createStmt = $mysqli->prepare("
        INSERT INTO collaborators (full_name, work_status, position, email, avatar_color, projects_count, tickets_count, rating)
        VALUES (?, 'Available', ?, ?, '#919090', 0, 0, 0.0)
      ");
      if ($createStmt) {
        $createStmt->bind_param('sss', $fullName, $position, $email);
        $createStmt->execute();
        $createStmt->close();
        $shouldRedirect = true;
      }
    }
  } elseif ($action === "edit" && $collabId > 0) {
    $status = trim((string) ($_POST['status'] ?? 'Available'));
    $position = trim((string) ($_POST['position'] ?? ''));
    if ($position !== '') {
      $editStmt = $mysqli->prepare("UPDATE collaborators SET work_status = ?, position = ? WHERE id_collab = ?");
      if ($editStmt) {
        $editStmt->bind_param('ssi', $status, $position, $collabId);
        $editStmt->execute();
        $editStmt->close();
        $shouldRedirect = true;
      }
    }
  } elseif ($action === "delete" && $collabId > 0) {
    $deleteStmt = $mysqli->prepare("DELETE FROM collaborators WHERE id_collab = ?");
    if ($deleteStmt) {
      $deleteStmt->bind_param('i', $collabId);
      $deleteStmt->execute();
      $deleteStmt->close();
      $shouldRedirect = true;
    }
  }

  // Redirection POST/Redirect/GET pour éviter la double soumission.
  if ($shouldRedirect) {
    $redirect = './collaborators.php';
    if ($searchQuery !== '') {
      $redirect .= '?q=' . urlencode($searchQuery);
    }
    header('Location: ' . $redirect);
    exit;
  }
}

// Chargement des collaborateurs depuis la BDD (avec filtre de recherche).
if ($searchQuery !== '') {
  $listStmt = $mysqli->prepare("
    SELECT id_collab, full_name, work_status, position, email, avatar_color, projects_count, tickets_count, rating
    FROM collaborators
    WHERE full_name LIKE ? OR position LIKE ? OR email LIKE ? OR work_status LIKE ?
    ORDER BY full_name ASC
  ");
  if ($listStmt) {
    $like = '%' . $searchQuery . '%';
    $listStmt->bind_param('ssss', $like, $like, $like, $like);
    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($row = $listResult->fetch_assoc()) {
      $collaborators[] = [
        'id_collab' => (int) ($row['id_collab'] ?? 0),
        'name' => (string) ($row['full_name'] ?? ''),
        'work_status' => (string) ($row['work_status'] ?? 'Available'),
        'position' => (string) ($row['position'] ?? ''),
        'projectsNb' => (int) ($row['projects_count'] ?? 0),
        'tickets' => (int) ($row['tickets_count'] ?? 0),
        'rating' => (string) ($row['rating'] ?? '0.0'),
        'email' => (string) ($row['email'] ?? ''),
        'avatarColor' => (string) ($row['avatar_color'] ?? '#919090'),
      ];
    }
    $listStmt->close();
  }
} else {
  $listResult = $mysqli->query("
    SELECT id_collab, full_name, work_status, position, email, avatar_color, projects_count, tickets_count, rating
    FROM collaborators
    ORDER BY full_name ASC
  ");
  if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
      $collaborators[] = [
        'id_collab' => (int) ($row['id_collab'] ?? 0),
        'name' => (string) ($row['full_name'] ?? ''),
        'work_status' => (string) ($row['work_status'] ?? 'Available'),
        'position' => (string) ($row['position'] ?? ''),
        'projectsNb' => (int) ($row['projects_count'] ?? 0),
        'tickets' => (int) ($row['tickets_count'] ?? 0),
        'rating' => (string) ($row['rating'] ?? '0.0'),
        'email' => (string) ($row['email'] ?? ''),
        'avatarColor' => (string) ($row['avatar_color'] ?? '#919090'),
      ];
    }
  }
}

?>



<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="./styles.css" />
  <title>Collaborators - Projecta</title>
  <link rel="icon" type="image/x-icon" sizes="32x32" href="./img/planet_logo_32x32.png" />
</head>

<body>
  <!-- Barre latérale de navigation globale. -->
  <nav id="sidebar">
    <div class="sidebar-logo">
      <img src="./img/planet_logo.png" alt="Logo" />
      <span>Projecta</span>
    </div>
    <ul>
      <li><a href="./admin/dashboard.php">Dashboard</a></li>
      <li><a href="./admin/projects.php">Projects</a></li>
      <li><a href="./admin/tickets.php">Tickets</a></li>
      <li><a href="./admin/admin-clients.php">Clients</a></li>
      <li><a href="./collaborators.php" class="active">Collaborators</a></li>
      <li><a href="./admin/profile.html">Profile</a></li>
      <li><a href="./admin/settings.html">Settings</a></li>
      <li><a href="./login/logpage.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer">
      <span>Connected as ADMIN</span>
    </div>
  </nav>

  <!-- En-tête avec barre de recherche collaborateurs. -->
  <header class="header">
    <form method="GET" action="./collaborators.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search for a collaborator..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal de la page collaborateurs. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard">Collaborators</h1>
      <a href="#addCollabModal" class="btn-primary">+ Add Collaborator</a>
    </div>

    <!-- Grille des cartes collaborateurs (données venant de la BDD). -->
    <section class="admin-clients-grid">
      <?php foreach ($collaborators as $collab): ?>
        <div class="client-card">
          <div class="client-card-header">
            <div class="client-avatar" style="background: <?= e($collab['avatarColor']) ?>">
              <?= getInitials($collab['name']) ?>
            </div>
            <div class="client-meta">
              <h2><?= e($collab['name']) ?></h2>
              <span><?= e($collab['position']) ?></span>
            </div>
            <div class="badge-status <?= strtolower(e($collab['work_status'])) === "available" ? "active" : "" ?>">
              <?= e($collab['work_status']) ?>
            </div>
          </div>

          <div class="client-stats-row">
            <div class="stat-item">
              <span class="stat-label">Projects</span>
              <span class="stat-value"><?= e($collab['projectsNb']) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Tickets</span>
              <span class="stat-value"><?= e($collab['tickets']) ?></span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Rating</span>
              <span class="stat-value"><?= e($collab['rating']) ?></span>
            </div>
          </div>

          <div class="client-card-footer">
            <div class="footer-actions">
              <div class="client-welcome"><?= e($collab['email']) ?></div>
            </div>
            <div class="footer-settings">
              <a href="#settingsCollab1-<?= e((string) $collab['id_collab']) ?>" class="btn-icon-settings">⚙️</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </main>

  <!-- Modale d'ajout d'un nouveau collaborateur. -->
  <div id="addCollabModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>New Collaborator</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>
      <form id="addCollabForm" method="POST">
        <div class="form-group">
          <label for="collabName">Full Name</label>
          <input type="text" name="name" id="collabName" placeholder="e.g. Robert Fox" required />
          <div id="collabNameError" class="error-text titanic">
            Please enter at least 2 words
          </div>
        </div>
        <div class="form-group">
          <label for="collabRole">Role / Position</label>
          <input type="text" name="position" id="collabRole" placeholder="e.g. Backend Developer" required />
          <div id="collabRoleError" class="error-text titanic">
            Role must be at least 4 characters
          </div>
        </div>
        <div class="form-group">
          <label for="collabEmail">Email Address</label>
          <input type="email" name="email" id="collabEmail" placeholder="robert@projecta.com" required />
          <div id="collabEmailError" class="error-text titanic">
            Email should be as follow : johndoe@gmail.com
          </div>
        </div>
        <div class="modal-footer">
          <a href="#" type="button" class="btn-secondary close-modal" style="width: auto">
            Cancel
          </a>
          <input type="hidden" name="action" value="create">
          <button type="submit" class="btn-primary" style="width: auto">
            Add to Team
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modales de gestion (édition/suppression) pour chaque collaborateur. -->
  <?php foreach ($collaborators as $collab): ?>
    <div id="settingsCollab1-<?= e((string) $collab['id_collab']) ?>" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Manage Collaborator</h2>
          <a href="#" class="close-modal">&times;</a>
        </div>

        <form method="POST" action="./collaborators.php">
          <input type="hidden" name="collab_id" value="<?= e((string) $collab['id_collab']) ?>">

          <div class="form-group">
            <label for="statusSelect-<?= e((string) $collab['id_collab']) ?>">Work Status</label>
            <select id="statusSelect-<?= e((string) $collab['id_collab']) ?>" name="status"
              style="width: 100%; height: 45px">
              <option value="Available" <?= strtolower($collab['work_status']) === 'available' ? 'selected' : '' ?>>
                Available
              </option>
              <option value="On project" <?= strtolower($collab['work_status']) === 'on project' ? 'selected' : '' ?>>
                On Project
              </option>
              <option value="Vacation / away" <?= strtolower($collab['work_status']) === 'vacation / away' ? 'selected' : '' ?>>
                Vacation / Away
              </option>
            </select>
          </div>

          <div class="form-group">
            <label for="editRole-<?= e((string) $collab['id_collab']) ?>">Update Role</label>
            <input type="text" name="position" id="editRole-<?= e((string) $collab['id_collab']) ?>"
              value="<?= e($collab['position']) ?>" />
            <div id="collabEditRoleError" class="error-text titanic">
              Role must be at least 4 characters
            </div>
          </div>

          <div class="modal-footer" style="justify-content: space-between; align-items: center; margin-top: 40px;">
            <!-- Button DELETE -->
            <button type="submit" name="action" value="delete" class="btn-primary alert"
              style="width: auto; padding: 10px 15px; font-size: 0.85rem; border: none; background: red;"
              onclick="return confirm('Delete this collaborator ?')">
              Delete Collaborator
            </button>

            <div style="display: flex; gap: 10px">
              <a href="#" class="btn-secondary">Cancel</a>

              <!-- Button EDIT -->
              <button type="submit" name="action" value="edit" class="btn-primary" style="width: auto">
                Save Changes
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

  <?php endforeach; ?>
  <!-- Script global (gestion UI/modales/validations front). -->
  <script src="./script.js"></script>
</body>

</html>
