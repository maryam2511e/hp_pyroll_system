<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$advance_id = $_GET['id'] ?? '';

if (!ctype_digit((string)$advance_id) || (int)$advance_id <= 0) {
    header("Location: index.php?error=Invalid advance ID");
    exit;
}

$advance_id = (int)$advance_id;

$stmt = $pdo->prepare("
    SELECT
        id,
        employee_id,
        amount,
        request_date,
        status
    FROM advance_management
    WHERE id = ?
");

$stmt->execute([$advance_id]);

$advance = $stmt->fetch();

if (!$advance) {
    header("Location: index.php?error=Advance record not found");
    exit;
}

$employee_stmt = $pdo->query("
    SELECT
        id,
        employee_code,
        first_name,
        last_name
    FROM employees
    WHERE employment_status = 'Active'
    ORDER BY first_name ASC, last_name ASC
");

$employees = $employee_stmt->fetchAll();

$errors = [];

$employee_id = (string)$advance['employee_id'];
$amount = $advance['amount'];
$request_date = $advance['request_date'];
$status = $advance['status'];

$allowed_statuses = [
    'Pending',
    'Approved',
    'Paid'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id = trim($_POST['employee_id'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $request_date = trim($_POST['request_date'] ?? '');
    $status = trim($_POST['status'] ?? '');

    if (
        $employee_id === '' ||
        !ctype_digit($employee_id) ||
        (int)$employee_id <= 0
    ) {
        $errors[] = "Please select a valid employee.";
    }

    if ($amount === '') {
        $errors[] = "Advance amount is required.";
    } elseif (!is_numeric($amount)) {
        $errors[] = "Advance amount must be a valid number.";
    } elseif ((float)$amount <= 0) {
        $errors[] = "Advance amount must be greater than zero.";
    } elseif (strlen($amount) > 12) {
        $errors[] = "Advance amount is too large.";
    }

    if ($request_date === '') {

        $errors[] = "Request date is required.";

    } else {

        $date_object = DateTime::createFromFormat(
            'Y-m-d',
            $request_date
        );

        if (
            !$date_object ||
            $date_object->format('Y-m-d') !== $request_date
        ) {
            $errors[] = "Please enter a valid request date.";
        } elseif ($request_date > date('Y-m-d')) {
            $errors[] = "Request date cannot be a future date.";
        }
    }

    if (!in_array($status, $allowed_statuses, true)) {
        $errors[] = "Invalid advance status selected.";
    }

    if (empty($errors)) {

        $employee_check = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = ?
            AND employment_status = 'Active'
        ");

        $employee_check->execute([
            (int)$employee_id
        ]);

        if (!$employee_check->fetch()) {
            $errors[] = "Selected employee is not active or does not exist.";
        }
    }

    if (empty($errors)) {

        $old_employee_id = (int)$advance['employee_id'];
        $old_amount = (float)$advance['amount'];
        $old_request_date = $advance['request_date'];
        $old_status = $advance['status'];

        $update = $pdo->prepare("
            UPDATE advance_management
            SET
                employee_id = ?,
                amount = ?,
                request_date = ?,
                status = ?
            WHERE id = ?
        ");

        $update->execute([
            (int)$employee_id,
            (float)$amount,
            $request_date,
            $status,
            $advance_id
        ]);

        $audit = $pdo->prepare("
            INSERT INTO audit_logs
            (
                action,
                timestamp
            )
            VALUES (?, CURRENT_TIMESTAMP)
        ");

        $audit->execute([
            "Advance updated. Advance ID: {$advance_id}. Old employee ID: {$old_employee_id}, new employee ID: {$employee_id}. Old amount: {$old_amount}, new amount: {$amount}. Old date: {$old_request_date}, new date: {$request_date}. Old status: {$old_status}, new status: {$status}"
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

    <title>Edit Advance - HR Payroll System</title>

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
            max-width: 850px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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

    <a href="index.php" class="active">Advances</a>

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

        <h2>Edit Salary Advance</h2>

        <p class="text-muted mb-0">
            Update the selected salary advance record.
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

        <form method="POST">

            <div class="mb-3">

                <label for="employee_id" class="form-label">
                    Employee <span class="text-danger">*</span>
                </label>

                <select
                    name="employee_id"
                    id="employee_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Select Employee
                    </option>

                    <?php foreach ($employees as $employee): ?>

                        <option
                            value="<?= (int)$employee['id'] ?>"
                            <?= (string)$employee_id === (string)$employee['id'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(
                                $employee['employee_code'] .
                                ' - ' .
                                $employee['first_name'] .
                                ' ' .
                                $employee['last_name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mb-3">

                <label for="amount" class="form-label">
                    Advance Amount <span class="text-danger">*</span>
                </label>

                <input
                    type="number"
                    name="amount"
                    id="amount"
                    class="form-control"
                    value="<?= htmlspecialchars($amount) ?>"
                    min="0.01"
                    max="999999999.99"
                    step="0.01"
                    inputmode="decimal"
                    required
                >

            </div>

            <div class="mb-3">

                <label for="request_date" class="form-label">
                    Request Date <span class="text-danger">*</span>
                </label>

                <input
                    type="date"
                    name="request_date"
                    id="request_date"
                    class="form-control"
                    value="<?= htmlspecialchars($request_date) ?>"
                    max="<?= date('Y-m-d') ?>"
                    required
                >

            </div>

            <div class="mb-3">

                <label for="status" class="form-label">
                    Status <span class="text-danger">*</span>
                </label>

                <select
                    name="status"
                    id="status"
                    class="form-select"
                    required
                >

                    <?php foreach ($allowed_statuses as $allowed_status): ?>

                        <option
                            value="<?= htmlspecialchars($allowed_status) ?>"
                            <?= $status === $allowed_status ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($allowed_status) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <button type="submit" class="btn btn-primary">
                    Update Advance
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