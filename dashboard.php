<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// --- Database Connection ---
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "helpdesk_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user details
$stmt = $conn->prepare("SELECT id, ad_username, role, name, department, email FROM users WHERE ad_username = ?");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch tickets for this user
$tickets = [];
if ($user) {
    $tid = intval($user['id']);
    $res = $conn->query("SELECT ticket_code, category, priority, description, status, created_at 
                         FROM tickets WHERE user_id='$tid' ORDER BY created_at DESC");
    if ($res) {
        while($row = $res->fetch_assoc()){
            $tickets[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Dashboard</title>
    <style>
        body { font-family: Arial; background:#f4f6f9; margin:0; }
        header { background:#2c3e50; color:white; padding:15px; text-align:center; }
        nav { background:#34495e; padding:10px; text-align:center; }
        nav a { color:white; margin:0 15px; text-decoration:none; font-weight:bold; }
        nav a:hover { text-decoration:underline; }
        .container { padding:20px; padding-bottom:60px; }
        .card { background:white; padding:20px; margin:15px auto; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); max-width:800px; }
        button { background:#2980b9; color:white; border:none; padding:10px 15px; border-radius:5px; cursor:pointer; }
        button:hover { background:#3498db; }
        input[type="password"] { padding:8px; width:100%; margin:8px 0; border:1px solid #ccc; border-radius:5px; }
        table { width:100%; border-collapse:collapse; margin-top:15px; }
        th, td { border:1px solid #ccc; padding:8px; text-align:left; }
        th { background:#2c3e50; color:white; }
        .success { background:#d4edda; color:#155724; padding:10px; border-radius:5px; margin-bottom:15px; }
        footer { background:#2c3e50; color:white; text-align:center; padding:10px; position:fixed; bottom:0; width:100%; }
    </style>
</head>
<body>
    <header>
        <h1>Helpdesk System Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </header>

    <nav>
        <a href="dashboard.php">Home</a>
        <a href="submit_ticket.php">Submit Ticket</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="container">
        <?php if(isset($_GET['ticket']) && $_GET['ticket'] === 'success'): ?>
            <div class="success">
                ✅ Ticket <?php echo htmlspecialchars($_GET['code']); ?> submitted successfully!
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Profile</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
        </div>

        <div class="card">
            <h2>Tickets Submitted</h2>
            <?php if(count($tickets) > 0): ?>
                <table>
                    <tr>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Issue</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                    <?php foreach($tickets as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['ticket_code']); ?></td>
                            <td><?php echo htmlspecialchars($t['category']); ?></td>
                            <td><?php echo htmlspecialchars($t['priority']); ?></td>
                            <td><?php echo htmlspecialchars($t['description']); ?></td>
                            <td><?php echo htmlspecialchars($t['status']); ?></td>
                            <td><?php echo htmlspecialchars($t['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No tickets submitted yet.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Change Your Password</h2>
            <form method="POST" action="login.php">
                <input type="hidden" name="action" value="reset">
                <label>New Password:</label>
                <input type="password" name="new_password" required><br><br>
                <button type="submit">Update Password</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Helpdesk System | Powered by KDC ICT TEAM</p>
    </footer>
</body>
</html>