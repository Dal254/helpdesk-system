<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($new_password === "" || $confirm_password === "") {
        $error = "Password cannot be empty.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // ✅ Update password and mark first_login = 0
        $sql = "UPDATE users SET password=?, first_login=0 WHERE id=?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error = "SQL prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("si", $new_password, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $success = "Password set successfully. Redirecting to dashboard...";
                header("refresh:2;url=dashboard.php");
            } else {
                $error = "Failed to set password: " . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Set Password - KDC Help Desk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)),
                url('assets/kdc_logo.png'); /* ✅ replace with your background image */
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
    font-family: 'Segoe UI', Arial, sans-serif;
}

/* Transparent frosted card */
.password-card {
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.35);
    background: rgba(255,255,255,0.75);
    backdrop-filter: blur(8px);
    padding: 2rem;
}

/* Heading */
.password-card h3 {
    font-weight: 700;
    color: #0d6efd;
    margin-bottom: 1.5rem;
    text-align: center;
}

/* Labels */
.form-label {
    font-weight: 600;
    color: #222;
}

/* Inputs */
.form-control {
    border-radius: 8px;
    border: 1px solid #ccc;
    transition: all 0.2s ease-in-out;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 6px rgba(13,110,253,0.3);
}

/* Buttons */
.btn-primary {
    border-radius: 30px;
    padding: 10px 25px;
    font-weight: 600;
    transition: background 0.3s ease;
}

.btn-primary:hover {
    background-color: #0b5ed7;
}
</style>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="password-card">
                <h3>Set Your New Password</h3>

                <!-- Error / Success messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger text-center">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success text-center">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="set_password.php">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Save Password</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-outline-secondary">⬅ Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
