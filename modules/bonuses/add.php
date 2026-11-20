
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$errors = [];

$employeeId = '';
$amount = '';
$reason = '';
$date = date('Y-m-d');

// Active employees
$employeeStmt = $pdo->query("
    SELECT id, employee_code, first_name, last_name
    FROM employees
    WHERE employment_status = 'Active'
    ORDER BY first_name ASC
");

$employees = $employeeStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId = filter_input(INPUT_POST, 'employee_id', FILTER_VALIDATE_INT);
    $amount = trim($_POST['amount'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $date = trim($_POST['date'] ?? '');

    if (!$employeeId || $employeeId <= 0) {
        $errors[] = "Please select a valid employee.";
    } else {
        $checkEmployee = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE id = ? AND employment_status = 'Active'
            LIMIT 1
        ");
        $checkEmployee->execute([$employeeId]);

        if (!$checkEmployee->fetch()) {
            $errors[] = "Selected employee is not active or does not exist.";
        }
    }

    if (!is_numeric($amount) || $amount <= 0 || $amount > 999999999) {
        $errors[] = "Bonus amount must be greater than 0 and within the allowed limit.";
    }

    if ($reason === '') {
        $errors[] = "Bonus reason is required.";
    } elseif (strlen($reason) > 500) {
        $errors[] = "Bonus reason cannot exceed 500 characters.";
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        $errors[] = "Please enter a valid bonus date.";
    } elseif ($date > date('Y-m-d')) {
        $errors[] = "Bonus date cannot be in the future.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insertStmt = $pdo->prepare("
                INSERT INTO bonus_management
                (employee_id, amount, reason, date)
                VALUES (?, ?, ?, ?)
            ");

            $insertStmt->execute([
                $employeeId,
                $amount,
                $reason,
                $date
            ]);

            $bonusId = $pdo->lastInsertId();

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $logStmt->execute([
                "Bonus record ID {$bonusId} created by Super Admin."
            ]);

            $pdo->commit();

            header("Location: index.php?success=1");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = "Unable to save bonus record. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Bonus | HR & Payroll</title>

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
    <a href="index.php" class="active">Bonuses</a>
    <a href="../leaves/index.php">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>
</div>

<div class="main-content">

    <h2 class="mb-4">Add Employee Bonus</h2>

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
                <label class="form-label">Select Employee *</label>

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
                <label class="form-label">Bonus Amount *</label>

                <input
                    type="number"
                    name="amount"
                    class="form-control"
                    step="0.01"
                    min="0.01"
                    max="999999999"
                    value="<?= htmlspecialchars((string)$amount) ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Bonus Reason *</label>

                <textarea
                    name="reason"
                    class="form-control"
                    rows="3"
                    maxlength="500"
                    required
                ><?= htmlspecialchars($reason) ?></textarea>
            </div>

            <div class="mb-4">
                <label class="form-label">Bonus Date *</label>

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
                Save Bonus
            </button>

            <a href="index.php" class="btn btn-secondary">
                Cancel
            </a>

        </form>

    </div>

</div>

</body>
</html>
