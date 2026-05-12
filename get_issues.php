<?php
include 'db.php';

$category = $_GET['category'] ?? '';

$issues = [];

if (!empty($category)) {
    $stmt = $conn->prepare("SELECT DISTINCT issue_type FROM tickets WHERE category = ? AND issue_type IS NOT NULL AND issue_type != '' ORDER BY issue_type");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $issues[] = $row['issue_type'];
    }
    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($issues);
