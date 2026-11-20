<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

function formatSalary($amount)
{
    return number_format((float)$amount, 2);
}

$currentMonth = date('Y-m');

/*
|--------------------------------------------------------------------------
| Payroll Settings
|--------------------------------------------------------------------------
*/

$daysPerMonth = 30;
$workingHoursPerDay = 8;

$settingsStmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM system_settings
    WHERE setting_key IN ('payroll_days_per_month', 'working_hours_per_day')
");

$settings = $settingsStmt->fetchAll();

foreach ($settings as $setting) {

    if ($setting['setting_key'] === 'payroll_days_per_month') {
        $value = (float)$setting['setting_value'];

        if ($value > 0) {
            $daysPerMonth = $value;
        }
    }

    if ($setting['setting_key'] === 'working_hours_per_day') {
        $value = (float)$setting['setting_value'];

        if ($value > 0) {
            $workingHoursPerDay = $value;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Employees + Current Month Salary Calculation
|--------------------------------------------------------------------------
|
| Net Salary =
| Base Salary
| + Overtime
| + Sunday Allowance
| + Bonus
| - Deductions
|
*/

$sql = "
    SELECT
        e.id,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.email,
        e.phone,
        e.designation,
        e.joining_date,
        e.employment_status,
        e.base_salary,

        COALESCE(ot.overtime_amount, 0) AS overtime_amount,
        COALESCE(sa.sunday_amount, 0) AS sunday_amount,
        COALESCE(bn.bonus_amount, 0) AS bonus_amount,
        COALESCE(dn.deduction_amount, 0) AS deduction_amount

    FROM employees e

    LEFT JOIN (
        SELECT
            employee_id,
            SUM(
                hours *
                multiplier *
                (base_salary / :days_per_month_ot / :working_hours_ot)
            ) AS overtime_amount
        FROM overtime_management om
        INNER JOIN employees oe
            ON oe.id = om.employee_id
        WHERE DATE_FORMAT(om.date, '%Y-%m') = :month_ot
        GROUP BY employee_id
    ) ot
        ON ot.employee_id = e.id

    LEFT JOIN (
        SELECT
            employee_id,
            SUM(amount) AS sunday_amount
        FROM sunday_allowance_mgmt
        WHERE DATE_FORMAT(date, '%Y-%m') = :month_sunday
        GROUP BY employee_id
    ) sa
        ON sa.employee_id = e.id

    LEFT JOIN (
        SELECT
            employee_id,
            SUM(amount) AS bonus_amount
        FROM bonus_management
        WHERE DATE_FORMAT(date, '%Y-%m') = :month_bonus
        GROUP BY employee_id
    ) bn
        ON bn.employee_id = e.id

    LEFT JOIN (
        SELECT
            employee_id,
            SUM(amount) AS deduction_amount
        FROM deduction_management
        WHERE DATE_FORMAT(date, '%Y-%m') = :month_deduction
        GROUP BY employee_id
    ) dn
        ON dn.employee_id = e.id

    ORDER BY e.id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':days_per_month_ot' => $daysPerMonth,
    ':working_hours_ot' => $workingHoursPerDay,
    ':month_ot' => $currentMonth,
    ':month_sunday' => $currentMonth,
    ':month_bonus' => $currentMonth,
    ':month_deduction' => $currentMonth
]);

$employees = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Salary Management</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            margin: 0;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
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
            color: white;
            margin-bottom: 25px;
        }

        .sidebar a {
            display: block;
            color: #adb5bd;
            text-decoration: none;
            padding: 11px 12px;
            margin-bottom: 5px;
            border-radius: 6px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #0d6efd;
            color: white;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 22px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .table-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .salary {
            font-weight: 600;
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

    <h4>HR & Payroll</h4>

    <a href="../../dashboard.php">
        Dashboard
    </a>

    <a href="../employees/index.php">
        Employees
    </a>

    <a href="../attendance/index.php">
        Attendance
    </a>

    <a href="index.php" class="active">
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

    <a href="../reports/index.php">
        Reports
    </a>

    <a href="../settings/index.php">
        Settings
    </a>

    <a href="../../logout.php">
        Logout
    </a>

</div>

<div class="main">

    <div class="page-header">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>

                <h2 class="mb-1">
                    Salary Management
                </h2>

                <p class="text-muted mb-0">
                    Current month salary calculation:
                    <?= htmlspecialchars($currentMonth) ?>
                </p>

            </div>

            <a
                href="../employees/add.php"
                class="btn btn-primary"
            >
                Add Employee
            </a>

        </div>

    </div>

    <?php if ($success === '1'): ?>

        <div class="alert alert-success">
            Salary record updated successfully.
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="table-card">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">

                    <tr>

                        <th>#</th>

                        <th>Employee Code</th>

                        <th>Employee Name</th>

                        <th>Designation</th>

                        <th>Joining Date</th>

                        <th>Status</th>

                        <th>Base Salary</th>

                        <th>Overtime</th>

                        <th>Sunday Allowance</th>

                        <th>Bonus</th>

                        <th>Deductions</th>

                        <th>Net Salary</th>

                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (count($employees) > 0): ?>

                    <?php foreach ($employees as $employee): ?>

                        <?php

                        $baseSalary = (float)$employee['base_salary'];

                        $overtimeAmount = (float)$employee['overtime_amount'];

                        $sundayAmount = (float)$employee['sunday_amount'];

                        $bonusAmount = (float)$employee['bonus_amount'];

                        $deductionAmount = (float)$employee['deduction_amount'];

                        $grossSalary =
                            $baseSalary
                            + $overtimeAmount
                            + $sundayAmount
                            + $bonusAmount;

                        $netSalary =
                            max(0, $grossSalary - $deductionAmount);

                        ?>

                        <tr>

                            <td>
                                <?= (int)$employee['id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($employee['employee_code']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $employee['first_name'] . ' ' . $employee['last_name']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($employee['designation']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($employee['joining_date']) ?>
                            </td>

                            <td>

                                <?php

                                $status = $employee['employment_status'];

                                if ($status === 'Active') {
                                    $badgeClass = 'bg-success';
                                } elseif ($status === 'Inactive') {
                                    $badgeClass = 'bg-secondary';
                                } else {
                                    $badgeClass = 'bg-danger';
                                }

                                ?>

                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>

                            </td>

                            <td class="salary">
                                <?= formatSalary($baseSalary) ?>
                            </td>

                            <td>
                                <?= formatSalary($overtimeAmount) ?>
                            </td>

                            <td>
                                <?= formatSalary($sundayAmount) ?>
                            </td>

                            <td>
                                <?= formatSalary($bonusAmount) ?>
                            </td>

                            <td class="text-danger">
                                <?= formatSalary($deductionAmount) ?>
                            </td>

                            <td class="salary text-success">
                                <?= formatSalary($netSalary) ?>
                            </td>

                            <td>

                                <a
                                    href="../employees/view.php?id=<?= (int)$employee['id'] ?>"
                                    class="btn btn-sm btn-info text-white"
                                >
                                    View
                                </a>

                                <a
                                    href="../employees/edit.php?id=<?= (int)$employee['id'] ?>"
                                    class="btn btn-sm btn-warning"
                                >
                                    Edit Salary
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="13"
                            class="text-center text-muted"
                        >
                            No employees found.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>