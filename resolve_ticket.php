<?php
session_start();
include 'db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != "Admin"){
    header("Location: login.php");
    exit();
}

if(isset($_GET['id'])){
    $id = intval($_GET['id']); // ✅ sanitize input

    // ✅ Set resolved_at = NOW() when marking as Resolved
    $stmt = $conn->prepare("UPDATE tickets SET status='Resolved', resolved_at=NOW() WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: manage_tickets.php");
exit();
?>