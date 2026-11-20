
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$errors = [];

$leaveId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$leaveId || $leaveId <= 0) {
    header("Location: index.php?error=not_found");
    exit;
}

$leaveStmt = $pdo->prepare("
    SELECT *
    FROM annual_leaves
    WHERE id = ?
    LIMIT 1
");
$leaveStmt->execute([$leaveId]);
$leave = $leaveStmt->fetch();

if (!$leave) {
    header("Location: index.php?error=not_found");
    exit;
}

$employeeId = (string)$leave['employee_id'];
$leaveType = $leave['leave_type'];
$startDate = $leave['start_date'];
$endDate = $leave['end_date'];
$status = $leave['status'];
$reason = $leave['reason'] ?? '';
$totalDays = $leave['total_days'];

$allowedTypes = ['Annual', 'Casual', 'Sick', 'Unpaid', 'Other'];
$allowedStatuses = ['Pending', 'Approved', 'Rejected'];

// Active employees ke saath current employee bhi show hoga.
$employeeStmt = $pdo->prepare("
    SELECT id, employee_code, first_name, last_name
    FROM employees
    WHERE employment_status = 'Active' OR id = ?
    ORDER BY first_name ASC
");
$employeeStmt->execute([$leave['employee_id']]);
$employees = $employeeStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $leaveType = trim($_POST['leave_type'] ?? '');
    $startDate = trim($_POST['start_date'] ?? '');
    $endDate = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

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
        $checkEmployee->execute([$employeeId, $leave['employee_id']]);

        if (!$checkEmployee->fetch()) {
            $errors[] = "Selected employee does not exist or is not active.";
        }
    }

    // Leave type validation
    if (!in_array($leaveType, $allowedTypes, true)) {
        $errors[] = "Please select a valid leave type.";
    }

    // Date validation
    $startObject = DateTime::createFromFormat('!Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('!Y-m-d', $endDate);

    $validStartDate = $startObject &&
        $startObject->format('Y-m-d') === $startDate;

    $validEndDate = $endObject &&
        $endObject->format('Y-m-d') === $endDate;

    if (!$validStartDate) {
        $errors[] = "Please enter a valid start date.";
    } elseif ($startDate < date('Y-m-d')) {
        $errors[] = "Start date cannot be in the past.";
    }

    if (!$validEndDate) {
        $errors[] = "Please enter a valid end date.";
    } elseif ($endDate < date('Y-m-d')) {
        $errors[] = "End date cannot be in the past.";
    }

    if ($validStartDate && $validEndDate) {
        if ($endObject < $startObject) {
            $errors[] = "End date cannot be before start date.";
        } else {
            $totalDays = $startObject->diff($endObject)->days + 1;
        }
    }

    // Status validation
    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = "Please select a valid leave status.";
    }

    // Reason validation
    if (strlen($reason) > 1000) {
        $errors[] = "Reason cannot exceed 1000 characters.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare("
                UPDATE annual_leaves
                SET
                    employee_id = ?,
                    leave_type = ?,
                    start_date = ?,
                    end_date = ?,
                    total_days = ?,
                    status = ?,
                    reason = ?
                WHERE id = ?
            ");

            $updateStmt->execute([
                $employeeId,
                $leaveType,
                $startDate,
                $endDate,
                $totalDays,
                $status,
                $reason,
                $leaveId
            ]);

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $logStmt->execute([
                "Annual leave record ID {$leaveId} updated by Super Admin."
            ]);

            $pdo->commit();

            header("Location: view.php?id=" . $leaveId . "&success=1");
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = "Unable to update leave record. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Annual Leave | HR & Payroll</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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
    <a href="../overtime/index.php">Overtime</a>
    <a href="../bonuses/index.php">Bonuses</a>
    <a href="index.php" class="active">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>
</div>

<div class="main-content">

    <h2 class="mb-4">Edit Annual Leave</h2>

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
                <label class="form-label">Employee *</label>

                <select name="employee_id" class="form-select" required>
                    <option value="">Select Employee</option>

                    <?php foreach ($employees as $employee): ?>
                        <option
                            value="<?= (int)$employee['id'] ?>"
                            <?= (string)$employeeId === (string)$employee['id'] ? 'selected' : '' ?>
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
                <label class="form-label">Leave Type *</label>

                <select name="leave_type" class="form-select" required>
                    <?php foreach ($allowedTypes as $type): ?>
                        <option
                            value="<?= htmlspecialchars($type) ?>"
                            <?= $leaveType === $type ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Start Date *</label>

                    <input
                        type="date"
                        name="start_date"
                        class="form-control"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($startDate) ?>"
                        required
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">End Date *</label>

                    <input
                        type="date"
                        name="end_date"
                        class="form-control"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($endDate) ?>"
                        required
                    >
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Leave Status *</label>

                <select name="status" class="form-select" required>
                    <?php foreach ($allowedStatuses as $item): ?>
                        <option
                            value="<?= htmlspecialchars($item) ?>"
                            <?= $status === $item ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">Reason</label>

                <textarea
                    name="reason"
                    class="form-control"
                    rows="4"
                    maxlength="1000"
                ><?= htmlspecialchars($reason) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                Update Leave
            </button>

            <a href="view.php?id=<?= (int)$leaveId ?>" class="btn btn-secondary">
                Cancel
            </a>

        </form>

    </div>

</div>

</body>
</html>
