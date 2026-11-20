<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$currentMonth = date('Y-m');
$selectedMonth = $_GET['month'] ?? $currentMonth;

if (
    !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)
    || $selectedMonth > $currentMonth
) {
    $selectedMonth = $currentMonth;
}

/*
|--------------------------------------------------------------------------
| Payroll Records
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        p.id,
        p.employee_id,
        p.month_year,
        p.gross_salary,
        p.net_salary,
        p.payment_status,
        p.approval_status,

        e.employee_code,
        CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
        e.designation,
        e.base_salary,

        COALESCE(pay.total_paid, 0) AS total_paid

    FROM payroll_management p

    LEFT JOIN employees e
        ON e.id = p.employee_id

    LEFT JOIN (
        SELECT
            payroll_id,
            SUM(amount) AS total_paid
        FROM payroll_payments
        GROUP BY payroll_id
    ) pay
        ON pay.payroll_id = p.id

    WHERE p.month_year = ?

    ORDER BY e.employee_code ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$selectedMonth]);

$payrolls = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Calculate Component Amounts
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

if (
    $settingValue !== false &&
    is_numeric($settingValue) &&
    (float)$settingValue > 0
) {
    $defaultOvertimeMultiplier = (float)$settingValue;
}

/*
|--------------------------------------------------------------------------
| Totals
|--------------------------------------------------------------------------
*/

$totalRecords = count($payrolls);

$totalGross = 0;
$totalNet = 0;
$totalPaid = 0;
$totalRemaining = 0;

$totalBasic = 0;
$totalOvertime = 0;
$totalSunday = 0;
$totalBonus = 0;
$totalDeductions = 0;

foreach ($payrolls as &$row) {

    $employeeId = (int)$row['employee_id'];

    $monthStart = $selectedMonth . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    /*
    |--------------------------------------------------------------------------
    | Basic Salary
    |--------------------------------------------------------------------------
    */

    $basicSalary = (float)($row['base_salary'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Overtime
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(
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

    $stmt->execute([
        $defaultOvertimeMultiplier,
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $overtimeAmount = (float)$stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Sunday Allowance
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM sunday_allowance_mgmt
        WHERE employee_id = ?
          AND date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $sundayAllowance = (float)$stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Bonus
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM bonus_management
        WHERE employee_id = ?
          AND date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $bonusAmount = (float)$stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Deductions
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM deduction_management
        WHERE employee_id = ?
          AND date BETWEEN ? AND ?
    ");

    $stmt->execute([
        $employeeId,
        $monthStart,
        $monthEnd
    ]);

    $deductionAmount = (float)$stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Store Components
    |--------------------------------------------------------------------------
    */

    $row['basic_salary'] = $basicSalary;
    $row['overtime_amount'] = $overtimeAmount;
    $row['sunday_allowance'] = $sundayAllowance;
    $row['bonus_amount'] = $bonusAmount;
    $row['deduction_amount'] = $deductionAmount;

    /*
    |--------------------------------------------------------------------------
    | Payment Calculations
    |--------------------------------------------------------------------------
    */

    $gross = (float)$row['gross_salary'];
    $net = (float)$row['net_salary'];
    $paid = (float)$row['total_paid'];

    $remaining = max(
        0,
        round($net - $paid, 2)
    );

    $row['remaining_balance'] = $remaining;

    if ($remaining <= 0 && $net > 0) {

        $row['calculated_status'] = 'Paid';

    } elseif ($paid > 0) {

        $row['calculated_status'] = 'Partial';

    } else {

        $row['calculated_status'] = 'Pending';

    }

    /*
    |--------------------------------------------------------------------------
    | Totals
    |--------------------------------------------------------------------------
    */

    $totalGross += $gross;
    $totalNet += $net;
    $totalPaid += $paid;
    $totalRemaining += $remaining;

    $totalBasic += $basicSalary;
    $totalOvertime += $overtimeAmount;
    $totalSunday += $sundayAllowance;
    $totalBonus += $bonusAmount;
    $totalDeductions += $deductionAmount;
}

unset($row);

/*
|--------------------------------------------------------------------------
| Escape Function
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Payroll Reports</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background-color: #f5f7fa;
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

        .summary-card {
            min-height: 130px;
        }

        .table th {
            white-space: nowrap;
        }

        .action-cell {
            min-width: 100px;
            width: 100px;
            text-align: center;
            white-space: nowrap;
        }

        .view-btn {
            display: inline-block;
            padding: 6px 14px;
            background-color: #0d6efd;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #0d6efd;
            cursor: pointer;
        }

        .view-btn:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            color: #ffffff !important;
            text-decoration: none;
        }

        .component-table th,
        .component-table td {
            white-space: nowrap;
        }

        @media(max-width: 768px) {

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


<div class="main-content">

    <!-- Page Header -->

    <div class="mb-4">

        <h2>
            Payroll Reports
        </h2>

        <p class="text-muted mb-0">
            Monthly payroll report and salary summary
        </p>

    </div>


    <!-- Report Controls -->

    <div class="card mb-4">

        <div class="card-body">

            <form method="GET"
                  class="row g-3 align-items-end">

                <div class="col-md-4">

                    <label class="form-label">
                        Select Payroll Month
                    </label>

                    <input
                        type="month"
                        name="month"
                        class="form-control"
                        value="<?= e($selectedMonth) ?>"
                        max="<?= e($currentMonth) ?>"
                    >

                </div>


                <div class="col-md-3">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Generate Report
                    </button>

                </div>


                <div class="col-md-5 d-flex gap-2 flex-wrap">

                    <a
                        href="excel.php?month=<?= urlencode($selectedMonth) ?>"
                        class="btn btn-success"
                    >
                        Export Excel
                    </a>

                    <a
                        href="pdf.php?month=<?= urlencode($selectedMonth) ?>"
                        class="btn btn-danger"
                    >
                        Download PDF
                    </a>

                    <a
                        href="report.php?month=<?= urlencode($selectedMonth) ?>"
                        class="btn btn-secondary"
                    >
                        View / Print
                    </a>

                </div>

            </form>

        </div>

    </div>


    <!-- Summary Cards -->

    <div class="row g-3 mb-4">

        <div class="col-md-3">

            <div class="card summary-card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Payroll Records
                    </h6>

                    <h3>
                        <?= $totalRecords ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card summary-card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Gross Salary
                    </h6>

                    <h3>
                        Rs.
                        <?= number_format($totalGross, 2) ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card summary-card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Net Salary
                    </h6>

                    <h3>
                        Rs.
                        <?= number_format($totalNet, 2) ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card summary-card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Total Paid
                    </h6>

                    <h3 class="text-success">
                        Rs.
                        <?= number_format($totalPaid, 2) ?>
                    </h3>

                </div>

            </div>

        </div>


        <div class="col-md-3">

            <div class="card summary-card">

                <div class="card-body">

                    <h6 class="text-muted">
                        Remaining Amount
                    </h6>

                    <h3 class="text-danger">
                        Rs.
                        <?= number_format($totalRemaining, 2) ?>
                    </h3>

                </div>

            </div>

        </div>

    </div>


    <!-- Component Summary -->

    <div class="card mb-4">

        <div class="card-header">

            <h5 class="mb-0">
                Salary Component Summary
            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered mb-0">

                    <thead class="table-light">

                    <tr>

                        <th>
                            Basic Salary
                        </th>

                        <th>
                            Overtime
                        </th>

                        <th>
                            Sunday Allowance
                        </th>

                        <th>
                            Bonus
                        </th>

                        <th>
                            Deductions
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <tr>

                        <td>
                            Rs.
                            <?= number_format(
                                $totalBasic,
                                2
                            ) ?>
                        </td>

                        <td>
                            Rs.
                            <?= number_format(
                                $totalOvertime,
                                2
                            ) ?>
                        </td>

                        <td>
                            Rs.
                            <?= number_format(
                                $totalSunday,
                                2
                            ) ?>
                        </td>

                        <td>
                            Rs.
                            <?= number_format(
                                $totalBonus,
                                2
                            ) ?>
                        </td>

                        <td class="text-danger">
                            Rs.
                            <?= number_format(
                                $totalDeductions,
                                2
                            ) ?>
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

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Payroll Report
                </h5>

                <span class="badge bg-secondary">
                    <?= e($selectedMonth) ?>
                </span>

            </div>


            <div class="table-responsive">

                <table class="table table-bordered table-hover align-middle">

                    <thead class="table-dark">

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Employee Code
                        </th>

                        <th>
                            Employee Name
                        </th>

                        <th>
                            Designation
                        </th>

                        <th>
                            Basic
                        </th>

                        <th>
                            Overtime
                        </th>

                        <th>
                            Sunday
                        </th>

                        <th>
                            Bonus
                        </th>

                        <th>
                            Deductions
                        </th>

                        <th>
                            Gross Salary
                        </th>

                        <th>
                            Net Salary
                        </th>

                        <th>
                            Total Paid
                        </th>

                        <th>
                            Remaining
                        </th>

                        <th>
                            Payment Status
                        </th>

                        <th>
                            Approval
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($payrolls)): ?>

                        <tr>

                            <td
                                colspan="16"
                                class="text-center text-muted py-4"
                            >
                                No payroll record found for
                                <?= e($selectedMonth) ?>.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($payrolls as $index => $row): ?>

                            <?php

                            $status =
                                $row['calculated_status'];

                            if ($status === 'Paid') {

                                $badgeClass =
                                    'bg-success';

                            } elseif ($status === 'Partial') {

                                $badgeClass =
                                    'bg-warning text-dark';

                            } else {

                                $badgeClass =
                                    'bg-secondary';

                            }

                            $approval =
                                $row['approval_status']
                                ?? 'Draft';

                            if ($approval === 'Approved') {

                                $approvalClass =
                                    'bg-success';

                            } else {

                                $approvalClass =
                                    'bg-warning text-dark';

                            }

                            ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <?= e(
                                        $row['employee_code']
                                        ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $row['employee_name']
                                        ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $row['designation']
                                        ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format(
                                        $row['basic_salary'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format(
                                        $row['overtime_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format(
                                        $row['sunday_allowance'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format(
                                        $row['bonus_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td class="text-danger">
                                    Rs.
                                    <?= number_format(
                                        $row['deduction_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format(
                                        (float)$row['gross_salary'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    Rs.
                                    <?= number_format(
                                        (float)$row['net_salary'],
                                        2
                                    ) ?>
                                </td>

                                <td class="text-success">

                                    Rs.
                                    <?= number_format(
                                        (float)$row['total_paid'],
                                        2
                                    ) ?>

                                </td>

                                <td class="text-danger">

                                    Rs.
                                    <?= number_format(
                                        $row['remaining_balance'],
                                        2
                                    ) ?>

                                </td>

                                <td>

                                    <span
                                        class="badge <?= $badgeClass ?>"
                                    >
                                        <?= e($status) ?>
                                    </span>

                                </td>

                                <td>

                                    <span
                                        class="badge <?= $approvalClass ?>"
                                    >
                                        <?= e($approval) ?>
                                    </span>

                                </td>

                                <td class="action-cell">

                                    <a
                                        href="../payroll/view.php?id=<?= (int)$row['id'] ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>