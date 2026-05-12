<?php
session_start();
include 'db.php';
include 'mailer.php';

/* PROTECT ADMIN */
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin'){
    header("Location: login.php");
    exit();
}

/* HANDLE ACTIONS FIRST (VERY IMPORTANT) */

/* ASSIGN + EMAIL */
if(isset($_POST['assign'])){
    $ticket_id  = intval($_POST['ticket_id'] ?? 0);
    $technician = intval($_POST['technician'] ?? 0);

    if($ticket_id && $technician){
        $stmt = $conn->prepare("UPDATE tickets SET assigned_to=? WHERE id=?");
        $stmt->bind_param("ii", $technician, $ticket_id);
        $stmt->execute();
        $stmt->close();

        $tech_stmt = $conn->prepare("SELECT name, email FROM users WHERE id=? AND role='Technician' LIMIT 1");
        $tech_stmt->bind_param("i", $technician);
        $tech_stmt->execute();
        $tech_result = $tech_stmt->get_result();
        $tech_data = $tech_result->fetch_assoc();
        $tech_email = $tech_data['email'] ?? '';
        $tech_name  = $tech_data['name'] ?? '';
        $tech_stmt->close();

        $ticket_stmt = $conn->prepare("
            SELECT tickets.ticket_code, tickets.description, tickets.status,
                   users.name AS requester_name, users.department AS requester_dept
            FROM tickets
            JOIN users ON tickets.user_id = users.id
            WHERE tickets.id=?");
        $ticket_stmt->bind_param("i", $ticket_id);
        $ticket_stmt->execute();
        $ticket = $ticket_stmt->get_result()->fetch_assoc();
        $ticket_stmt->close();

        if(!empty($tech_email)){
            $subject = "New Ticket Assigned: " . $ticket['ticket_code'];
            $body = "Hello $tech_name,\n\n"
                  . "You have been assigned a new ticket.\n\n"
                  . "Ticket Code: " . $ticket['ticket_code'] . "\n"
                  . "Issue: " . $ticket['description'] . "\n"
                  . "Status: " . $ticket['status'] . "\n"
                  . "Requester: " . $ticket['requester_name'] . "\n"
                  . "Department: " . $ticket['requester_dept'] . "\n\n"
                  . "Regards,\nHelpdesk Admin";

            sendMail($tech_email, $subject, $body);
        }
    }

    header("Location: manage_tickets.php");
    exit();
}

/* DELETE */
if(isset($_POST['delete'])){
    $id = intval($_POST['ticket_id']);
    $conn->query("DELETE FROM tickets WHERE id=$id");
    header("Location: manage_tickets.php");
    exit();
}

/* RESOLVE + SAVE NOTES */
if(isset($_POST['resolve'])){
    $id = intval($_POST['ticket_id']);
    $notes = $conn->real_escape_string($_POST['resolution_notes'] ?? '');

    $conn->query("UPDATE tickets SET status='Resolved', resolution_notes='$notes' WHERE id=$id");

    header("Location: manage_tickets.php");
    exit();
}

/* REOPEN */
if(isset($_POST['open'])){
    $id = intval($_POST['ticket_id']);
    $conn->query("UPDATE tickets SET status='Open' WHERE id=$id");
    header("Location: manage_tickets.php");
    exit();
}

/* ================= MAIN LOGIC ================= */

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$limit  = 5;
$offset = ($page - 1) * $limit;

$search_safe = $conn->real_escape_string($search);
$status_safe = $conn->real_escape_string($status);

/* ✅ ADDED priority + resolution_notes safely */
$sql = "SELECT tickets.*, users.name, users.department 
        FROM tickets 
        JOIN users ON tickets.user_id = users.id
        WHERE 1";

if(!empty($search)){
    $sql .= " AND (
        tickets.ticket_code LIKE '%$search_safe%' OR
        tickets.description LIKE '%$search_safe%' OR
        users.name LIKE '%$search_safe%' OR
        users.department LIKE '%$search_safe%'
    )";
}

if(!empty($status)){
    $sql .= " AND tickets.status='$status_safe'";
}

/* COUNT */
$count_sql = "SELECT COUNT(*) as total 
              FROM tickets 
              JOIN users ON tickets.user_id = users.id 
              WHERE 1";

if(!empty($search)){
    $count_sql .= " AND (
        tickets.ticket_code LIKE '%$search_safe%' OR
        tickets.description LIKE '%$search_safe%' OR
        users.name LIKE '%$search_safe%' OR
        users.department LIKE '%$search_safe%'
    )";
}
if(!empty($status)){
    $count_sql .= " AND tickets.status='$status_safe'";
}

$total_rows  = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

/* EXPORT */
if(isset($_GET['export'])){
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=report.xls");

    echo "No\tCode\tUser\tDept\tIssue\tPriority\tStatus\tNotes\tAssigned\tDate\n";

    $exp = $conn->query($sql);
    $i=1;
    while($r=$exp->fetch_assoc()){
        echo $i++ . "\t".$r['ticket_code']."\t".$r['name']."\t".$r['department']."\t".$r['description']."\t".$r['priority']."\t".$r['status']."\t".$r['resolution_notes']."\t".$r['assigned_to']."\t".$r['created_at']."\n";
    }
    exit();
}

$sql .= " ORDER BY tickets.created_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

/* TECHNICIANS */
$technicians = [];
$tq = $conn->query("SELECT id, name FROM users WHERE role='Technician'");
while($t = $tq->fetch_assoc()){
    $technicians[] = $t;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Tickets</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="container mt-5">

<h3 class="text-center mb-4">Manage Tickets</h3>

<table class="table table-bordered">
<tr>
<th>#</th>
<th>Ticket Code</th>
<th>Issue</th>
<th>Priority</th> <!-- ✅ ADDED -->
<th>Status</th>
<th>Resolution Notes</th> <!-- ✅ ADDED -->
<th>Requested By</th>
<th>Assign</th>
<th>Delete</th>
<th>Actions</th>
<th>Date</th>
</tr>

<?php $i=1; while($row=$result->fetch_assoc()): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= $row['ticket_code'] ?></td>
<td><?= $row['description'] ?></td>
<td><?= $row['priority'] ?? 'N/A' ?></td> <!-- SAFE -->
<td><?= $row['status'] ?></td>

<td>
<?= $row['resolution_notes'] ?? '' ?>
</td>

<td><?= $row['name'] ?> (<?= $row['department'] ?>)</td>

<td>
<form method="POST">
<input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">
<select name="technician" class="form-control mb-1">
<?php foreach($technicians as $tech): ?>
<option value="<?= $tech['id'] ?>"><?= $tech['name'] ?></option>
<?php endforeach; ?>
</select>
<button name="assign" class="btn btn-sm btn-primary">Assign</button>
</form>
</td>

<td>
<form method="POST">
<input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">
<button name="delete" class="btn btn-danger btn-sm">Delete</button>
</form>
</td>

<td>
<form method="POST">
<input type="hidden" name="ticket_id" value="<?= $row['id'] ?>">

<?php if($row['status']!='Resolved'): ?>
<textarea name="resolution_notes" class="form-control mb-1" placeholder="Add notes"></textarea>
<button name="resolve" class="btn btn-success btn-sm">Resolve</button>
<?php else: ?>
<button name="open" class="btn btn-warning btn-sm">Reopen</button>
<?php endif; ?>

</form>
</td>

<td><?= $row['created_at'] ?></td>
</tr>
<?php endwhile; ?>

</table>

</div>
</body>
</html>