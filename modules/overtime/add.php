<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$employeeStmt = $pdo->query("
    SELECT id, employee_code, first_name, last_name
    FROM employees
    WHERE employment_status = 'Active'
    ORDER BY first_name ASC
");

$employees = $employeeStmt->fetchAll();

$errors = [];

$employee_id = '';
$hours = '';
$date = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Load Default Overtime Multiplier From Settings
|--------------------------------------------------------------------------
*/

$multiplier = '1.5';

$settingStmt = $pdo->prepare("
    SELECT setting_value
    FROM system_settings
    WHERE setting_key = ?
    LIMIT 1
");

$settingStmt->execute(['default_overtime_multiplier']);

$settingMultiplier = $settingStmt->fetchColumn();

if ($settingMultiplier !== false && is_numeric($settingMultiplier)) {
    $multiplier = (string)$settingMultiplier;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id = trim($_POST['employee_id'] ?? '');
    $hours = trim($_POST['hours'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $multiplier = trim($_POST['multiplier'] ?? '');

    // Employee validation
    if ($employee_id === '' || !ctype_digit($employee_id)) {
        $errors[] = "Please select a valid employee.";
    }

    // Hours validation
    if ($hours === '' || !is_numeric($hours) || (float)$hours <= 0) {
        $errors[] = "Overtime hours must be greater than zero.";
    } elseif ((float)$hours > 24) {
        $errors[] = "Overtime hours cannot exceed 24 per day.";
    }

    // Date validation
    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        $errors[] = "Please enter a valid overtime date.";
    } elseif ($date > date('Y-m-d')) {
        $errors[] = "Future dates are not allowed.";
    }

    // Multiplier validation
    if (
        $multiplier === '' ||
        !is_numeric($multiplier) ||
        (float)$multiplier <= 0 ||
        (float)$multiplier > 10
    ) {
        $errors[] = "Multiplier must be greater than 0 and cannot exceed 10.";
    }

    // Verify employee
    if (!$errors) {

        $checkEmployee = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = ?
            AND employment_status = 'Active'
            LIMIT 1
        ");

        $checkEmployee->execute([(int)$employee_id]);

        if (!$checkEmployee->fetch()) {
            $errors[] = "Selected employee is not active or does not exist.";
        }
    }

    // Save overtime
    if (!$errors) {

        try {

            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO overtime_management
                (employee_id, hours, date, multiplier)
                VALUES (?, ?, ?, ?)
            ");

            $insertStmt->execute([
                (int)$employee_id,
                (float)$hours,
                $date,
                (float)$multiplier
            ]);

            $overtimeId = (int)$pdo->lastInsertId();

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $logStmt->execute([
                "Overtime record ID {$overtimeId} added by Super Admin."
            ]);

            $pdo->commit();

            header("Location: index.php?success=1");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = "Unable to save overtime record. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Overtime - HR Payroll System</title>

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
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            max-width: 850px;
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
    <a href="../salary/index.php">Salary Management</a>
    <a href="../advances/index.php">Advances</a>
    <a href="../deductions/index.php">Deductions</a>
    <a href="index.php" class="active">Overtime</a>
    <a href="../bonuses/index.php">Bonuses</a>
    <a href="../leaves/index.php">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>

</div>

<div class="main">

    <h2 class="mb-2">Add Overtime</h2>

    <p class="text-muted mb-4">
        Enter employee overtime information.
    </p>

    <div class="form-card">

        <?php if ($errors): ?>

            <div class="alert alert-danger">

                <ul class="mb-0">

                    <?php foreach ($errors as $error): ?>

                        <li><?= htmlspecialchars($error) ?></li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Employee <span class="text-danger">*</span>
                </label>

                <select
                    name="employee_id"
                    class="form-select"
                    required
                >

                    <option value="">Select Employee</option>

                    <?php foreach ($employees as $employee): ?>

                        <option
                            value="<?= (int)$employee['id'] ?>"
                            <?= (string)$employee_id === (string)$employee['id'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $employee['employee_code'] . ' - ' .
                                $employee['first_name'] . ' ' .
                                $employee['last_name']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Overtime Hours <span class="text-danger">*</span>
                </label>

                <input
                    type="number"
                    name="hours"
                    class="form-control"
                    min="0.01"
                    max="24"
                    step="0.01"
                    value="<?= htmlspecialchars($hours) ?>"
                    required
                >

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Overtime Date <span class="text-danger">*</span>
                </label>

                <input
                    type="date"
                    name="date"
                    class="form-control"
                    max="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($date) ?>"
                    required
                >

            </div>

            <div class="mb-4">

                <label class="form-label">
                    Overtime Multiplier <span class="text-danger">*</span>
                </label>

                <input
                    type="number"
                    name="multiplier"
                    class="form-control"
                    min="0.01"
                    max="10"
                    step="0.01"
                    value="<?= htmlspecialchars($multiplier) ?>"
                    required
                >

                <small class="text-muted">
                    Default value is loaded from Settings.
                    You can change it for this overtime record if required.
                </small>

            </div>

            <button type="submit" class="btn btn-primary">
                Save Overtime
            </button>

            <a href="index.php" class="btn btn-secondary">
                Cancel
            </a>

        </form>

    </div>

</div>

</body>
</html>