<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

// Existing overtime record
$stmt = $pdo->prepare("
    SELECT *
    FROM overtime_management
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$overtime = $stmt->fetch();

if (!$overtime) {
    header("Location: index.php?error=not_found");
    exit;
}

// Load default overtime multiplier from Settings
$defaultMultiplier = 1.5;

$settingStmt = $pdo->prepare("
    SELECT setting_value
    FROM system_settings
    WHERE setting_key = ?
    LIMIT 1
");

$settingStmt->execute(['default_overtime_multiplier']);

$settingValue = $settingStmt->fetchColumn();

if ($settingValue !== false && is_numeric($settingValue) && (float)$settingValue > 0) {
    $defaultMultiplier = (float)$settingValue;
}

// Employees: active employees + current employee
$employeeStmt = $pdo->prepare("
    SELECT id, employee_code, first_name, last_name, employment_status
    FROM employees
    WHERE employment_status = 'Active' OR id = ?
    ORDER BY first_name ASC
");
$employeeStmt->execute([$overtime['employee_id']]);
$employees = $employeeStmt->fetchAll();

$employeeId = $overtime['employee_id'];
$hours = $overtime['hours'];
$date = $overtime['date'];

// Existing multiplier is preserved.
// Settings default is only used if existing multiplier is invalid.
$existingMultiplier = (float)$overtime['multiplier'];

if ($existingMultiplier > 0) {
    $multiplier = $existingMultiplier;
} else {
    $multiplier = $defaultMultiplier;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $hours = trim($_POST['hours'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $multiplier = trim($_POST['multiplier'] ?? '');

    // Employee validation
    if (!$employeeId || $employeeId <= 0) {

        $errors[] = "Please select a valid employee.";

    } else {

        $checkEmployee = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = ?
            AND (employment_status = 'Active' OR id = ?)
            LIMIT 1
        ");

        $checkEmployee->execute([
            $employeeId,
            $overtime['employee_id']
        ]);

        if (!$checkEmployee->fetch()) {
            $errors[] = "Selected employee is not available.";
        }
    }

    // Hours validation
    if (!is_numeric($hours) || (float)$hours <= 0 || (float)$hours > 24) {
        $errors[] = "Overtime hours must be greater than 0 and maximum 24.";
    }

    // Date validation
    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {

        $errors[] = "Please enter a valid overtime date.";

    } elseif ($date > date('Y-m-d')) {

        $errors[] = "Overtime date cannot be in the future.";
    }

    // Multiplier validation
    if (
        !is_numeric($multiplier) ||
        (float)$multiplier <= 0 ||
        (float)$multiplier > 10
    ) {
        $errors[] = "Multiplier must be greater than 0 and maximum 10.";
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("
                UPDATE overtime_management
                SET employee_id = ?,
                    hours = ?,
                    date = ?,
                    multiplier = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                (int)$employeeId,
                (float)$hours,
                $date,
                (float)$multiplier,
                $id
            ]);

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $logStmt->execute([
                "Overtime record ID {$id} updated by Super Admin."
            ]);

            $pdo->commit();

            header("Location: index.php?success=updated");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = "Unable to update overtime record. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Overtime | HR & Payroll</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
            margin: 0;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #212529;
            color: white;
            padding: 20px 12px;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .sidebar h4 {
            text-align: center;
            margin-bottom: 25px;
        }

        .sidebar a {
            display: block;
            color: #ddd;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #0d6efd;
            color: white;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .form-card {
            max-width: 750px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        @media (max-width: 768px) {

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

        }

    </style>

</head>

<body>

<div class="sidebar">

    <h4>HR & Payroll</h4>

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

<div class="main-content">

    <h2 class="mb-4">Edit Overtime</h2>

    <div class="form-card">

        <?php if (!empty($errors)): ?>

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
                    Employee *
                </label>

                <select
                    name="employee_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Employee
                    </option>

                    <?php foreach ($employees as $employee): ?>

                        <option
                            value="<?= (int)$employee['id'] ?>"
                            <?= (string)$employeeId === (string)$employee['id'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $employee['employee_code'] . ' - ' .
                                $employee['first_name'] . ' ' .
                                $employee['last_name'] . ' (' .
                                $employee['employment_status'] . ')'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Overtime Hours *
                </label>

                <input
                    type="number"
                    name="hours"
                    class="form-control"
                    step="0.01"
                    min="0.01"
                    max="24"
                    value="<?= htmlspecialchars((string)$hours) ?>"
                    required
                >

                <small class="text-muted">
                    Maximum 24 hours per day.
                </small>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Overtime Date *
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
                    Overtime Multiplier *
                </label>

                <input
                    type="number"
                    name="multiplier"
                    class="form-control"
                    step="0.01"
                    min="0.01"
                    max="10"
                    value="<?= htmlspecialchars((string)$multiplier) ?>"
                    required
                >

                <small class="text-muted">
                    System default multiplier:
                    <?= htmlspecialchars(number_format($defaultMultiplier, 2)) ?>
                </small>

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Update Overtime
            </button>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </form>

    </div>

</div>

</body>
</html>