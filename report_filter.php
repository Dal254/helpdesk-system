<?php
session_start();
include 'db.php';

/* PROTECT ADMIN */
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin'){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Report Filter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
<style>
    body { background: #f4f5f7; }

    .report-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e2e4e9;
        padding: 2rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }

    .section-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #aaa;
        margin-bottom: .75rem;
        margin-top: 1.25rem;
    }

    .form-label { font-weight: 500; font-size: 14px; color: #333; }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        font-size: 14px;
        padding: 9px 12px;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }

    .btn-generate {
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 28px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        transition: background .15s;
    }
    .btn-generate:hover { background: #1d4ed8; color: #fff; }

    .btn-back {
        background: #f3f4f6;
        color: #444;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        transition: background .15s;
    }
    .btn-back:hover { background: #e5e7eb; color: #333; }

    .format-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 16px;
        cursor: pointer;
        font-size: 14px;
        transition: all .15s;
        background: #fff;
    }

    .format-btn input[type="radio"] { display: none; }

    .format-btn.selected-pdf {
        border-color: #dc2626;
        background: #fff5f5;
        color: #dc2626;
    }

    .format-btn.selected-excel {
        border-color: #16a34a;
        background: #f0fdf4;
        color: #16a34a;
    }

    .divider {
        border: none;
        border-top: 1px solid #f0f0f0;
        margin: 1.25rem 0;
    }
</style>
</head>
<body>

<?php include 'admin_navbar.php'; ?>

<div class="container mt-5 mb-5" style="max-width: 680px;">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="admin_dashboard.php" class="btn-back">
            <i class="ti ti-arrow-left"></i> Dashboard
        </a>
        <div>
            <h4 class="mb-0 fw-bold">Generate Ticket Report</h4>
            <small class="text-muted">Filter and export tickets by date, user, department or category</small>
        </div>
    </div>

    <form action="download_report.php" method="get" class="report-card">

        <!-- DATE RANGE -->
        <div class="section-title"><i class="ti ti-calendar me-1"></i> Date Range</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control">
            </div>
        </div>

        <hr class="divider">

        <!-- USER & DEPARTMENT -->
        <div class="section-title"><i class="ti ti-users me-1"></i> User & Department</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Filter by User</label>
                <select name="user_id" class="form-select">
                    <option value="">All Users</option>
                    <?php
                    $res = $conn->query("SELECT id, name FROM users ORDER BY name");
                    while($row = $res->fetch_assoc()){
                        echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Filter by Department</label>
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php
                    $res = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
                    while($row = $res->fetch_assoc()){
                        echo "<option value='" . htmlspecialchars($row['department']) . "'>" . htmlspecialchars($row['department']) . "</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <hr class="divider">

        <!-- TICKET FILTERS -->
        <div class="section-title"><i class="ti ti-ticket me-1"></i> Ticket Filters</div>
        <div class="row g-3">

            <!-- Category -->
            <div class="col-md-6">
                <label class="form-label">Filter by Category</label>
                <select name="category" id="categorySelect" class="form-select" onchange="loadSubcategories(this.value)">
                    <option value="">All Categories</option>
                    <?php
                    // Change 'categories' to your actual categories table name
                    $res = $conn->query("SELECT DISTINCT category FROM tickets WHERE category IS NOT NULL AND category != '' ORDER BY category");
                    if($res && $res->num_rows > 0){
                        while($row = $res->fetch_assoc()){
                            echo "<option value='" . htmlspecialchars($row['category']) . "'>" . htmlspecialchars($row['category']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Issue / Subcategory -->
            <div class="col-md-6">
                <label class="form-label">Filter by Issue Type</label>
                <select name="issue_type" id="issueSelect" class="form-select">
                    <option value="">All Issues</option>
                    <?php
                    // Load all issue types initially
                    $res = $conn->query("SELECT DISTINCT issue_type FROM tickets WHERE issue_type IS NOT NULL AND issue_type != '' ORDER BY issue_type");
                    if($res && $res->num_rows > 0){
                        while($row = $res->fetch_assoc()){
                            echo "<option value='" . htmlspecialchars($row['issue_type']) . "'>" . htmlspecialchars($row['issue_type']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-6">
                <label class="form-label">Filter by Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Open">Open</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Closed">Closed</option>
                </select>
            </div>

            <!-- Priority -->
            <div class="col-md-6">
                <label class="form-label">Filter by Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All Priorities</option>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>

        </div>

        <hr class="divider">

        <!-- EXPORT FORMAT -->
        <div class="section-title"><i class="ti ti-file-export me-1"></i> Export Format</div>
        <div class="d-flex gap-3">
            <label class="format-btn" id="pdfLabel">
                <input type="radio" name="format" value="pdf" checked onchange="updateFormat()">
                <i class="ti ti-file-type-pdf" style="font-size:20px;"></i>
                <div>
                    <div style="font-weight:600;">PDF</div>
                    <div style="font-size:12px; opacity:.7;">Printable report</div>
                </div>
            </label>
            <label class="format-btn" id="excelLabel">
                <input type="radio" name="format" value="excel" onchange="updateFormat()">
                <i class="ti ti-file-spreadsheet" style="font-size:20px;"></i>
                <div>
                    <div style="font-weight:600;">Excel</div>
                    <div style="font-size:12px; opacity:.7;">Spreadsheet (.xlsx)</div>
                </div>
            </label>
        </div>

        <hr class="divider">

        <!-- ACTIONS -->
        <div class="d-flex justify-content-between align-items-center mt-2">
            <a href="admin_dashboard.php" class="btn-back">
                <i class="ti ti-arrow-left"></i> Back
            </a>
            <button type="submit" class="btn-generate">
                <i class="ti ti-download"></i> Generate Report
            </button>
        </div>

    </form>
</div>

<script>
// Highlight selected format
function updateFormat() {
    const pdf   = document.querySelector('input[value="pdf"]');
    const excel = document.querySelector('input[value="excel"]');
    document.getElementById('pdfLabel').className   = 'format-btn' + (pdf.checked   ? ' selected-pdf'   : '');
    document.getElementById('excelLabel').className = 'format-btn' + (excel.checked ? ' selected-excel' : '');
}
updateFormat();

// Load issue types based on selected category via AJAX
function loadSubcategories(category) {
    const issueSelect = document.getElementById('issueSelect');
    issueSelect.innerHTML = '<option value="">All Issues</option>';

    if (!category) return;

    fetch('get_issues.php?category=' + encodeURIComponent(category))
        .then(res => res.json())
        .then(data => {
            data.forEach(function(item) {
                const opt = document.createElement('option');
                opt.value = item;
                opt.textContent = item;
                issueSelect.appendChild(opt);
            });
        })
        .catch(() => {});
}
</script>

</body>
</html>
