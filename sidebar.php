<?php
// Sidebar for Hospital Appraisal System (Professional Design)
?>
<div class="sidebar p-3">
    <div class="text-center mb-4">
        <i class="fas fa-hospital fa-2x mb-2"></i>
        <h5>St. Mary's Hospital</h5>
        <small>Appraisal System</small>
    </div>
    <div class="text-center mb-4">
        <div class="user-avatar mx-auto mb-2">
            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
        </div>
        <div>
            <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
            <br><small><?php echo ucfirst($_SESSION['role']); ?></small>
        </div>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link" href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <a class="nav-link" href="manage_users.php">
            <i class="fas fa-users"></i> Manage Users
        </a>
        <a class="nav-link" href="view_all_appraisals.php">
            <i class="fas fa-clipboard-list"></i> All Appraisals
        </a>
        <a class="nav-link" href="reports.php">
            <i class="fas fa-chart-bar"></i> System Reports
        </a>
        <?php elseif ($_SESSION['role'] == 'appraiser'): ?>
        <a class="nav-link" href="my_appraisals.php">
            <i class="fas fa-clipboard-check"></i> My Appraisals
        </a>
        <a class="nav-link" href="create_appraisal.php">
            <i class="fas fa-plus"></i> New Appraisal
        </a>
        <?php else: ?>
        <a class="nav-link" href="my_appraisals.php">
            <i class="fas fa-user-edit"></i> My Appraisals
        </a>
        <?php endif; ?>
        <a class="nav-link" href="performance_planning.php">
            <i class="fas fa-bullseye"></i> Performance Planning
        </a>
        <a class="nav-link" href="reports.php">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <hr>
        <a class="nav-link" href="profile.php">
            <i class="fas fa-user"></i> Profile
        </a>
        <a class="nav-link" href="index.php?logout=1">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>
