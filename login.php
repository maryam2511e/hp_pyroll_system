
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $error = "Please enter email and password.";

    } else {

        try {

            $stmt = $pdo->prepare(
                "SELECT id, name, email, password
                 FROM admins
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);

            $admin = $stmt->fetch();

            if ($admin && password_verify(
                $password,
                $admin['password']
            )) {

                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];

                header("Location: dashboard.php");
                exit;

            } else {

                $error = "Invalid email or password.";

            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $error = "Something went wrong. Please try again.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | HR & Payroll</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>

        body {
            background: #f3f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 8px 35px rgba(0,0,0,0.08);
        }

        .login-title {
            font-weight: 700;
            color: #1e293b;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 14px;
        }

        .form-control {
            padding: 12px;
        }

        .btn-login {
            background: #2563eb;
            color: white;
            padding: 12px;
            font-weight: 600;
        }

        .btn-login:hover {
            background: #1d4ed8;
            color: white;
        }

    </style>

</head>

<body>

    <div class="login-card">

        <div class="text-center mb-4">

            <h3 class="login-title">
                HR & Payroll
            </h3>

            <p class="login-subtitle">
                Super Admin Login
            </p>

        </div>

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <div class="mb-3">

                <label class="form-label">
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >

            </div>

            <div class="mb-4">

                <label class="form-label">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter your password"
                    required
                >

            </div>

            <button type="submit"
                    class="btn btn-login w-100">

                Login to Dashboard

            </button>

        </form>

        <div class="text-center mt-4">

            <small class="text-muted">
                Internal Company Management System
            </small>

        </div>

    </div>

</body>

</html>
