
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

// Get deduction record
$stmt = $pdo->prepare("
    SELECT
        d.*,
        e.first_name,
        e.last_name,
        e.employee_code,
        e.employment_status
    FROM deduction_management d
    INNER JOIN employees e ON e.id = d.employee_id
    WHERE d.id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$deduction = $stmt->fetch();

if (!$deduction) {
    header("Location: index.php?error=not_found");
    exit;
}

// Load active employees and current employee
$stmt = $pdo->query("
    SELECT id, employee_code, first_name, last_name, employment_status
    FROM employees
    WHERE employment_status = 'Active'
       OR id = " . (int)$deduction['employee_id'] . "
    ORDER BY first_name ASC
");

$employees = $stmt->fetchAll();

$errors = [];

$employee_id = (string)$deduction['employee_id'];
$amount = (string)$deduction['amount'];
$reason = $deduction['reason'] ?? '';
$date = $deduction['date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id = trim($_POST['employee_id'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $date = trim($_POST['date'] ?? '');

    // Employee validation
    if ($employee_id === '' || !ctype_digit($employee_id)) {
        $errors[] = "Please select a valid employee.";
    }

    // Amount validation
    if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = "Deduction amount must be greater than zero.";
    } elseif (strlen($amount) > 12) {
        $errors[] = "Deduction amount is too large.";
    }

    // Reason validation
    if ($reason === '') {
        $errors[] = "Please enter deduction reason.";
    } elseif (mb_strlen($reason) > 500) {
        $errors[] = "Reason cannot exceed 500 characters.";
    }

    // Date validation
    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        $errors[] = "Please enter a valid deduction date.";
    } elseif ($date > date('Y-m-d')) {
        $errors[] = "Future dates are not allowed.";
    }

    // Verify employee
    if (!$errors) {

        $employeeStmt = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = ?
            AND (
                employment_status = 'Active'
                OR id = ?
            )
            LIMIT 1
        ");

        $employeeStmt->execute([
            (int)$employee_id,
            (int)$deduction['employee_id']
        ]);

        if (!$employeeStmt->fetch()) {
            $errors[] = "Selected employee is not available.";
        }
    }

    // Update record
    if (!$errors) {

        try {

            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("
                UPDATE deduction_management
                SET
                    employee_id = ?,
                    amount = ?,
                    reason = ?,
                    date = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                (int)$employee_id,
                (float)$amount,
                $reason,
                $date,
                $id
            ]);

            // Audit log
            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $logStmt->execute([
                "Deduction ID {$id} updated by Super Admin. "
                . "Old amount: {$deduction['amount']}, "
                . "New amount: {$amount}"
            ]);

            $pdo->commit();

            header("Location: index.php?success=1");
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = "Unable to update deduction. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Deduction - HR Payroll System</title>

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
    <a href="index.php" class="active">Deductions</a>
    <a href="../overtime/index.php">Overtime</a>
    <a href="../bonuses/index.php">Bonuses</a>
    <a href="../leaves/index.php">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>

</div>

<div class="main">

    <h2 class="mb-2">Edit Deduction</h2>

    <p class="text-muted mb-4">
        Update employee deduction information.
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
                                $employee['last_name'] .
                                ($employee['employment_status'] !== 'Active' ? ' (Inactive)' : '')
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Deduction Amount <span class="text-danger">*</span>
                </label>

                <input
                    type="number"
                    name="amount"
                    class="form-control"
                    min="0.01"
                    step="0.01"
                    max="999999999.99"
                    value="<?= htmlspecialchars($amount) ?>"
                    required
                >

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Reason <span class="text-danger">*</span>
                </label>

                <textarea
                    name="reason"
                    class="form-control"
                    rows="4"
                    maxlength="500"
                    required
                ><?= htmlspecialchars($reason) ?></textarea>

            </div>

            <div class="mb-4">

                <label class="form-label">
                    Deduction Date <span class="text-danger">*</span>
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

            <button type="submit" class="btn btn-primary">
                Update Deduction
            </button>

            <a href="index.php" class="btn btn-secondary">
                Cancel
            </a>

        </form>

    </div>

</div>

</body>
</html>
