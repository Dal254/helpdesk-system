<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

$error = ""; // variable to hold error messages

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? ''); // username or email
    $password   = trim($_POST['password'] ?? '');

    if ($identifier === "") {
        $error = "Please enter your username or email.";
    } else {
        $sql = "SELECT id, ad_username, password, role, name, department, email, first_login, account_status 
                FROM users 
                WHERE ad_username = ? OR email = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error = "SQL prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $row = $result->fetch_assoc()) {
                // 🚫 Block disabled accounts
                if (strcasecmp($row['account_status'], 'Disabled') === 0) {
                    $error = "Your account has been disabled. Please contact the ICT team.";
                } elseif ((int)$row['first_login'] === 1) {
                    // ✅ First-time login: skip password check
                    $_SESSION['user_id']   = $row['id'];
                    $_SESSION['role']      = $row['role'];
                    $_SESSION['username']  = $row['ad_username'];
                    $_SESSION['name']      = $row['name'];
                    header("Location: set_password.php");
                    exit();
                } elseif ($password !== "" && $password === $row['password']) {
                    // ✅ Normal login
                    $_SESSION['user_id']   = $row['id'];
                    $_SESSION['role']      = $row['role'];
                    $_SESSION['name']      = $row['name'];

                    $role = trim($row['role']);
                    if (strcasecmp($role, 'Admin') === 0) {
                        $_SESSION['username'] = $row['ad_username'];
                        header("Location: admin_dashboard.php");
                    } elseif (strcasecmp($role, 'Technician') === 0) {
                        // ✅ Fetch team_name from technicians table using email
                        $techRes = $conn->query("SELECT team_name FROM technicians WHERE email='" . $conn->real_escape_string($row['email']) . "' LIMIT 1");
                        if ($techRes && $techRow = $techRes->fetch_assoc()) {
                            $_SESSION['username'] = $techRow['team_name'];
                        } else {
                            $_SESSION['username'] = $row['ad_username']; // fallback
                        }
                        header("Location: technician_dashboard.php");
                    } else {
                        $_SESSION['username'] = $row['ad_username'];
                        header("Location: dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Login - KDC Help Desk</title>
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
.login-card {
    border-radius: 12px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.35);
    background: rgba(255,255,255,0.75); /* semi-transparent white */
    backdrop-filter: blur(8px); /* frosted glass effect */
    padding: 2rem;
}

/* Heading */
.login-card h3 {
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
            <div class="login-card">
                <h3>Login to Help Desk</h3>

                <!-- Error message -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger text-center">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                    
<input type="text" name="identifier" class="form-control" required>

                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (first time? leave it blank)</label>
                        <input type="password" name="password" class="form-control" >
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-secondary">⬅ Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
