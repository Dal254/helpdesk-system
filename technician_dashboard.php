<?php
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

/* PROTECT TECHNICIAN */
if(!isset($_SESSION['role']) || strcasecmp($_SESSION['role'], 'Technician') !== 0){
    header("Location: login.php");
    exit();
}

/* SESSION CHECK */
if(!isset($_SESSION['user_id'])){
    die("Session error: user_id missing. Fix login session.");
}

$tech_id   = $_SESSION['user_id'];
$tech_name = $_SESSION['username'];

/* ================= FETCH TICKETS ================= */
$stmt = $conn->prepare("
    SELECT t.*, u.name AS requester_name 
    FROM tickets t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE t.assigned_to = ? 
    ORDER BY t.created_at DESC
");

$stmt->bind_param("i", $tech_id);
$stmt->execute();
$result = $stmt->get_result();

/* ================= UPDATE STATUS ================= */
if(isset($_POST['update_status'])){

    $ticket_id = intval($_POST['ticket_id']);
    $status    = $_POST['status'];
    $notes     = trim($_POST['notes']);

    /* UPDATE TICKET */
    $update = $conn->prepare("
        UPDATE tickets 
        SET status=?, resolution_notes=? 
        WHERE id=? AND assigned_to=?
    ");
    $update->bind_param("ssii", $status, $notes, $ticket_id, $tech_id);
    $update->execute();

    /* SEND EMAIL IF RESOLVED */
    if($status === 'Resolved'){

        $ticketStmt = $conn->prepare("
            SELECT ticket_code, description, user_id 
            FROM tickets 
            WHERE id=? LIMIT 1
        ");
        $ticketStmt->bind_param("i", $ticket_id);
        $ticketStmt->execute();
        $ticketResult = $ticketStmt->get_result();

        if($trow = $ticketResult->fetch_assoc()){

            $ticket_code = $trow['ticket_code'];
            $issue       = $trow['description'];
            $user_id     = $trow['user_id'];

            $userStmt = $conn->prepare("
                SELECT email, name 
                FROM users 
                WHERE id=? LIMIT 1
            ");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();

            if($urow = $userResult->fetch_assoc()){

                $userEmail = $urow['email'];
                $userName  = $urow['name'];

                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.office365.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'helpdesk@kdc.go.ke';
                    $mail->Password   = 'Is@36977041'; // ⚠️ move to config in production
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;

                    $mail->setFrom('helpdesk@kdc.go.ke', 'KDC Helpdesk');
                    $mail->addAddress($userEmail, $userName);

                    $mail->isHTML(true);
                    $mail->Subject = "Ticket Resolved: $ticket_code";

                    $mail->Body = "
                        <h2>Your Ticket Has Been Resolved</h2>
                        <p>Dear $userName,</p>
                        <p><strong>Ticket Code:</strong> $ticket_code</p>
                        <p><strong>Issue:</strong> $issue</p>
                        <p><strong>Status:</strong> Resolved</p>
                        <p><strong>Resolution Notes:</strong><br>$notes</p>
                        <p>Regards,<br>KDC Helpdesk</p>
                    ";

                    $mail->send();

                } catch (Exception $e) {
                    echo "Mailer Error: " . $mail->ErrorInfo;
                }
            }
        }
    }

    header("Location: technician_dashboard.php?success=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Technician Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; font-family:Arial; }
.card { border-radius:12px; }
.open { color:orange; font-weight:bold; }
.resolved { color:green; font-weight:bold; }
</style>
</head>

<body>

<div class="container mt-5">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Welcome, <?php echo htmlspecialchars($tech_name); ?></h2>
    <a href="logout.php" class="btn btn-danger">Logout</a>
</div>

<!-- ✅ FIXED ALERT -->
<?php if(isset($_GET['success'])): ?>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999;">
    <div class="alert alert-success shadow-sm px-3 py-2 mb-0" style="font-size:14px; min-width:250px;">
        ✅ Ticket updated successfully and user notified.
    </div>
</div>
<?php endif; ?>

<div class="card shadow p-3">
<h4>Your Assigned Tickets</h4>

<table class="table table-bordered table-striped">
<thead class="table-dark">
<tr>
<th>Ticket Code</th>
<th>Issue</th>
<th>Priority</th>
<th>Status</th>
<th>Resolution Notes</th>
<th>Requested By</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php if($result && $result->num_rows > 0): ?>
<?php while($row = $result->fetch_assoc()): ?>

<tr>
<td><?= htmlspecialchars($row['ticket_code']) ?></td>
<td><?= htmlspecialchars($row['description']) ?></td>
<td><?= htmlspecialchars($row['priority']) ?></td>

<td class="<?= strtolower($row['status']) ?>">
    <?= htmlspecialchars($row['status']) ?>
</td>

<td><?= htmlspecialchars($row['resolution_notes'] ?? '') ?></td>

<td><?= htmlspecialchars($row['requester_name'] ?? 'Unknown') ?></td>

<td>
<form method="POST" class="d-flex">

<input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">

<select name="status" class="form-select me-2">
    <option value="Open" <?= $row['status']=='Open'?'selected':'' ?>>Open</option>
    <option value="Resolved" <?= $row['status']=='Resolved'?'selected':'' ?>>Resolved</option>
</select>

<input type="text" name="notes" class="form-control me-2" placeholder="Resolution notes" required>

<button name="update_status" class="btn btn-primary">Update</button>

</form>
</td>

</tr>

<?php endwhile; ?>
<?php else: ?>

<tr>
<td colspan="7" class="text-center text-muted">
No tickets assigned to you
</td>
</tr>

<?php endif; ?>

</tbody>
</table>

</div>
</div>

<!-- ✅ AUTO HIDE ALERT -->
<script>
setTimeout(function() {
    var alertBox = document.querySelector('.alert');
    if(alertBox){
        alertBox.style.transition = "opacity 0.5s";
        alertBox.style.opacity = "0";
        setTimeout(function(){
            alertBox.remove();
        }, 500);
    }
}, 3000);
</script>

</body>
</html>