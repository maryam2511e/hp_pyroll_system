<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$employee_id = $_GET['id'] ?? '';

if (!ctype_digit((string)$employee_id) || (int)$employee_id <= 0) {
    header("Location: index.php?error=Invalid employee ID");
    exit;
}

$employee_id = (int)$employee_id;

$stmt = $pdo->prepare("
    SELECT
        id,
        employee_code,
        first_name,
        last_name,
        designation,
        employment_status,
        base_salary
    FROM employees
    WHERE id = ?
");

$stmt->execute([$employee_id]);

$employee = $stmt->fetch();

if (!$employee) {
    header("Location: index.php?error=Employee not found");
    exit;
}

$errors = [];

$base_salary = $employee['base_salary'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $base_salary = trim($_POST['base_salary'] ?? '');

    if ($base_salary === '') {
        $errors[] = "Base salary is required.";
    } elseif (!is_numeric($base_salary)) {
        $errors[] = "Base salary must be a valid number.";
    } elseif ((float)$base_salary < 0) {
        $errors[] = "Base salary cannot be negative.";
    } elseif (strlen($base_salary) > 12) {
        $errors[] = "Base salary value is too large.";
    }

    if (empty($errors)) {

        $old_salary = (float)$employee['base_salary'];
        $new_salary = (float)$base_salary;

        $update = $pdo->prepare("
            UPDATE employees
            SET base_salary = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $update->execute([
            $new_salary,
            $employee_id
        ]);

        $audit = $pdo->prepare("
            INSERT INTO audit_logs (action, timestamp)
            VALUES (?, CURRENT_TIMESTAMP)
        ");

        $audit->execute([
            "Salary updated for employee ID {$employee_id}. Old salary: {$old_salary}, New salary: {$new_salary}"
        ]);

        header("Location: index.php?success=1");
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Salary - HR Payroll System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background: #172033;
            color: white;
            padding: 25px 15px;
            top: 0;
            left: 0;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .sidebar h3 {
            font-size: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .sidebar a {
            display: block;
            color: #dce3f0;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #263653;
            color: white;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            max-width: 800px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .readonly-box {
            background: #f1f3f5;
        }

        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

        }

    </style>

</head>

<body>

<div class="sidebar">

    <h3>HR Payroll</h3>

    <a href="../../dashboard.php">Dashboard</a>

    <a href="../employees/index.php">Employees</a>

    <a href="../attendance/index.php">Attendance</a>

    <a href="index.php" class="active">Salary Management</a>

    <a href="../advances/index.php">Advances</a>

    <a href="../deductions/index.php">Deductions</a>

    <a href="../overtime/index.php">Overtime</a>

    <a href="../bonuses/index.php">Bonuses</a>

    <a href="../leaves/index.php">Annual Leaves</a>

    <a href="../payroll/index.php">Payroll</a>

    <a href="../reports/index.php">Reports</a>

    <a href="../settings/index.php">Settings</a>

    <a href="../../logout.php">Logout</a>

</div>

<div class="main">

    <div class="mb-4">

        <h2>Edit Employee Salary</h2>

        <p class="text-muted mb-0">
            Update the employee's current base salary.
        </p>

    </div>

    <div class="form-card">

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <div class="row g-3 mb-3">

            <div class="col-md-6">

                <label class="form-label">
                    Employee Code
                </label>

                <input
                    type="text"
                    class="form-control readonly-box"
                    value="<?= htmlspecialchars($employee['employee_code']) ?>"
                    readonly
                >

            </div>

            <div class="col-md-6">

                <label class="form-label">
                    Employee Name
                </label>

                <input
                    type="text"
                    class="form-control readonly-box"
                    value="<?= htmlspecialchars(
                        $employee['first_name'] . ' ' . $employee['last_name']
                    ) ?>"
                    readonly
                >

            </div>

            <div class="col-md-6">

                <label class="form-label">
                    Designation
                </label>

                <input
                    type="text"
                    class="form-control readonly-box"
                    value="<?= htmlspecialchars($employee['designation']) ?>"
                    readonly
                >

            </div>

            <div class="col-md-6">

                <label class="form-label">
                    Employment Status
                </label>

                <input
                    type="text"
                    class="form-control readonly-box"
                    value="<?= htmlspecialchars($employee['employment_status']) ?>"
                    readonly
                >

            </div>

        </div>

        <form method="POST" novalidate>

            <div class="mb-3">

                <label for="base_salary" class="form-label">
                    Base Salary <span class="text-danger">*</span>
                </label>

                <input
                    type="number"
                    name="base_salary"
                    id="base_salary"
                    class="form-control"
                    value="<?= htmlspecialchars($base_salary) ?>"
                    min="0"
                    max="999999999.99"
                    step="0.01"
                    inputmode="decimal"
                    required
                >

                <div class="form-text">
                    Enter the employee's current monthly base salary.
                </div>

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <button type="submit" class="btn btn-primary">
                    Update Salary
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>