<?php
session_start();
include 'db.php';

/* PROTECT ADMIN */
if(!isset($_SESSION['role']) || strcasecmp($_SESSION['role'], 'Admin') !== 0){
    header("Location: login.php");
    exit();
}

/* CATEGORY (PIE) */
$cat_labels = [];
$cat_data = [];
$res1 = $conn->query("SELECT IFNULL(category,'Unknown') as category, COUNT(*) as total FROM tickets GROUP BY category");
while($row = $res1->fetch_assoc()){
    $cat_labels[] = $row['category'];
    $cat_data[]   = (int)$row['total'];
}

/* STAFF (BAR) */
$staff_labels = [];
$staff_data = [];
$res2 = $conn->query("SELECT IFNULL(NULLIF(assigned_to,''),'Unassigned') as staff, COUNT(*) as total FROM tickets GROUP BY staff");
while($row = $res2->fetch_assoc()){
    $staff_labels[] = $row['staff'];
    $staff_data[]   = (int)$row['total'];
}

/* MONTHLY (LINE) */
$months = [];
$month_data = [];
$res3 = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as total FROM tickets GROUP BY month ORDER BY month ASC");
while($row = $res3->fetch_assoc()){
    $months[]     = $row['month'];
    $month_data[] = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Executive Analytics Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: #f8f9fa; }
.card { border-radius: 10px; margin-bottom: 20px; }
.card-header { font-weight: bold; text-align: center; background: #343a40; color: #fff; }
.section-card { max-width: 900px; margin: auto; }
</style>
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between mb-3">
        
    </div>

    <h3 class="text-center mb-4">Executive Analytics Dashboard</h3>

    <!-- EXECUTIVE REPORT FILTER -->
    <div class="card shadow p-3 mb-4 section-card">
        <h5>Executive Report Filters</h5>
        <form method="POST" action="download_report.php" id="executiveForm" class="row g-2">
            <input type="hidden" name="report_category" value="system">
            <input type="hidden" name="chartCategory" id="chartCategory">
            <input type="hidden" name="chartStaff" id="chartStaff">
            <input type="hidden" name="chartTrend" id="chartTrend">

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
        <form method="POST" action="download_report.php" id="individualForm" class="row g-2">

            <input type="hidden" name="report_category" value="user">
            <input type="hidden" name="chartCategory" id="chartCategory2">
            <input type="hidden" name="chartStaff" id="chartStaff2">
            <input type="hidden" name="chartTrend" id="chartTrend2">
            <input type="hidden" name="selected_user_name" id="selected_user_name">

            <div class="col-md-3">
                <select name="user_id" id="user_id_select" class="form-select" required>
                    <option value="">Select User</option>
                    <?php
                    $users = $conn->query("SELECT id, name FROM users WHERE role != 'Admin' ORDER BY name");
                    while ($u = $users->fetch_assoc()) {
                        echo "<option value='{$u['id']}' data-name='" . htmlspecialchars($u['name']) . "'>{$u['name']}</option>";
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

    <!-- CHARTS -->
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">Tickets by Category</div>
                <div class="card-body">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">Tickets by Staff</div>
                <div class="card-body">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header">Monthly Tickets Trend</div>
                <div class="card-body">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const catLabels   = <?= json_encode($cat_labels) ?>;
const catData     = <?= json_encode($cat_data) ?>;
const staffLabels = <?= json_encode($staff_labels) ?>;
const staffData   = <?= json_encode($staff_data) ?>;
const months      = <?= json_encode($months) ?>;
const monthData   = <?= json_encode($month_data) ?>;

const pieChart = new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: ['#3498db','#e74c3c','#2ecc71','#9b59b6'] }] }
});

const barChart = new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: { labels: staffLabels, datasets: [{ label: 'Tickets per Staff', data: staffData, backgroundColor: '#27ae60' }] },
    options: { scales: { y: { beginAtZero: true } } }
});

const lineChart = new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: { labels: months, datasets: [{ label: 'Monthly Tickets', data: monthData, borderColor: '#f39c12', fill: false }] }
});

// Capture charts on Executive form submit
document.getElementById('executiveForm').addEventListener('submit', function() {
    document.getElementById('chartCategory').value = pieChart.toBase64Image();
    document.getElementById('chartStaff').value    = barChart.toBase64Image();
    document.getElementById('chartTrend').value    = lineChart.toBase64Image();
});

// Capture selected user name when dropdown changes
document.getElementById('user_id_select').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    document.getElementById('selected_user_name').value = selected.getAttribute('data-name') || '';
});

// Capture charts on Individual form submit
document.getElementById('individualForm').addEventListener('submit', function() {
    document.getElementById('chartCategory2').value = pieChart.toBase64Image();
    document.getElementById('chartStaff2').value    = barChart.toBase64Image();
    document.getElementById('chartTrend2').value    = lineChart.toBase64Image();
});
</script>

</body>
</html>