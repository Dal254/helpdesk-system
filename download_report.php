<?php
session_start();
include 'db.php';
require('fpdf/fpdf.php');

/* ── PROTECT ADMIN ── */
if(!isset($_SESSION['role']) || strcasecmp($_SESSION['role'], 'Admin') !== 0){
    header("Location: login.php");
    exit();
}

/* ══════════════════════════════════════════════
   CUSTOM FPDF CLASS — adds KDC header, footer
   and watermark on every page
═══════════════════════════════════════════════ */
class KDC_PDF extends FPDF {

    var $logoPath  = '';
    var $watermarkPath = '';

    function Header(){
        /* ── Watermark (drawn first so content sits on top) ── */
        if(!empty($this->watermarkPath) && file_exists($this->watermarkPath)){
            /* centre of A4 landscape: 210 x 148.5 mm */
            $this->Image($this->watermarkPath, 60, 25, 170, 110);
        }

        /* ── Blue header bar ── */
        $this->SetFillColor(0, 48, 115);   /* KDC navy blue */
        $this->Rect(0, 0, $this->GetPageWidth(), 22, 'F');

        /* ── Logo ── */
        if(!empty($this->logoPath) && file_exists($this->logoPath)){
            $this->kdc_logo.png($this->logopath, 4, 1, 28, 20);
        }

        /* ── Company name + system name ── */
        $this->SetFont('Arial','B',13);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(34, 3);
        $this->Cell(0, 7, 'KENYA DEVELOPMENT CORPORATION', 0, 1, 'L');

        $this->SetFont('Arial','',9);
        $this->SetTextColor(200, 220, 255);
        $this->SetX(34);
        $this->Cell(0, 6, 'ICT Help Desk Management System', 0, 1, 'L');

        /* ── Date/time stamp top-right ── */
        $this->SetFont('Arial','',8);
        $this->SetTextColor(200, 220, 255);
        $this->SetXY(0, 7);
        $this->Cell($this->GetPageWidth() - 6, 6, 'Generated: ' . date('d M Y  H:i'), 0, 0, 'R');

        $this->Ln(6);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer(){
        $this->SetY(-12);
        /* Gold line */
        $this->SetDrawColor(197, 160, 80);
        $this->SetLineWidth(0.6);
        $this->Line(8, $this->GetY(), $this->GetPageWidth() - 8, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'CONFIDENTIAL — Kenya Development Corporation | Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }

    /* Diagonal "KDC CONFIDENTIAL" text watermark fallback
       (used when no image watermark is available) */
    function TextWatermark(){
        $this->SetFont('Arial','B',42);
        $this->SetTextColor(220, 220, 230);
        $this->SetAlpha(0.12);   /* only works if GD extension active */
        $this->RotatedText(80, 100, 'KDC CONFIDENTIAL', 35);
        $this->SetAlpha(1);
        $this->SetTextColor(0, 0, 0);
    }

    /* Rotated text helper */
    function RotatedText($x, $y, $txt, $angle){
        $angle = $angle * M_PI / 180;
        $c = cos($angle); $s = sin($angle);
        $cx = $x * $this->k; $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf(
            'q %.5F %.5F %.5F %.5F %.2F %.2F cm',
            $c, $s, -$s, $c, $cx, $cy
        ));
        $this->Text(0, 0, $txt);
        $this->_out('Q');
    }
}

/* ══════════════════════════════════════════════
   HELPER FUNCTIONS
═══════════════════════════════════════════════ */
function clean($str){
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', (string)($str ?? ''));
}

function embedChart($pdf, $chartData, $title){
    if(empty(trim($chartData))) return;
    $raw     = preg_replace('#^data:image/\w+;base64,#i', '', $chartData);
    $raw     = str_replace(' ', '+', $raw);
    $decoded = base64_decode($raw, true);
    if(!$decoded) return;
    $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chart_' . uniqid() . '.png';
    file_put_contents($tmpPath, $decoded);
    if(file_exists($tmpPath)){
        $pdf->Ln(4);
        $pdf->SetFont('Arial','B',11);
        $pdf->SetTextColor(0, 48, 115);
        $pdf->Cell(0, 8, clean($title), 0, 1, 'L');
        $pdf->Image($tmpPath, $pdf->GetX(), $pdf->GetY(), 130, 85);
        $pdf->Ln(89);
        unlink($tmpPath);
    }
}

function runQuery($conn, $sql, $types, $params){
    if(!empty($params)){
        $stmt = $conn->prepare($sql);
        if(!$stmt) die("Query preparation failed: " . $conn->error . " | SQL: " . $sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    } else {
        $result = $conn->query($sql);
        if(!$result) die("Query failed: " . $conn->error . " | SQL: " . $sql);
        return $result;
    }
}

/* ══════════════════════════════════════════════
   READ INPUTS
═══════════════════════════════════════════════ */
$report_category    = $_POST['report_category']    ?? $_GET['report_category']    ?? 'system';
$report_type        = $_POST['report_type']        ?? $_GET['report_type']        ?? 'executive';
$format             = $_POST['format']             ?? $_GET['format']             ?? 'pdf';
$from_date          = $_POST['from_date']          ?? $_GET['from_date']          ?? '';
$to_date            = $_POST['to_date']            ?? $_GET['to_date']            ?? '';
$user_id            = $_POST['user_id']            ?? $_GET['user_id']            ?? '';
$selected_user_name = trim($_POST['selected_user_name'] ?? $_GET['selected_user_name'] ?? '');
$department_filter  = trim($_POST['department']    ?? $_GET['department']         ?? '');
$category_filter    = trim($_POST['category']      ?? $_GET['category']           ?? '');
$issue_filter       = trim($_POST['issue_type']    ?? $_GET['issue_type']         ?? '');
$status_filter      = trim($_POST['status']        ?? $_GET['status']             ?? '');
$priority_filter    = trim($_POST['priority']      ?? $_GET['priority']           ?? '');

$chartCategory = $_POST['chartCategory'] ?? '';
$chartStaff    = $_POST['chartStaff']    ?? '';
$chartTrend    = $_POST['chartTrend']    ?? '';

/* ── Logo path (place kdc_logo.png in your helpdesk root folder) ── */
$logoPath      = __DIR__ . '/kdc_logo.png';
$watermarkPath = __DIR__ . '/kdc_logo.png'; /* same image used faintly as watermark */

/* ══════════════════════════════════════════════
   RESOLVE USER NAME FROM DB
═══════════════════════════════════════════════ */
if($report_category === 'user' && !empty($user_id)){
    $uStmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    if($uStmt){
        $uStmt->bind_param('i', $user_id);
        $uStmt->execute();
        $uResult = $uStmt->get_result();
        if($uRow = $uResult->fetch_assoc()) $selected_user_name = $uRow['name'];
        $uStmt->close();
    }
}

/* ══════════════════════════════════════════════
   BUILD WHERE CLAUSE
   Tickets table columns used:
   id, title, description, user_name, department,
   status, technician, created_at, assigned_to,
   category, user_id, ticket_code, priority,
   resolution_notes, resolved_at
═══════════════════════════════════════════════ */
$where_parts = [];
$params      = [];
$types       = '';

/* User filter */
if($report_category === 'user' && !empty($user_id)){
    $where_parts[] = "t.user_id = ?";
    $params[]      = (int)$user_id;
    $types        .= 'i';
}

/* Status from report_type */
if($report_type === 'resolved'){
    $where_parts[] = "t.status = 'Resolved'";
} elseif($report_type === 'pending'){
    $where_parts[] = "t.status = 'Pending'";
} elseif($report_type === 'open'){
    $where_parts[] = "t.status = 'Open'";
}

/* Date range */
if(!empty($from_date)){
    $where_parts[] = "DATE(t.created_at) >= ?";
    $params[]      = $from_date;
    $types        .= 's';
}
if(!empty($to_date)){
    $where_parts[] = "DATE(t.created_at) <= ?";
    $params[]      = $to_date;
    $types        .= 's';
}

/* Department filter (on tickets table) */
if(!empty($department_filter)){
    $where_parts[] = "t.department = ?";
    $params[]      = $department_filter;
    $types        .= 's';
}

/* Category filter */
if(!empty($category_filter)){
    $where_parts[] = "t.category = ?";
    $params[]      = $category_filter;
    $types        .= 's';
}

/* Issue / title keyword filter */
if(!empty($issue_filter)){
    $where_parts[] = "t.title LIKE ?";
    $params[]      = '%' . $issue_filter . '%';
    $types        .= 's';
}

/* Status filter (from filter form — overrides report_type) */
if(!empty($status_filter) && $report_type === 'executive'){
    $where_parts[] = "t.status = ?";
    $params[]      = $status_filter;
    $types        .= 's';
}

/* Priority filter */
if(!empty($priority_filter)){
    $where_parts[] = "t.priority = ?";
    $params[]      = $priority_filter;
    $types        .= 's';
}

$where_sql = count($where_parts) ? "WHERE " . implode(" AND ", $where_parts) : "";

$sql = "SELECT 
            t.ticket_code,
            t.title,
            t.description,

            COALESCE(u.name, t.user_name) AS user_name,
            COALESCE(u.department, t.department) AS department,

            t.status,
            t.assigned_to,

            COALESCE(tech.name, t.technician, t.assigned_to) AS technician,

            t.priority,
            t.category,
            t.created_at,
            t.resolution_notes,
            t.resolved_at

        FROM tickets t
        LEFT JOIN users u 
            ON t.user_id = u.id

        LEFT JOIN users tech 
            ON t.assigned_to = tech.id

        $where_sql
        ORDER BY t.created_at DESC";

$result = runQuery($conn, $sql, $types, $params);

/* ── Build active filter summary for report header ── */
$filter_summary = [];
if(!empty($department_filter)) $filter_summary[] = "Department: $department_filter";
if(!empty($category_filter))   $filter_summary[] = "Category: $category_filter";
if(!empty($issue_filter))      $filter_summary[] = "Issue: $issue_filter";
if(!empty($status_filter))     $filter_summary[] = "Status: $status_filter";
if(!empty($priority_filter))   $filter_summary[] = "Priority: $priority_filter";

/* ══════════════════════════════════════════════
   PDF EXPORT
═══════════════════════════════════════════════ */
if($format === 'pdf'){

    while(ob_get_level()) ob_end_clean();

    $pdf = new KDC_PDF('L','mm','A4');
    $pdf->logoPath      = $logoPath;
    $pdf->watermarkPath = $watermarkPath;
    $pdf->AliasNbPages();
    $pdf->SetMargins(8, 26, 8);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AddPage();

    /* ── Report title ── */
    $reportTitle = $report_category === 'user'
        ? 'Individual User Report — ' . $selected_user_name
        : 'ICT Helpdesk Ticket Report';

    $pdf->SetFont('Arial','B',14);
    $pdf->SetFillColor(0, 48, 115);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, clean($reportTitle), 0, 1, 'C', true);
    $pdf->Ln(2);

    /* ── Gold accent line ── */
    $pdf->SetDrawColor(197, 160, 80);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(8, $pdf->GetY(), $pdf->GetPageWidth() - 8, $pdf->GetY());
    $pdf->Ln(3);

    /* ── Meta block ── */
    $pdf->SetFont('Arial','',9);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);

    $type_label = $report_type === 'executive' ? 'All Tickets' : ucfirst($report_type) . ' Tickets Only';

    if($report_category === 'user')
        $pdf->Cell(0, 5, clean("User: $selected_user_name"), 0, 1, 'L');

    $pdf->Cell(0, 5, clean("Report Type: $type_label"), 0, 1, 'L');

    if(!empty($from_date) || !empty($to_date)){
        $range = (!empty($from_date) ? $from_date : 'Start') . '  to  ' . (!empty($to_date) ? $to_date : 'Today');
        $pdf->Cell(0, 5, clean("Date Range: $range"), 0, 1, 'L');
    }

    if(!empty($filter_summary)){
        $pdf->Cell(0, 5, clean("Filters: " . implode(' | ', $filter_summary)), 0, 1, 'L');
    }

    $pdf->Ln(3);

    /* ── Summary stats row ── */
    $total      = $result->num_rows;
    $result->data_seek(0);
    $stat = ['Open'=>0,'Pending'=>0,'Resolved'=>0,'Closed'=>0];
    while($r = $result->fetch_assoc()){
        $s = ucfirst(strtolower($r['status'] ?? ''));
        if(isset($stat[$s])) $stat[$s]++;
    }
    $result->data_seek(0);

    $statW = ($pdf->GetPageWidth() - 16) / 4;
    $statColors = [
        'Open'     => [255, 199, 206],
        'Pending'  => [255, 235, 156],
        'Resolved' => [198, 239, 206],
        'Closed'   => [220, 220, 220],
    ];
    $pdf->SetFont('Arial','B',9);
    foreach($stat as $label => $count){
        [$r2,$g,$b] = $statColors[$label];
        $pdf->SetFillColor($r2,$g,$b);
        $pdf->SetTextColor(40,40,40);
        $pdf->Cell($statW, 10, clean("$label: $count"), 1, 0, 'C', true);
    }
    $pdf->SetFillColor(0,48,115);
    $pdf->SetTextColor(255,255,255);
    $pdf->Ln();
    $pdf->Ln(3);

    /* ── Table header ── */
    $cols = [
        'Code'        => 26,
        'Title'       => 38,
        'User'        => 28,
        'Department'  => 28,
        'Status'      => 22,
        'Assigned To' => 30,
        'Technician'  => 28,
        'Priority'    => 18,
        'Category'    => 28,
        'Date'        => 24,
    ];

    $pdf->SetFont('Arial','B',7.5);
    $pdf->SetFillColor(0, 48, 115);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.2);

    foreach($cols as $h => $w){
        $pdf->Cell($w, 8, clean($h), 1, 0, 'C', true);
    }
    $pdf->Ln();

    /* ── Table rows ── */
    $pdf->SetFont('Arial','',7);
    $pdf->SetTextColor(0,0,0);
    $fill = false;

    if($result && $result->num_rows > 0){
        while($row = $result->fetch_assoc()){
            /* auto page break guard */
            if($pdf->GetY() > 175){
                $pdf->AddPage();
                $pdf->SetFont('Arial','B',7.5);
                $pdf->SetFillColor(0,48,115);
                $pdf->SetTextColor(255,255,255);
                foreach($cols as $h => $w)
                    $pdf->Cell($w, 8, clean($h), 1, 0, 'C', true);
                $pdf->Ln();
                $pdf->SetFont('Arial','',7);
                $pdf->SetTextColor(0,0,0);
            }

            $bgR = $fill ? 240 : 255;
            $pdf->SetFillColor($bgR,$bgR,$bgR);

            $status = $row['status'] ?? '';

            $pdf->Cell($cols['Code'],       7, clean(mb_strimwidth($row['ticket_code'] ?? '', 0, 14, '')), 1, 0, 'L', $fill);
            $pdf->Cell($cols['Title'],      7, clean(mb_strimwidth($row['title']       ?? '', 0, 28, '...')), 1, 0, 'L', $fill);
            $pdf->Cell($cols['User'],       7, clean(mb_strimwidth($row['user_name']   ?? '', 0, 20, '...')), 1, 0, 'L', $fill);
            $pdf->Cell($cols['Department'], 7, clean(mb_strimwidth($row['department']  ?? '', 0, 20, '...')), 1, 0, 'L', $fill);

            /* Status colour cell */
            $sR=255; $sG=255; $sB=255;
            if(strtolower($status)==='resolved'){ $sR=198;$sG=239;$sB=206; }
            elseif(strtolower($status)==='pending'){ $sR=255;$sG=235;$sB=156; }
            elseif(strtolower($status)==='open'){ $sR=255;$sG=199;$sB=206; }
            elseif(strtolower($status)==='closed'){ $sR=220;$sG=220;$sB=220; }
            $pdf->SetFillColor($sR,$sG,$sB);
            $pdf->Cell($cols['Status'],     7, clean($status), 1, 0, 'C', true);

            $pdf->SetFillColor($bgR,$bgR,$bgR);
            $pdf->Cell($cols['Assigned To'], 7, clean(mb_strimwidth($row['assigned_to'] ?? '', 0, 22, '...')), 1, 0, 'L', $fill);
            $pdf->Cell($cols['Technician'],  7, clean(mb_strimwidth($row['technician']  ?? '', 0, 22, '...')), 1, 0, 'L', $fill);

            /* Priority colour */
            $pR=255;$pG=255;$pB=255;
            if(strtolower($row['priority'] ?? '')==='high'||strtolower($row['priority'] ?? '')==='critical'){ $pR=255;$pG=199;$pB=206; }
            elseif(strtolower($row['priority'] ?? '')==='medium'){ $pR=255;$pG=235;$pB=156; }
            elseif(strtolower($row['priority'] ?? '')==='low'){ $pR=198;$pG=239;$pB=206; }
            $pdf->SetFillColor($pR,$pG,$pB);
            $pdf->Cell($cols['Priority'],   7, clean($row['priority'] ?? ''), 1, 0, 'C', true);

            $pdf->SetFillColor($bgR,$bgR,$bgR);
            $pdf->Cell($cols['Category'],   7, clean(mb_strimwidth($row['category']   ?? '', 0, 20, '...')), 1, 0, 'L', $fill);
            $pdf->Cell($cols['Date'],       7, clean(substr($row['created_at'] ?? '', 0, 10)), 1, 0, 'C', $fill);
            $pdf->Ln();

            $fill = !$fill;
        }
    } else {
        $pdf->SetFont('Arial','I',9);
        $pdf->Cell(0, 10, clean('No tickets found for the selected filters.'), 1, 1, 'C');
    }

    /* ── Total row ── */
    $pdf->SetFont('Arial','B',8);
    $pdf->SetFillColor(0,48,115);
    $pdf->SetTextColor(255,255,255);
    $pdf->Cell(0, 7, clean("Total Tickets: $total"), 1, 1, 'R', true);
    $pdf->SetTextColor(0,0,0);

    /* ── Charts page ── */
    $hasCharts = !empty(trim($chartCategory)) || !empty(trim($chartStaff)) || !empty(trim($chartTrend));
    if($hasCharts){
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',13);
        $pdf->SetFillColor(0,48,115);
        $pdf->SetTextColor(255,255,255);
        $pdf->Cell(0, 10, clean('Analytics Charts'), 0, 1, 'C', true);
        $pdf->SetTextColor(0,0,0);
        $pdf->Ln(4);
        embedChart($pdf, $chartCategory, 'Tickets by Category');
        embedChart($pdf, $chartStaff,    'Tickets by Staff / Technician');
        embedChart($pdf, $chartTrend,    'Monthly Tickets Trend');
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $selected_user_name);
    $outFile  = $report_category === 'user'
        ? 'KDC_Report_' . $safeName . '_' . date('Ymd') . '.pdf'
        : 'KDC_Helpdesk_Report_' . date('Ymd') . '.pdf';

    $pdf->Output('D', $outFile);
    exit();
}

/* ══════════════════════════════════════════════
   EXCEL EXPORT
═══════════════════════════════════════════════ */
if($format === 'excel'){

    while(ob_get_level()) ob_end_clean();

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $selected_user_name);
    $outFile  = $report_category === 'user'
        ? 'KDC_Report_' . $safeName . '_' . date('Ymd') . '.xls'
        : 'KDC_Helpdesk_Report_' . date('Ymd') . '.xls';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $outFile . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $reportTitle = $report_category === 'user'
        ? 'Individual User Report — ' . htmlspecialchars($selected_user_name)
        : 'KDC ICT Helpdesk Ticket Report';

    $type_label = $report_type === 'executive' ? 'All Tickets' : ucfirst($report_type) . ' Tickets Only';

    $range = '';
    if(!empty($from_date) || !empty($to_date)){
        $range = (!empty($from_date) ? $from_date : 'Start') . ' to ' . (!empty($to_date) ? $to_date : 'Today');
    }

    echo '
    <html xmlns:o="urn:schemas-microsoft-com:office:office"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]>
        <xml><x:ExcelWorkbook><x:ExcelWorksheets>
        <x:ExcelWorksheet><x:Name>KDC Report</x:Name>
        <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
        </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml>
        <![endif]-->
        <style>
            body  { font-family: Arial, sans-serif; font-size: 11px; }
            table { border-collapse: collapse; width: 100%; }
            .header-row td {
                background-color: #003073; color: #ffffff;
                font-weight: bold; font-size: 14px;
                text-align: center; padding: 10px;
            }
            .meta-row td { color: #444; font-style: italic; font-size: 10px; padding: 3px 6px; }
            .filter-row td { color: #003073; font-size: 10px; padding: 2px 6px; }
            th {
                background-color: #003073; color: #ffffff;
                font-weight: bold; text-align: center;
                padding: 6px 8px; border: 1px solid #aaa;
            }
            td    { padding: 5px 8px; border: 1px solid #ccc; font-size: 10px; }
            .alt  { background-color: #EEF2FF; }
            .resolved { background-color: #C6EFCE; text-align: center; }
            .pending  { background-color: #FFEB9C; text-align: center; }
            .open     { background-color: #FFC7CE; text-align: center; }
            .closed   { background-color: #DCDCDC; text-align: center; }
            .pri-high     { background-color: #FFC7CE; text-align: center; }
            .pri-medium   { background-color: #FFEB9C; text-align: center; }
            .pri-low      { background-color: #C6EFCE; text-align: center; }
            .total-row td { background-color: #003073; color: #fff; font-weight: bold; text-align: right; }
            .footer-row td { color: #888; font-size: 9px; font-style: italic; text-align: center; border-top: 2px solid #C5A050; }
        </style>
    </head>
    <body>
    <table>';

    /* KDC header row */
    echo '<tr class="header-row"><td colspan="11">KENYA DEVELOPMENT CORPORATION — ICT Help Desk Management System</td></tr>';
    echo '<tr class="header-row"><td colspan="11">' . $reportTitle . '</td></tr>';

    /* Meta */
    echo '<tr class="meta-row"><td colspan="11">Report Type: ' . htmlspecialchars($type_label) . '</td></tr>';
    if(!empty($range))
        echo '<tr class="meta-row"><td colspan="11">Date Range: ' . htmlspecialchars($range) . '</td></tr>';
    if(!empty($filter_summary))
        echo '<tr class="filter-row"><td colspan="11">Filters: ' . htmlspecialchars(implode(' | ', $filter_summary)) . '</td></tr>';
    echo '<tr class="meta-row"><td colspan="11">Generated: ' . date('d M Y H:i:s') . '</td></tr>';
    echo '<tr><td colspan="11"></td></tr>'; /* spacer */

    /* Column headers */
    echo '<tr>
        <th>Ticket Code</th>
        <th>Title</th>
        <th>User</th>
        <th>Department</th>
        <th>Status</th>
        <th>Assigned To</th>
        <th>Technician</th>
        <th>Priority</th>
        <th>Category</th>
        <th>Date Created</th>
        <th>Resolved At</th>
    </tr>';

    $result2 = runQuery($conn, $sql, $types, $params);
    $rowNum  = 0;
    $total   = 0;

    if($result2 && $result2->num_rows > 0){
        while($row = $result2->fetch_assoc()){
            $total++;
            $alt          = ($rowNum % 2 === 1) ? ' class="alt"' : '';
            $status       = $row['status'] ?? '';
            $priority     = strtolower($row['priority'] ?? '');

            $statusClass  = match(strtolower($status)){
                'resolved' => 'resolved',
                'pending'  => 'pending',
                'open'     => 'open',
                'closed'   => 'closed',
                default    => ''
            };
            $priClass = match($priority){
                'high','critical' => 'pri-high',
                'medium'          => 'pri-medium',
                'low'             => 'pri-low',
                default           => ''
            };
            $altClass = ($rowNum % 2 === 1) ? 'alt' : '';

            echo '<tr>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['ticket_code']  ?? '') . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['title']        ?? '') . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['user_name']    ?? '') . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['department']   ?? '') . '</td>';
            echo '<td class="'.$statusClass.'">' . htmlspecialchars($status)                 . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['assigned_to']  ?? '') . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['technician']   ?? '') . '</td>';
            echo '<td class="'.$priClass.'">'  . htmlspecialchars($row['priority']    ?? '') . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars($row['category']     ?? '') . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars(substr($row['created_at']  ?? '', 0, 10)) . '</td>';
            echo '<td class="'.$altClass.'">' . htmlspecialchars(substr($row['resolved_at'] ?? '', 0, 10)) . '</td>';
            echo '</tr>';
            $rowNum++;
        }
    } else {
        echo '<tr><td colspan="11" style="text-align:center;font-style:italic;">No tickets found for the selected filters.</td></tr>';
    }

    /* Total row */
    echo '<tr class="total-row"><td colspan="11">Total Tickets: ' . $total . '</td></tr>';

    /* Footer */
    echo '<tr class="footer-row"><td colspan="11">CONFIDENTIAL — Kenya Development Corporation | Generated ' . date('d M Y H:i') . '</td></tr>';

    echo '</table></body></html>';
    exit();
}

/* Fallback */
header("Location: analytics.php");
exit();
?>
