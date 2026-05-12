<?php
session_start();

if(!isset($_SESSION['user_id'])){
header("Location: login.php");
exit();
}
?>
session_start();
include 'db.php';

if(!isset($_SESSION['user_id'])){
header("Location: login.php");
exit();
}

$user_id=$_SESSION['user_id'];

$sql="SELECT * FROM tickets WHERE user_id='$user_id'";

$result=$conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>

<title>My Tickets</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">

<h3>My Support Tickets</h3>

<table class="table table-bordered">

<tr>

<th>ID</th>
<th>Subject</th>
<th>Message</th>
<th>Status</th>
<th>Date</th>

</tr>

<?php
while($row=$result->fetch_assoc()){
?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['subject']; ?></td>

<td><?php echo $row['message']; ?></td>

<td><?php echo $row['status']; ?></td>

<td><?php echo $row['created_at']; ?></td>

</tr>

<?php
}
?>

</table>

<a href="dashboard.php" class="btn btn-secondary">
Back
</a>

</div>

</body>
</html>