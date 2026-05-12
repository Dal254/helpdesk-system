<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ─────────────────────────────────────────────────────────
// Helper: build a configured PHPMailer instance
// ─────────────────────────────────────────────────────────
function createMailer(): PHPMailer {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = 'smtp.office365.com';
    $m->SMTPAuth   = true;
    return $m;
}

$error       = "";
$ticket_code = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id  = (int) $_SESSION['user_id'];
    $category = trim($_POST['category'] ?? '');
    $issue    = trim($_POST['issue']    ?? '');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $title    = trim($_POST['title']    ?? '');

    if ($category === "" || $issue === "" || $title === "") {
        $error = "Please fill all fields.";
    } else {

        // ─────────────────────────────────────────────────
        // 1. Fetch user name + email using get_result()
        //    (more reliable than bind_result for nulls)
        // ─────────────────────────────────────────────────
        $name      = "User";
        $userEmail = "";

        $userStmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
        if ($userStmt) {
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($userRow = $userResult->fetch_assoc()) {
                $name      = !empty($userRow['name'])  ? $userRow['name']  : "User";
                $userEmail = !empty($userRow['email']) ? $userRow['email'] : "";
            }
            $userStmt->close();
        }

        // ─────────────────────────────────────────────────
        // 2. Generate ticket code
        // ─────────────────────────────────────────────────
        $res  = $conn->query("SELECT MAX(id) AS max_id FROM tickets");
        $next = 1;
        if ($res) {
            $row = $res->fetch_assoc();
            if ($row && $row['max_id'] !== null) {
                $next = (int)$row['max_id'] + 1;
            }
        }
        $ticket_code = "KDC-" . str_pad($next, 4, "0", STR_PAD_LEFT);

        // ─────────────────────────────────────────────────
        // 3. Insert ticket into database
        // ─────────────────────────────────────────────────
        $sql  = "INSERT INTO tickets 
                    (ticket_code, user_id, category, priority, description, status, resolution_notes, assigned_to, created_at, title)
                 VALUES (?, ?, ?, ?, ?, 'Pending', NULL, 'Help Desk', NOW(), ?)";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("sissss", $ticket_code, $user_id, $category, $priority, $issue, $title);

            if (!$stmt->execute()) {
                $error = "Failed to save ticket: " . $stmt->error;
            } else {
                $stmt->close();

                // ─────────────────────────────────────────
                // 4a. EMAIL → Internal helpdesk notification
                // ─────────────────────────────────────────
                try {
                    $mail = createMailer();
                    $mail->addAddress('helpdesk@kdc.go.ke', 'KDC Helpdesk');
                    $mail->Subject = "New Helpdesk Ticket - $ticket_code";
                    $mail->Body    = "
                        <h3 style='color:#0d6efd;'>New Ticket Submitted</h3>
                        <p><strong>Code:</strong> $ticket_code</p>
                        <p><strong>Title:</strong> $title</p>
                        <p><strong>Category:</strong> $category</p>
                        <p><strong>Priority:</strong> $priority</p>
                        <p><strong>Submitted By:</strong> $name ($userEmail)</p>
                        <p><strong>Issue:</strong><br>" . nl2br(htmlspecialchars($issue)) . "</p>
                    ";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Helpdesk notification email failed: " . $e->getMessage());
                }

                // ─────────────────────────────────────────
                // 4b. EMAIL → Confirmation to the requester
                // ─────────────────────────────────────────
                if (!empty($userEmail)) {
                    try {
                        $uMail = createMailer();
                        $uMail->addAddress($userEmail, $name);
                        $uMail->Subject = "Request Received: $ticket_code — KDC Help Desk";
                        $uMail->Body    = "
                        <div style='font-family:Segoe UI,Arial,sans-serif;max-width:620px;margin:auto;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;'>

                            <!-- Header -->
                            <div style='background:#0d6efd;padding:24px 30px;text-align:center;'>
                                <h2 style='color:#fff;margin:0;letter-spacing:1px;'>KDC Help Desk</h2>
                                <p style='color:#cfe2ff;margin:6px 0 0;font-size:13px;'>Kenya Development Corporation</p>
                            </div>

                            <!-- Body -->
                            <div style='padding:32px 30px;background:#fff;'>
                                <p style='font-size:16px;margin-top:0;'>Dear <strong>$name</strong>,</p>

                                <p style='font-size:15px;line-height:1.6;'>
                                    Thank you for contacting the <strong>KDC Help Desk</strong>.
                                    Your request has been <strong>successfully received</strong> and our
                                    technical team is already working on it.
                                </p>

                                <!-- Ticket summary box -->
                                <div style='background:#f0f4ff;border-left:5px solid #0d6efd;border-radius:6px;padding:18px 22px;margin:24px 0;'>
                                    <p style='margin:0 0 10px;font-size:15px;font-weight:700;color:#0d6efd;'>Ticket Summary</p>
                                    <table style='width:100%;font-size:14px;border-collapse:collapse;'>
                                        <tr>
                                            <td style='padding:6px 0;color:#555;width:120px;'>Ticket Code</td>
                                            <td style='padding:6px 0;font-weight:700;color:#222;'>$ticket_code</td>
                                        </tr>
                                        <tr>
                                            <td style='padding:6px 0;color:#555;'>Title</td>
                                            <td style='padding:6px 0;color:#222;'>$title</td>
                                        </tr>
                                        <tr>
                                            <td style='padding:6px 0;color:#555;'>Category</td>
                                            <td style='padding:6px 0;color:#222;'>$category</td>
                                        </tr>
                                        <tr>
                                            <td style='padding:6px 0;color:#555;'>Priority</td>
                                            <td style='padding:6px 0;color:#222;'>$priority</td>
                                        </tr>
                                        <tr>
                                            <td style='padding:6px 0;color:#555;vertical-align:top;'>Issue</td>
                                            <td style='padding:6px 0;color:#222;'>" . nl2br(htmlspecialchars($issue)) . "</td>
                                        </tr>
                                    </table>
                                </div>

                                <p style='font-size:15px;line-height:1.6;'>
                                    You will receive updates as your ticket progresses.
                                    If you need to add more information, please reply to this email
                                    quoting your ticket code: <strong>$ticket_code</strong>.
                                </p>

                                <p style='font-size:15px;margin-top:28px;margin-bottom:0;'>
                                    Warm regards,<br>
                                    <strong>KDC Help Desk Team</strong><br>
                                    <span style='color:#888;font-size:13px;'>helpdesk@kdc.go.ke</span>
                                </p>
                            </div>

                            <!-- Footer -->
                            <div style='background:#f5f5f5;text-align:center;padding:14px;font-size:12px;color:#999;'>
                                This is an automated message — please do not reply directly to this email.
                            </div>

                        </div>";
                        $uMail->send();
                    } catch (Exception $e) {
                        error_log("User confirmation email failed for $userEmail: " . $e->getMessage());
                    }
                } else {
                    error_log("No email found for user_id=$user_id. Confirmation email not sent.");
                }

                // ─────────────────────────────────────────
                // 5. Redirect to dashboard on success
                // ─────────────────────────────────────────
                header("Location: dashboard.php?ticket=success&code=$ticket_code");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Ticket - KDC Help Desk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)),
                        url('assets/kdc_helpdesk_bg.jpeg') center/cover no-repeat;
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
        }

        .ticket-card {
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
            background: rgba(255,255,255,0.80);
            backdrop-filter: blur(10px);
            padding: 2rem 2.2rem;
        }

        .ticket-card h3 {
            font-weight: 700;
            color: #0d6efd;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-label {
            font-weight: 600;
            color: #222;
        }

        .form-control, .form-select, textarea {
            border-radius: 8px;
            border: 1px solid #ccc;
            transition: all 0.2s ease-in-out;
        }

        .form-control:focus, .form-select:focus, textarea:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 6px rgba(13,110,253,0.3);
        }

        .btn-primary {
            border-radius: 30px;
            padding: 10px 30px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="ticket-card">
                <h3>🎫 Submit a Help Desk Ticket</h3>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger text-center">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="submit_ticket.php">

                    <div class="mb-3">
                        <label class="form-label">Ticket Title</label>
                        <input type="text" name="title" class="form-control"
                               placeholder="Enter a short title" required
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">-- Select Category --</option>
                            <?php
                            $categories = [
                                "System Access", "Email Issues", "Hardware Support",
                                "Software/Application Support", "Network Connectivity",
                                "Printer/Scanner", "ERP"
                            ];
                            foreach ($categories as $cat):
                                $sel = (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $cat; ?>" <?php echo $sel; ?>><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" required>
                            <option value="">-- Select Priority --</option>
                            <?php foreach (['Low', 'Medium', 'High'] as $p):
                                $sel = (isset($_POST['priority']) && $_POST['priority'] === $p) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $p; ?>" <?php echo $sel; ?>><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Issue Details</label>
                        <textarea name="issue" class="form-control" rows="5"
                                  placeholder="Describe the issue in detail..." required><?php
                            echo isset($_POST['issue']) ? htmlspecialchars($_POST['issue']) : '';
                        ?></textarea>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Submit Ticket</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <a href="dashboard.php" class="btn btn-outline-secondary">⬅ Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
