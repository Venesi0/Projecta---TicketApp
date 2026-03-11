<?php

// Vérifie la session client via le guard commun.
require_once __DIR__ . '/../auth-user.php';

// Chargement des utilitaires et de la connexion BDD.
require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../db.php';

// Initialisation des données de page.
$collaborators = [];
$searchQuery = trim((string) ($_GET['q'] ?? ''));

// Chargement des collaborateurs (avec filtre de recherche).
if ($searchQuery !== '') {
  $stmt = $mysqli->prepare("
    SELECT *
    FROM collaborators
    WHERE full_name LIKE ? OR position LIKE ? OR email LIKE ? OR work_status LIKE ?
    ORDER BY full_name ASC
  ");
  if ($stmt) {
    $like = '%' . $searchQuery . '%';
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $collaborators[] = [
        'name' => (string) ($row['full_name'] ?? ''),
        'status' => (string) ($row['work_status'] ?? 'Available'),
        'position' => (string) ($row['position'] ?? ''),
        'projectsNb' => (int) ($row['projects_count'] ?? 0),
        'tickets' => (int) ($row['tickets_count'] ?? 0),
        'rating' => (string) ($row['rating'] ?? '0.0'),
        'email' => (string) ($row['email'] ?? ''),
        'avatarColor' => (string) ($row['avatar_color'] ?? '#919090'),
      ];
    }
    $stmt->close();
  }
} else {
  $result = $mysqli->query("SELECT * FROM collaborators ORDER BY full_name ASC");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $collaborators[] = [
        'name' => (string) ($row['full_name'] ?? ''),
        'status' => (string) ($row['work_status'] ?? 'Available'),
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
  <link rel="stylesheet" href="./../styles.css" />
  <title>Collaborators - Projecta</title>
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
      <li><a href="./user-tickets.html">My Tickets</a></li>
      <li>
        <a href="./user-contact.php" class="active">Contact Support</a>
      </li>
      <li><a href="./user-profile.html">Profile</a></li>
      <li><a href="./user-settings.html">Settings</a></li>
      <li><a href="./../login/logpage.php">Logout</a></li>
    </ul>
    <div class="sidebar-footer user">
      <span>Connected as CLIENT</span>
    </div>
  </nav>

  <!-- En-tête avec searchbar collaborateurs. -->
  <header class="header">
    <form method="GET" action="./user-contact.php" style="padding: 0; margin: 0; box-shadow: none">
      <input type="text" name="q" placeholder="Search for a collaborator..." value="<?= e($searchQuery) ?>" />
    </form>
  </header>

  <!-- Contenu principal avec cartes collaborateurs. -->
  <main>
    <div class="page-header">
      <h1 id="titleboard">Collaborators</h1>
    </div>

    <section class="admin-clients-grid">
      <?php foreach ($collaborators as $idx => $collab): ?>
        <div class="client-card">
          <div class="client-card-header">
            <div class="client-avatar" style="background: <?= e($collab['avatarColor']) ?>">
              <?= getInitials($collab['name']) ?>
            </div>
            <div class="client-meta">
              <h2><?= e($collab['name']) ?></h2>
              <span><?= e($collab['position']) ?></span>
            </div>
            <div class="badge-status <?= strtolower(e($collab['status'])) === "available" ? "active" : "" ?>">
              <?= e($collab['status']) ?>
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
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  </main>

  <div id="addCollabModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>New Collaborator</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>
      <form>
        <div class="form-group">
          <label for="collabName">Full Name</label>
          <input type="text" id="collabName" placeholder="e.g. Robert Fox" required />
        </div>
        <div class="form-group">
          <label for="collabRole">Role / Position</label>
          <input type="text" id="collabRole" placeholder="e.g. Backend Developer" required />
        </div>
        <div class="form-group">
          <label for="collabEmail">Email Address</label>
          <input type="email" id="collabEmail" placeholder="robert@projecta.com" required />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary close-modal" style="width: auto">
            Cancel
          </button>
          <button type="submit" class="btn-primary" style="width: auto">
            Add to Team
          </button>
        </div>
      </form>
    </div>
  </div>

  <!--Modal for collaborator settings-->
  <div id="settingsCollab1" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Manage Collaborator</h2>
        <a href="#" class="close-modal">&times;</a>
      </div>

      <form>
        <div class="form-group">
          <label for="statusSelect">Work Status</label>
          <select id="statusSelect" style="width: 100%; height: 45px">
            <option value="available">Available</option>
            <option value="on-project">On Project</option>
            <option value="vacation">Vacation / Away</option>
          </select>
        </div>

        <div class="form-group">
          <label for="editRole">Update Role</label>
          <input type="text" id="editRole" value="Fullstack Developer" />
        </div>

        <div class="modal-footer" style="
              justify-content: space-between;
              align-items: center;
              margin-top: 40px;
            ">
          <button type="button" class="btn-primary alert" style="width: auto; padding: 10px 15px; font-size: 0.85rem">
            Delete Collaborator
          </button>

          <div style="display: flex; gap: 10px">
            <a href="#" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" style="width: auto">
              Save Changes
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</body>

</html>
