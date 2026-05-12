<?php
// -- DATABASE CONFIG --
$db_host = "localhost";
$db_name = "helpdesk_system";
$db_user = "root";
$db_pass = "";

$message      = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ad_username    = trim($_POST["ad_username"] ?? "");
    $password       = $_POST["password"] ?? "";
    $role           = trim($_POST["role"] ?? "");
    $name           = trim($_POST["name"] ?? "");
    $department     = trim($_POST["department"] ?? "");
    $email          = trim($_POST["email"] ?? "");
    $first_login    = ($_POST["first_login"] ?? "1") === "1" ? 1 : 0;
    $account_status = trim($_POST["account_status"] ?? "active");

    $errors = [];

    if (empty($ad_username))                             $errors[] = "AD username is required.";
    if (empty($password))                                $errors[] = "Password is required.";
    elseif (strlen($password) < 8)                      $errors[] = "Password must be at least 8 characters.";
    if (empty($role))                                    $errors[] = "Role is required.";
    if (empty($name))                                    $errors[] = "Full name is required.";
    if (empty($department))                              $errors[] = "Department is required.";
    if (empty($email))                                   $errors[] = "Email is required.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = "Enter a valid email address.";

    if (empty($errors)) {
        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Check for duplicate username or email
            $check = $pdo->prepare("SELECT id FROM users WHERE ad_username = ? OR email = ? LIMIT 1");
            $check->execute([$ad_username, $email]);

            if ($check->rowCount() > 0) {
                $errors[] = "A user with that username or email already exists.";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare("
                    INSERT INTO users
                        (ad_username, password, role, name, department, email, first_login, account_status)
                    VALUES
                        (:ad_username, :password, :role, :name, :department, :email, :first_login, :account_status)
                ");

                $stmt->execute([
                    ":ad_username"    => $ad_username,
                    ":password"       => $hashed,
                    ":role"           => $role,
                    ":name"           => $name,
                    ":department"     => $department,
                    ":email"          => $email,
                    ":first_login"    => $first_login,
                    ":account_status" => $account_status,
                ]);

                $message      = "User <strong>" . htmlspecialchars($name) . "</strong> added successfully.";
                $message_type = "success";

                // Clear fields after success
                $ad_username = $password = $role = $name = $department = $email = "";
                $first_login    = 1;
                $account_status = "active";
            }

        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $message      = implode("<br>", $errors);
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add User</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 2.5rem 1rem;
    }

    .card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid #e2e4e9;
      padding: 2rem;
      width: 100%;
      max-width: 620px;
      box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }

    .page-title {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 1.75rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid #f0f0f0;
    }

    .page-title i { font-size: 22px; color: #2563eb; }
    .page-title h1 { font-size: 17px; font-weight: 600; color: #111; }

    .section {
      border: 1px solid #e8eaed;
      border-radius: 10px;
      padding: 1.2rem;
      margin-bottom: 1rem;
    }

    .section-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #aaa;
      margin-bottom: 1rem;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    @media (max-width: 500px) { .grid { grid-template-columns: 1fr; } }

    .field { display: flex; flex-direction: column; gap: 5px; }

    .field label {
      font-size: 13px;
      font-weight: 500;
      color: #333;
    }

    .field label .req { color: #e53e3e; margin-left: 2px; }

    .field input,
    .field select {
      padding: 9px 12px;
      font-size: 14px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      outline: none;
      color: #111;
      background: #fff;
      transition: border-color .15s, box-shadow .15s;
      width: 100%;
    }

    .field input:focus,
    .field select:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }

    .pwd-wrap { position: relative; }
    .pwd-wrap input { padding-right: 40px; }

    .eye-btn {
      position: absolute;
      right: 10px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer; color: #aaa;
      font-size: 17px; padding: 0; line-height: 1;
    }
    .eye-btn:hover { color: #555; }

    .actions {
      display: flex;
      gap: 10px;
      margin-top: 1.5rem;
    }

    .btn-save {
      flex: 1;
      padding: 11px;
      font-size: 14px;
      font-weight: 600;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      transition: background .15s;
    }
    .btn-save:hover { background: #1d4ed8; }

    .btn-reset {
      padding: 11px 22px;
      font-size: 14px;
      font-weight: 500;
      background: #f3f4f6;
      color: #444;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: background .15s;
    }
    .btn-reset:hover { background: #e5e7eb; }

    .btn-dashboard {
      padding: 11px 18px;
      font-size: 14px;
      font-weight: 500;
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background .15s;
    }
    .btn-dashboard:hover { background: #dcfce7; }

    .alert {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 13.5px;
      line-height: 1.5;
      margin-bottom: 1.25rem;
    }
    .alert i { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
    .alert.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert.error   { background: #fff5f5; color: #9b2c2c; border: 1px solid #feb2b2; }
  </style>
</head>
<body>
<div class="card">

  <div class="page-title">
    <i class="ti ti-user-plus"></i>
    <h1>Add new user</h1>
  </div>

  <?php if (!empty($message)): ?>
  <div class="alert <?= $message_type ?>">
    <i class="ti <?= $message_type === 'success' ? 'ti-circle-check' : 'ti-alert-circle' ?>"></i>
    <div><?= $message ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" action="">

    <!-- Credentials -->
    <div class="section">
      <div class="section-title">Account credentials</div>
      <div class="grid">
        <div class="field">
          <label>AD username <span class="req">*</span></label>
          <input type="text" name="ad_username" placeholder="e.g. jdoe"
                 value="<?= htmlspecialchars($ad_username ?? '') ?>"/>
        </div>
        <div class="field">
          <label>Password <span class="req">*</span></label>
          <div class="pwd-wrap">
            <input type="password" name="password" id="pwd" placeholder="Min. 8 characters"/>
            <button type="button" class="eye-btn" onclick="togglePwd()">
              <i class="ti ti-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Personal info -->
    <div class="section">
      <div class="section-title">Personal information</div>
      <div class="grid">
        <div class="field">
          <label>Full name <span class="req">*</span></label>
          <input type="text" name="name" placeholder="e.g. John Doe"
                 value="<?= htmlspecialchars($name ?? '') ?>"/>
        </div>
        <div class="field">
          <label>Email address <span class="req">*</span></label>
          <input type="email" name="email" placeholder="e.g. jdoe@company.com"
                 value="<?= htmlspecialchars($email ?? '') ?>"/>
        </div>
        <div class="field">
          <label>Department <span class="req">*</span></label>
          <input type="text" name="department" placeholder="e.g. Finance"
                 value="<?= htmlspecialchars($department ?? '') ?>"/>
        </div>
        <div class="field">
          <label>Role <span class="req">*</span></label>
          <select name="role">
            <option value="">Select role...</option>
            <option value="admin"   <?= (($role ?? '') === 'admin')   ? 'selected' : '' ?>>Admin</option>
            <option value="manager" <?= (($role ?? '') === 'manager') ? 'selected' : '' ?>>Manager</option>
            <option value="user"    <?= (($role ?? '') === 'user')    ? 'selected' : '' ?>>User</option>
            <option value="viewer"  <?= (($role ?? '') === 'viewer')  ? 'selected' : '' ?>>Viewer</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Account settings -->
    <div class="section">
      <div class="section-title">Account settings</div>
      <div class="grid">
        <div class="field">
          <label>Account status</label>
          <select name="account_status">
            <option value="active"    <?= (($account_status ?? 'active') === 'active')    ? 'selected' : '' ?>>Active</option>
            <option value="inactive"  <?= (($account_status ?? '') === 'inactive')         ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= (($account_status ?? '') === 'suspended')        ? 'selected' : '' ?>>Suspended</option>
          </select>
        </div>
        <div class="field">
          <label>First login</label>
          <select name="first_login">
            <option value="1" <?= (($first_login ?? 1) == 1) ? 'selected' : '' ?>>Yes — force password change</option>
            <option value="0" <?= (($first_login ?? 1) == 0) ? 'selected' : '' ?>>No — skip prompt</option>
          </select>
        </div>
      </div>
    </div>

    <div class="actions">
      <button type="submit" class="btn-save">
        <i class="ti ti-device-floppy"></i> Save user
      </button>
      <a href="" class="btn-reset">Reset</a>
      <a href="admin_dashboard.php" class="btn-dashboard">
        <i class="ti ti-layout-dashboard"></i> Dashboard
      </a>
    </div>

  </form>
</div>

<script>
  function togglePwd() {
    const p = document.getElementById('pwd');
    const i = document.getElementById('eyeIcon');
    p.type = p.type === 'password' ? 'text' : 'password';
    i.className = p.type === 'password' ? 'ti ti-eye' : 'ti ti-eye-off';
  }
</script>
</body>
</html>
