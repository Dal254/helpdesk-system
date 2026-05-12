<?php
session_start();
include 'db.php';

/* PROTECT USER */
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* FETCH USER TICKETS WITH DEPARTMENT */
$query = "
SELECT t.*, u.name, u.department 
FROM tickets t
JOIN users u ON t.user_id = u.id
WHERE t.user_id = '$user_id'
ORDER BY t.id DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
<title>My Tickets</title>

<style>
body{
    margin:0;
    font-family:Arial;
}

/* BACKGROUND */
body::before{
    content:"";
    position:fixed;
    width:100%;
    height:100%;
    background:url('assets/kdc_logo.png') no-repeat center;
    background-size:cover;
    filter:brightness(0.3) blur(3px);
    z-index:-1;
}

/* CONTAINER */
.container{
    width:90%;
    margin:40px auto;
    background:rgba(255,255,255,0.95);
    padding:20px;
    border-radius:12px;
    box-shadow:0 8px 25px rgba(0,0,0,0.3);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
}

th, td{
    padding:12px;
    border-bottom:1px solid #ccc;
    text-align:left;
}

th{
    background:#007bff;
    color:white;
}

/* STATUS COLORS */
.open{
    color:orange;
    font-weight:bold;
}
.resolved{
    color:green;
    font-weight:bold;
}

/* NAV */
.nav{
    text-align:center;
    margin-bottom:15px;
}

.nav a{
    margin:0 10px;
    text-decoration:none;
    color:#007bff;
    font-weight:bold;
}
</style>

</head>
<body>

<div class="container">

<div class="nav">
    <a href="dashboard.php">🏠 Home</a>
    <a href="dashboard.php">⬅ Back</a>
</div>

<h2>My Tickets</h2>

<table>
<tr>
    <th>#</th>
    <th>Ticket Code</th>
    <th>Department</th>
    <th>Category</th>
    <th>Issue</th>
    <th>Priority</th>
    <th>Status</th>
    <th>Assigned To</th>
    <th>Date</th>
</tr>

<?php
$count = 1;

if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
?>
<tr>
    <td><?php echo $count++; ?></td>
    <td><?php echo htmlspecialchars($row['ticket_code']); ?></td>
    <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
    <td><?php echo htmlspecialchars($row['category']); ?></td>
    <td><?php echo htmlspecialchars($row['issue']); ?></td>
    <td><?php echo htmlspecialchars($row['priority']); ?></td>
    <td class="<?php echo strtolower($row['status']); ?>">
        <?php echo htmlspecialchars($row['status']); ?>
    </td>
    <td><?php echo htmlspecialchars($row['assigned_to'] ?? 'Unassigned'); ?></td>
    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
</tr>
<?php
    }
}else{
    echo "<tr><td colspan='9' style='text-align:center;'>No tickets found</td></tr>";
}
?>

</table>

</div>

</body>
</html>
