<?php
require 'vendor/autoload.php'; // Dompdf installed via Composer
use Dompdf\Dompdf;

include 'db.php';

// Get chart images from POST
$chartCategory = $_POST['chartCategory'] ?? '';
$chartStaff    = $_POST['chartStaff'] ?? '';
$chartTrend    = $_POST['chartTrend'] ?? '';

// Fetch ticket data
$sql = "SELECT ticket_code, issue, status, assigned_to, created_at FROM tickets";
$result = $conn->query($sql);

// Build HTML
$html = "<h2>Executive Helpdesk Report</h2>";
$html .= "<table border='1' cellspacing='0' cellpadding='5'>
<tr><th>Ticket Code</th><th>Issue</th><th>Status</th><th>Assigned To</th><th>Date</th></tr>";

while($row = $result->fetch_assoc()){
    $html .= "<tr>
        <td>{$row['ticket_code']}</td>
        <td>{$row['issue']}</td>
        <td>{$row['status']}</td>
        <td>{$row['assigned_to']}</td>
        <td>{$row['created_at']}</td>
    </tr>";
}
$html .= "</table>";

// ✅ Embed charts if provided
if(!empty($chartCategory)) $html .= "<h3>Tickets by Category</h3><img src='$chartCategory' style='width:300px;'>";
if(!empty($chartStaff))    $html .= "<h3>Tickets by Staff</h3><img src='$chartStaff' style='width:400px;'>";
if(!empty($chartTrend))    $html .= "<h3>Monthly Tickets Trend</h3><img src='$chartTrend' style='width:400px;'>";

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Executive_Report.pdf", ["Attachment" => true]);
?>
