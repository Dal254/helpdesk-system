<?php
session_start();
include 'db.php';

/* PROTECT: must be logged in */
if (!isset($_SESSION['ad_username'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($new_password === $confirm_password) {
        $stmt = $conn->prepare("UPDATE users SET password=?, first_login=0 WHERE ad_username=?");
        $stmt->bind_param("ss", $new_password, $_SESSION['ad_username']);

        if ($stmt->execute()) {
            $success = "Password updated successfully. Please log in again.";
            session_destroy();
            header("Refresh:2; url=login.php");
        } else {
            $error = "Error updating password: " . $stmt->error;
        }
    } else {
        $error = "Passwords do not match.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Change Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5" style="max-width:500px;">
<div class="card shadow-lg p-4">

<h4 class="text-center mb-3">Set New Password</h4>

<?php if(isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
<?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

<form method="POST">
<div class="mb-3">
<label>New Password</label>
<input type="password" name="new_password" class="form-control" required>
</div>

<div class="mb-3">
<label>Confirm Password</label>
<input type="password" name="confirm_password" class="form-control" required>
</div>

<button class="btn btn-primary w-100">Update Password</button>
</form>

</div>
</div>

</body>
</html>
