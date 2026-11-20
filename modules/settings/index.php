<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| Default Settings
|--------------------------------------------------------------------------
*/

$defaultSettings = [
    'payroll_days_per_month' => '30',
    'working_hours_per_day' => '8',
    'default_overtime_multiplier' => '1.5',
    'default_sunday_allowance' => '0'
];

/*
|--------------------------------------------------------------------------
| Load Settings
|--------------------------------------------------------------------------
*/

$settings = $defaultSettings;

$stmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM system_settings
");

while ($row = $stmt->fetch()) {
    if (array_key_exists($row['setting_key'], $settings)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

/*
|--------------------------------------------------------------------------
| Update Settings
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $payrollDays = trim($_POST['payroll_days_per_month'] ?? '');
    $workingHours = trim($_POST['working_hours_per_day'] ?? '');
    $overtimeMultiplier = trim($_POST['default_overtime_multiplier'] ?? '');
    $sundayAllowance = trim($_POST['default_sunday_allowance'] ?? '');

    $errors = [];

    if (
        $payrollDays === '' ||
        !is_numeric($payrollDays) ||
        (float)$payrollDays <= 0 ||
        (float)$payrollDays > 31
    ) {
        $errors[] = "Payroll days per month must be between 1 and 31.";
    }

    if (
        $workingHours === '' ||
        !is_numeric($workingHours) ||
        (float)$workingHours <= 0 ||
        (float)$workingHours > 24
    ) {
        $errors[] = "Working hours per day must be between 1 and 24.";
    }

    if (
        $overtimeMultiplier === '' ||
        !is_numeric($overtimeMultiplier) ||
        (float)$overtimeMultiplier <= 0
    ) {
        $errors[] = "Overtime multiplier must be greater than 0.";
    }

    if (
        $sundayAllowance === '' ||
        !is_numeric($sundayAllowance) ||
        (float)$sundayAllowance < 0
    ) {
        $errors[] = "Sunday allowance must be 0 or greater.";
    }

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $newSettings = [
                'payroll_days_per_month' => number_format((float)$payrollDays, 2, '.', ''),
                'working_hours_per_day' => number_format((float)$workingHours, 2, '.', ''),
                'default_overtime_multiplier' => number_format((float)$overtimeMultiplier, 2, '.', ''),
                'default_sunday_allowance' => number_format((float)$sundayAllowance, 2, '.', '')
            ];

            foreach ($newSettings as $key => $value) {

                $checkStmt = $pdo->prepare("
                    SELECT id
                    FROM system_settings
                    WHERE setting_key = ?
                    LIMIT 1
                ");

                $checkStmt->execute([$key]);
                $existingId = $checkStmt->fetchColumn();

                if ($existingId) {

                    $updateStmt = $pdo->prepare("
                        UPDATE system_settings
                        SET setting_value = ?, updated_at = NOW()
                        WHERE id = ?
                    ");

                    $updateStmt->execute([
                        $value,
                        $existingId
                    ]);

                } else {

                    $insertStmt = $pdo->prepare("
                        INSERT INTO system_settings
                        (setting_key, setting_value, updated_at)
                        VALUES (?, ?, NOW())
                    ");

                    $insertStmt->execute([
                        $key,
                        $value
                    ]);
                }
            }

            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $auditStmt->execute([
                "System payroll settings updated"
            ]);

            $pdo->commit();

            $settings = $newSettings;

            $message = "Settings updated successfully.";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $error = "Settings update failed. Please try again.";
        }

    } else {
        $error = implode(" ", $errors);

        $settings = [
            'payroll_days_per_month' => $payrollDays,
            'working_hours_per_day' => $workingHours,
            'default_overtime_multiplier' => $overtimeMultiplier,
            'default_sunday_allowance' => $sundayAllowance
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings - HR & Payroll Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #f4f6f9;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #212529;
            color: white;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .sidebar h4 {
            margin-bottom: 25px;
        }

        .sidebar a {
            display: block;
            color: #ddd;
            text-decoration: none;
            padding: 10px 12px;
            margin-bottom: 5px;
            border-radius: 6px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #343a40;
            color: white;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .form-label {
            font-weight: 600;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
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

    <a href="../leaves/index.php">Annual Leaves</a>

    <a href="../payroll/index.php">Payroll</a>

    <a href="../reports/index.php">Reports</a>

    <a href="index.php" class="active">Settings</a>

    <a href="../../logout.php">Logout</a>

</div>

<div class="main">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="mb-1">System Settings</h2>
            <p class="text-muted mb-0">
                Configure payroll calculation rules
            </p>
        </div>

    </div>

    <?php if ($message): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="info-box">

        <strong>Important:</strong>

        These settings control payroll calculations. Changes should be made
        carefully because they can affect future payroll calculations.

    </div>

    <div class="card">

        <div class="card-body">

            <h5 class="card-title mb-4">
                Payroll Configuration
            </h5>

            <form method="POST">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Payroll Days Per Month
                        </label>

                        <input
                            type="number"
                            name="payroll_days_per_month"
                            class="form-control"
                            min="1"
                            max="31"
                            step="1"
                            required
                            value="<?= htmlspecialchars($settings['payroll_days_per_month']) ?>"
                        >

                        <div class="form-text">
                            Used for calculating daily salary.
                            Example: 30
                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Working Hours Per Day
                        </label>

                        <input
                            type="number"
                            name="working_hours_per_day"
                            class="form-control"
                            min="1"
                            max="24"
                            step="0.01"
                            required
                            value="<?= htmlspecialchars($settings['working_hours_per_day']) ?>"
                        >

                        <div class="form-text">
                            Used for calculating hourly salary.
                            Example: 8
                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Default Overtime Multiplier
                        </label>

                        <input
                            type="number"
                            name="default_overtime_multiplier"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            required
                            value="<?= htmlspecialchars($settings['default_overtime_multiplier']) ?>"
                        >

                        <div class="form-text">
                            Example: 1.5 means 1.5 × normal hourly rate.
                        </div>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Default Sunday Allowance
                        </label>

                        <input
                            type="number"
                            name="default_sunday_allowance"
                            class="form-control"
                            min="0"
                            step="0.01"
                            required
                            value="<?= htmlspecialchars($settings['default_sunday_allowance']) ?>"
                        >

                        <div class="form-text">
                            Default allowance amount for Sunday work.
                        </div>

                    </div>

                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">

                    <button
                        type="submit"
                        class="btn btn-primary px-4"
                    >
                        Save Settings
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

</body>
</html>