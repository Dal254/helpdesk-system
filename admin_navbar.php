<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
<div class="container">

    <!-- LOGO -->
    <a class="navbar-brand d-flex align-items-center fw-bold">
        <img src="/helpdesk/assets/kdc_logo.png" height="40" class="me-2">
        KDC Admin
    </a>

    <!-- TOGGLER (mobile support) -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
    </button>

    <!-- MENU -->
    <div class="collapse navbar-collapse" id="navMenu">

        <ul class="navbar-nav ms-auto align-items-center">

            <li class="nav-item">
                <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
            </li>

            <li class="nav-item">
                <a href="manage_tickets.php" class="nav-link">Tickets</a>
            </li>

            <li class="nav-item">
                <a href="analytics.php" class="nav-link">Analytics</a>
            </li>

            <!-- ADD USER (highlighted) -->
            <li class="nav-item">
                <a href="add_user.php" class="btn btn-success ms-3">+ Add User</a>
            </li>

            <li class="nav-item">
                <a href="logout.php" class="btn btn-danger ms-3">Logout</a>
            </li>

        </ul>

    </div>

</div>
</nav>