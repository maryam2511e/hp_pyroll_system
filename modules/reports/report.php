<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

/*
|--------------------------------------------------------------------------
| Payroll records
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.employee_id,
        p.month_year,
        p.gross_salary,
        p.net_salary,
        p.approval_status,
        p.approved_at,

        e.employee_code,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.designation,
        e.employment_status,
        e.base_salary,

        COALESCE(pp.total_paid, 0) AS total_paid

    FROM payroll_management p

    LEFT JOIN employees e
        ON e.id = p.employee_id

    LEFT JOIN (
        SELECT
            payroll_id,
            SUM(amount) AS total_paid
        FROM payroll_payments
        GROUP BY payroll_id
    ) pp
        ON pp.payroll_id = p.id

    WHERE p.month_year = ?

    ORDER BY e.employee_code ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$month]);

$records = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Payroll settings
|--------------------------------------------------------------------------
*/

$defaultOvertimeMultiplier = 1.5;

$settingStmt = $pdo->prepare("
    SELECT setting_value
    FROM system_settings
    WHERE setting_key = ?
    LIMIT 1
");

$settingStmt->execute(['default_overtime_multiplier']);

$settingValue = $settingStmt->fetchColumn();

if ($settingValue !== false && is_numeric($settingValue) && (float)$settingValue > 0) {
    $defaultOvertimeMultiplier = (float)$settingValue;
}

/*
|--------------------------------------------------------------------------
| Totals
|--------------------------------------------------------------------------
*/

$totalEmployees = count($records);

$totalBasic = 0;
$totalOvertime = 0;
$totalSunday = 0;
$totalBonus = 0;
$totalDeductions = 0;
$totalGross = 0;
$totalNet = 0;
$totalPaid = 0;
$totalRemaining = 0;

/*
|--------------------------------------------------------------------------
| Component calculations
|--------------------------------------------------------------------------
*/

foreach ($records as &$record) {

    $employeeId = (int)$record['employee_id'];

    $basicSalary = (float)$record['base_salary'];

    /*
    |--------------------------------------------------------------------------
    | Overtime
    |--------------------------------------------------------------------------
    */

    $overtimeStmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(
                    hours *
                    CASE
                        WHEN multiplier IS NULL OR multiplier <= 0
                        THEN ?
                        ELSE multiplier
                    END
                ),
                0
            )
        FROM overtime_management
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $overtimeStmt->execute([
        $defaultOvertimeMultiplier,
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $overtimeHoursWeighted = (float)$overtimeStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Working settings
    |--------------------------------------------------------------------------
    */

    $daysPerMonth = 30;
    $workingHoursPerDay = 8;

    $daysStmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    $daysStmt->execute(['payroll_days_per_month']);

    $daysValue = $daysStmt->fetchColumn();

    if ($daysValue !== false && is_numeric($daysValue) && (float)$daysValue > 0) {
        $daysPerMonth = (float)$daysValue;
    }

    $hoursStmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    $hoursStmt->execute(['working_hours_per_day']);

    $hoursValue = $hoursStmt->fetchColumn();

    if ($hoursValue !== false && is_numeric($hoursValue) && (float)$hoursValue > 0) {
        $workingHoursPerDay = (float)$hoursValue;
    }

    $hourlyRate = 0;

    if ($daysPerMonth > 0 && $workingHoursPerDay > 0) {
        $hourlyRate =
            $basicSalary /
            $daysPerMonth /
            $workingHoursPerDay;
    }

    $overtimeAmount = $overtimeHoursWeighted * $hourlyRate;

    /*
    |--------------------------------------------------------------------------
    | Sunday allowance
    |--------------------------------------------------------------------------
    */

    $sundayStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM sunday_allowance_mgmt
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $sundayStmt->execute([
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $sundayAllowance = (float)$sundayStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Bonus
    |--------------------------------------------------------------------------
    */

    $bonusStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM bonus_management
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $bonusStmt->execute([
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $bonusAmount = (float)$bonusStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Deductions
    |--------------------------------------------------------------------------
    */

    $deductionStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM deduction_management
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $deductionStmt->execute([
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $deductionAmount = (float)$deductionStmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Official payroll values
    |--------------------------------------------------------------------------
    */

    $grossSalary = (float)$record['gross_salary'];
    $netSalary = (float)$record['net_salary'];

    $totalPaidForEmployee = (float)$record['total_paid'];

    $remainingBalance = max(
        0,
        $netSalary - $totalPaidForEmployee
    );

    /*
    |--------------------------------------------------------------------------
    | Dynamic payment status
    |--------------------------------------------------------------------------
    */

    if ($remainingBalance <= 0 && $netSalary > 0) {
        $paymentStatus = 'Paid';
    } elseif ($totalPaidForEmployee > 0) {
        $paymentStatus = 'Partial';
    } else {
        $paymentStatus = 'Pending';
    }

    $record['basic_salary'] = $basicSalary;
    $record['overtime_amount'] = $overtimeAmount;
    $record['sunday_allowance'] = $sundayAllowance;
    $record['bonus_amount'] = $bonusAmount;
    $record['deduction_amount'] = $deductionAmount;
    $record['gross_salary'] = $grossSalary;
    $record['net_salary'] = $netSalary;
    $record['total_paid'] = $totalPaidForEmployee;
    $record['remaining_balance'] = $remainingBalance;
    $record['payment_status_dynamic'] = $paymentStatus;

    $totalBasic += $basicSalary;
    $totalOvertime += $overtimeAmount;
    $totalSunday += $sundayAllowance;
    $totalBonus += $bonusAmount;
    $totalDeductions += $deductionAmount;
    $totalGross += $grossSalary;
    $totalNet += $netSalary;
    $totalPaid += $totalPaidForEmployee;
    $totalRemaining += $remainingBalance;
}

unset($record);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detailed Payroll Report</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f5f7fa;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: #212529;
            padding: 20px 15px;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .sidebar h4 {
            color: #fff;
            margin-bottom: 25px;
        }

        .sidebar a {
            display: block;
            color: #adb5bd;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 5px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #343a40;
            color: #fff;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .table {
            font-size: 14px;
        }

        @media print {

            .sidebar,
            .no-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0;
                padding: 0;
            }

            body {
                background: white;
            }

            .card {
                box-shadow: none;
            }

            .table {
                font-size: 11px;
            }
        }

    </style>

</head>

<body>

<!-- Sidebar -->

<div class="sidebar">

    <h4>HR Payroll System</h4>

    <a href="../../dashboard.php">
        Dashboard
    </a>

    <a href="../employees/index.php">
        Employees
    </a>

    <a href="../attendance/index.php">
        Attendance
    </a>

    <a href="../salary/index.php">
        Salary Management
    </a>

    <a href="../advances/index.php">
        Advances
    </a>

    <a href="../deductions/index.php">
        Deductions
    </a>

    <a href="../overtime/index.php">
        Overtime
    </a>

    <a href="../bonuses/index.php">
        Bonuses
    </a>

    <a href="../leaves/index.php">
        Annual Leaves
    </a>

    <a href="../payroll/index.php">
        Payroll
    </a>

    <a href="index.php" class="active">
        Reports
    </a>

    <a href="../settings/index.php">
        Settings
    </a>

    <a href="../../logout.php">
        Logout
    </a>

</div>

<!-- Main Content -->

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2>
                Detailed Payroll Report
            </h2>

            <p class="text-muted mb-0">
                Payroll report for <?= htmlspecialchars($month) ?>
            </p>

        </div>

        <div class="no-print">

            <button
                onclick="window.print()"
                class="btn btn-dark"
            >
                Print / Save PDF
            </button>

            <a
                href="index.php?month=<?= urlencode($month) ?>"
                class="btn btn-secondary"
            >
                Back
            </a>

        </div>

    </div>

    <!-- Company Heading -->

    <div class="card mb-4">

        <div class="card-body text-center">

            <h3>
                HR & Payroll Management System
            </h3>

            <h5>
                Monthly Payroll Report
            </h5>

            <p class="mb-0">
                Month:
                <strong><?= htmlspecialchars($month) ?></strong>
            </p>

        </div>

    </div>

    <!-- Summary -->

    <div class="row g-3 mb-4">

        <div class="col-md-3">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Employees
                    </h6>

                    <h3>
                        <?= $totalEmployees ?>
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Gross
                    </h6>

                    <h3>
                        Rs. <?= number_format($totalGross, 2) ?>
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Net
                    </h6>

                    <h3>
                        Rs. <?= number_format($totalNet, 2) ?>
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Paid
                    </h6>

                    <h3>
                        Rs. <?= number_format($totalPaid, 2) ?>
                    </h3>

                </div>

            </div>

        </div>

    </div>

    <!-- Salary Component Summary -->

    <div class="card mb-4">

        <div class="card-body">

            <h5 class="mb-3">
                Salary Component Summary
            </h5>

            <div class="table-responsive">

                <table class="table table-bordered">

                    <thead class="table-dark">

                        <tr>

                            <th>Basic Salary</th>

                            <th>Overtime</th>

                            <th>Sunday Allowance</th>

                            <th>Bonus</th>

                            <th>Deductions</th>

                            <th>Gross Salary</th>

                            <th>Net Salary</th>

                            <th>Remaining</th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td>
                                Rs. <?= number_format($totalBasic, 2) ?>
                            </td>

                            <td>
                                Rs. <?= number_format($totalOvertime, 2) ?>
                            </td>

                            <td>
                                Rs. <?= number_format($totalSunday, 2) ?>
                            </td>

                            <td>
                                Rs. <?= number_format($totalBonus, 2) ?>
                            </td>

                            <td>
                                Rs. <?= number_format($totalDeductions, 2) ?>
                            </td>

                            <td>
                                <strong>
                                    Rs. <?= number_format($totalGross, 2) ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    Rs. <?= number_format($totalNet, 2) ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    Rs. <?= number_format($totalRemaining, 2) ?>
                                </strong>
                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Payroll Table -->

    <div class="card">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered align-middle">

                    <thead class="table-dark">

                        <tr>

                            <th>#</th>

                            <th>Employee Code</th>

                            <th>Employee Name</th>

                            <th>Designation</th>

                            <th>Status</th>

                            <th>Basic</th>

                            <th>Overtime</th>

                            <th>Sunday</th>

                            <th>Bonus</th>

                            <th>Deductions</th>

                            <th>Gross</th>

                            <th>Net</th>

                            <th>Paid</th>

                            <th>Remaining</th>

                            <th>Payment Status</th>

                            <th>Approval</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (empty($records)): ?>

                        <tr>

                            <td colspan="16" class="text-center py-4">

                                No payroll record found.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($records as $index => $record): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['employee_code'] ?? 'N/A') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['employee_name'] ?? 'N/A') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['designation'] ?? 'N/A') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['employment_status'] ?? 'N/A') ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['basic_salary'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['overtime_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['sunday_allowance'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['bonus_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['deduction_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['gross_salary'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        Rs. <?= number_format(
                                            (float)$record['net_salary'],
                                            2
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    Rs. <?= number_format(
                                        (float)$record['total_paid'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        Rs. <?= number_format(
                                            (float)$record['remaining_balance'],
                                            2
                                        ) ?>
                                    </strong>
                                </td>

                                <td>

                                    <?php
                                    $paymentStatus =
                                        $record['payment_status_dynamic'];

                                    $paymentClass = 'bg-secondary';

                                    if ($paymentStatus === 'Paid') {
                                        $paymentClass = 'bg-success';
                                    } elseif ($paymentStatus === 'Partial') {
                                        $paymentClass = 'bg-warning text-dark';
                                    }
                                    ?>

                                    <span class="badge <?= $paymentClass ?>">
                                        <?= htmlspecialchars($paymentStatus) ?>
                                    </span>

                                </td>

                                <td>

                                    <?php
                                    $approvalStatus =
                                        $record['approval_status'] ?? 'Draft';
                                    ?>

                                    <?php if ($approvalStatus === 'Approved'): ?>

                                        <span class="badge bg-success">
                                            Approved
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-warning text-dark">
                                            Draft
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                    <?php if (!empty($records)): ?>

                    <tfoot class="table-light">

                        <tr>

                            <th colspan="5" class="text-end">
                                Total
                            </th>

                            <th>
                                Rs. <?= number_format($totalBasic, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalOvertime, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalSunday, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalBonus, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalDeductions, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalGross, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalNet, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalPaid, 2) ?>
                            </th>

                            <th>
                                Rs. <?= number_format($totalRemaining, 2) ?>
                            </th>

                            <th colspan="2"></th>

                        </tr>

                    </tfoot>

                    <?php endif; ?>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>