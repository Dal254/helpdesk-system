<?php
session_start();
include 'db.php';

/* PROTECT ADMIN */
if(!isset($_SESSION['role']) || strcasecmp($_SESSION['role'], 'Admin') !== 0){
    header("Location: login.php");
    exit();
}

/* TOTAL STATS */
$totalTickets = $conn->query("SELECT COUNT(*) as c FROM tickets")->fetch_assoc()['c'];
$openTickets  = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='Open'")->fetch_assoc()['c'];
$resolvedTickets = $conn->query("SELECT COUNT(*) as c FROM tickets WHERE status='Resolved'")->fetch_assoc()['c'];

/* TOTAL CHATS - use your analytics code here */
$totalChats = 0; 
// Example: if you have a function getChatCount() in your analytics code
// $totalChats = getChatCount($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $status  = ($_POST['action'] === 'Disable') ? 'Disabled' : 'Active';

    $stmt = $conn->prepare("UPDATE users SET account_status=? WHERE id=?");
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius: 10px; }
        h5 { font-size: 15px; margin-bottom: 10px; }
        .form-select, .form-control {
            font-size: 12px;
            padding: 3px 5px;
            border-radius: 4px;
        }
        .btn {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .section-card {
            max-width: 900px;
            margin: auto;
        }
    </style>
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="container mt-4">

    <h3 class="text-center mb-4">Admin Dashboard</h3>

    <!-- STATS -->
<div class="row mb-4">

    <div class="col-md-3">
        <a href="manage_tickets.php" style="text-decoration:none; color:inherit;">
            <div class="card shadow p-3 text-center">
                <h6>Total Tickets</h6>
                <h3><?= $totalTickets ?></h3>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="manage_tickets.php?status=Open" style="text-decoration:none; color:inherit;">
            <div class="card shadow p-3 text-center">
                <h6>Open Tickets</h6>
                <h3><?= $openTickets ?></h3>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="manage_tickets.php?status=Resolved" style="text-decoration:none; color:inherit;">
            <div class="card shadow p-3 text-center">
                <h6>Resolved Tickets</h6>
                <h3><?= $resolvedTickets ?></h3>
            </div>
        </a>
    </div>

    <div class="col-md-3">
        <a href="analytics.php" style="text-decoration:none; color:inherit;">
            <div class="card shadow p-3 text-center">
                <h6>Total Chats</h6>
                <h3><?= $totalChats ?></h3>
            </div>
        </a>
    </div>

</div>

    <!-- BUTTONS -->
    <div class="d-flex justify-content-between mb-4">
        <a href="manage_tickets.php" class="btn btn-primary">Manage Tickets</a>
        <a href="analytics.php" class="btn btn-info">Analytics</a>
    </div>

    <!-- SYSTEM REPORTS -->
     <div class="card shadow p-3 mb-4 section-card">
    <h5>System Reports</h5>
    <form method="GET" action="download_report.php" class="row g-2">

        <input type="hidden" name="report_category" value="system">

        <div class="col-md-3">
            <select name="report_type" class="form-select" required>
                <option value="">Select Report</option>
                <option value="executive">Executive (All + Charts)</option>
                <option value="resolved">Resolved Only</option>
                <option value="pending">Pending Only</option>
                <option value="open">Open Only</option>
            </select>
        </div>

        <div class="col-md-2">
            <input type="date" name="from_date" class="form-control" placeholder="From">
        </div>

        <div class="col-md-2">
            <input type="date" name="to_date" class="form-control" placeholder="To">
        </div>

        <div class="col-md-2">
            <select name="format" class="form-select" required>
                <option value="excel">Excel (.xlsx)</option>
                <option value="pdf">PDF</option>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-success w-100">Export</button>
        </div>

    </form>
</div>

<!-- INDIVIDUAL USER REPORT -->
<div class="card shadow p-3 mb-4 section-card">
    <h5>Individual User Report</h5>
    <form method="GET" action="download_report.php" class="row g-2">

        <input type="hidden" name="report_category" value="user">

        <div class="col-md-3">
            <select name="user_id" class="form-select" required>
                <option value="">Select User</option>
                <?php
                $users = $conn->query("SELECT id, name FROM users WHERE role != 'Admin' ORDER BY name");
                while ($u = $users->fetch_assoc()) {
                    echo "<option value='{$u['id']}'>{$u['name']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-2">
            <input type="date" name="from_date" class="form-control">
        </div>

        <div class="col-md-2">
            <input type="date" name="to_date" class="form-control">
        </div>

        <div class="col-md-2">
            <select name="format" class="form-select">
                <option value="excel">Excel (.xlsx)</option>
                <option value="pdf">PDF</option>
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">Export</button>
        </div>

    </form>
</div>

    <!-- USER MANAGEMENT -->
    <div class="card shadow p-3 section-card">
        <h5>Manage Users</h5>

        <?php
        $res = $conn->query("SELECT * FROM users");
        echo "<table class='table table-bordered table-sm'>";
        echo "<tr><th>Name</th><th>Email</th><th>Dept</th><th>Status</th><th>Action</th></tr>";

        while($row = $res->fetch_assoc()){
            echo "<tr>
                <td>{$row['name']}</td>
                <td>{$row['email']}</td>
                <td>{$row['department']}</td>
                <td>{$row['account_status']}</td>
                <td>
                    <form method='POST' style='display:inline'>
                        <input type='hidden' name='user_id' value='{$row['id']}'>
                        <button name='action' value='Activate' class='btn btn-success btn-sm'>Activate</button>
                    </form>
                    <form method='POST' style='display:inline'>
                        <input type='hidden' name='user_id' value='{$row['id']}'>
                        <button name='action' value='Disable' class='btn btn-danger btn-sm'>Disable</button>
                    </form>
                </td>
            </tr>";
        }
        echo "</table>";
        ?>
    </div>

   

    <!-- EMAIL SECTION -->
    <div class="card shadow p-3 section-card mt-4">
        <h5>Send Email to Team</h5>

        <form method="POST" action="send_team_email.php">

            <div class="mb-2">
                <select name="department" class="form-control" required>
                    <option value="">Select Department</option>
                    <?php
                    $dept = $conn->query("SELECT DISTINCT department FROM users");
                    while($d = $dept->fetch_assoc()){
                        echo "<option value='{$d['department']}'>{$d['department']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-2">
                <input type="text" name="subject" class="form-control" placeholder="Subject" required>
            </div>

            <div class="mb-2">
                <textarea name="message" class="form-control" placeholder="Message" required></textarea>
            </div>

            <button class="btn btn-primary w-100">Send Email</button>

        </form>
    </div>

</div>

</body>
</html> 