<?php
include 'db.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=ticket_report.xls");

$result = $conn->query("SELECT * FROM tickets");

echo "Issue\tTechnician\tStatus\tDate\n";

while($row = $result->fetch_assoc()){

echo $row['subject']."\t".$row['assigned_to']."\t".$row['status']."\t".$row['created_at']."\n";

}
?>