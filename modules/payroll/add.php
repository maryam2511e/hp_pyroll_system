<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$errors = [];

$employeeId = '';
$monthYear = date('Y-m');
$calculation = null;

/*
|--------------------------------------------------------------------------
| Payroll Setting Helper
|--------------------------------------------------------------------------
*/

function getPayrollSetting($pdo, $key, $default)
{
    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$key]);

    $value = $stmt->fetchColumn();

    if (
        $value === false ||
        !is_numeric($value) ||
        (float)$value <= 0
    ) {
        return (float)$default;
    }

    return (float)$value;
}

/*
|--------------------------------------------------------------------------
| Load Configurable Payroll Settings
|--------------------------------------------------------------------------
*/

$daysPerMonth = getPayrollSetting(
    $pdo,
    'payroll_days_per_month',
    30
);

$workingHoursPerDay = getPayrollSetting(
    $pdo,
    'working_hours_per_day',
    8
);

$defaultOvertimeMultiplier = getPayrollSetting(
    $pdo,
    'default_overtime_multiplier',
    1.5
);

/*
|--------------------------------------------------------------------------
| Active Employees
|--------------------------------------------------------------------------
*/

$employeeStmt = $pdo->query("
    SELECT
        id,
        employee_code,
        first_name,
        last_name,
        base_salary
    FROM employees
    WHERE employment_status = 'Active'
    ORDER BY first_name ASC
");

$employees = $employeeStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Generate Payroll
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId = filter_input(
        INPUT_POST,
        'employee_id',
        FILTER_VALIDATE_INT
    );

    $monthYear = trim($_POST['month_year'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validate Employee
    |--------------------------------------------------------------------------
    */

    if (!$employeeId || $employeeId <= 0) {
        $errors[] = "Please select a valid employee.";
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Month
    |--------------------------------------------------------------------------
    */

    $monthObject = DateTime::createFromFormat(
        '!Y-m',
        $monthYear
    );

    if (
        !$monthObject ||
        $monthObject->format('Y-m') !== $monthYear
    ) {
        $errors[] = "Please select a valid payroll month.";
    } elseif ($monthYear > date('Y-m')) {
        $errors[] = "Future payroll months are not allowed.";
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Payroll
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Verify Employee
            |--------------------------------------------------------------------------
            */

            $checkEmployee = $pdo->prepare("
                SELECT
                    id,
                    employee_code,
                    first_name,
                    last_name,
                    base_salary
                FROM employees
                WHERE id = ?
                AND employment_status = 'Active'
                LIMIT 1
            ");

            $checkEmployee->execute([
                $employeeId
            ]);

            $employee = $checkEmployee->fetch();

            if (!$employee) {
                throw new Exception(
                    "Selected employee is not active or does not exist."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Payroll
            |--------------------------------------------------------------------------
            */

            $duplicateStmt = $pdo->prepare("
                SELECT id
                FROM payroll_management
                WHERE employee_id = ?
                AND month_year = ?
                LIMIT 1
            ");

            $duplicateStmt->execute([
                $employeeId,
                $monthYear
            ]);

            if ($duplicateStmt->fetch()) {
                throw new Exception(
                    "Payroll for this employee and month already exists."
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Month Date Range
            |--------------------------------------------------------------------------
            */

            $monthStart = $monthObject->format('Y-m-01');
            $monthEnd = $monthObject->format('Y-m-t');

            /*
            |--------------------------------------------------------------------------
            | Basic Salary
            |--------------------------------------------------------------------------
            */

            $basicSalary = (float)$employee['base_salary'];

            /*
            |--------------------------------------------------------------------------
            | Overtime Calculation
            |--------------------------------------------------------------------------
            |
            | Each overtime record already has its own multiplier.
            | If a record has a valid multiplier, that multiplier is used.
            | Otherwise the system default multiplier from Settings is used.
            |
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

            $weightedOvertimeHours = (float)$overtimeStmt->fetchColumn();

            /*
            |--------------------------------------------------------------------------
            | Hourly Rate
            |--------------------------------------------------------------------------
            */

            $hourlyRate =
                $basicSalary /
                $daysPerMonth /
                $workingHoursPerDay;

            $overtimeAmount =
                $hourlyRate *
                $weightedOvertimeHours;

            /*
            |--------------------------------------------------------------------------
            | Sunday Allowance
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
            | Bonuses
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
            | Gross Salary
            |--------------------------------------------------------------------------
            */

            $grossSalary =
                $basicSalary +
                $overtimeAmount +
                $sundayAllowance +
                $bonusAmount;

            /*
            |--------------------------------------------------------------------------
            | Net Salary
            |--------------------------------------------------------------------------
            */

            $netSalary = max(
                0,
                $grossSalary - $deductionAmount
            );

            /*
            |--------------------------------------------------------------------------
            | Calculation Preview
            |--------------------------------------------------------------------------
            */

            $calculation = [
                'basic_salary' => $basicSalary,
                'overtime_amount' => $overtimeAmount,
                'sunday_allowance' => $sundayAllowance,
                'bonus_amount' => $bonusAmount,
                'deduction_amount' => $deductionAmount,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary
            ];

            /*
            |--------------------------------------------------------------------------
            | Insert Payroll
            |--------------------------------------------------------------------------
            */

            $insertStmt = $pdo->prepare("
                INSERT INTO payroll_management
                (
                    employee_id,
                    month_year,
                    gross_salary,
                    net_salary,
                    payment_status
                )
                VALUES (?, ?, ?, ?, 'Pending')
            ");

            $insertStmt->execute([
                $employeeId,
                $monthYear,
                $grossSalary,
                $netSalary
            ]);

            $payrollId = (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            $logStmt = $pdo->prepare("
                INSERT INTO audit_logs
                (action, timestamp)
                VALUES (?, NOW())
            ");

            $logStmt->execute([
                "Payroll record ID {$payrollId} generated for employee ID {$employeeId}, month {$monthYear} by Super Admin."
            ]);

            $pdo->commit();

            header(
                "Location: view.php?id=" .
                $payrollId .
                "&success=1"
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            if ($e instanceof Exception) {
                $errors[] = $e->getMessage();
            } else {
                $errors[] = "Unable to generate payroll. Please try again.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Generate Payroll | HR & Payroll</title>

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

    <a href="../overtime/index.php">Overtime</a>

    <a href="../bonuses/index.php">Bonuses</a>

    <a href="../leaves/index.php">Annual Leaves</a>

    <a href="index.php" class="active">Payroll</a>

    <a href="../reports/index.php">Reports</a>

    <a href="../settings/index.php">Settings</a>

    <a href="../../logout.php">Logout</a>

</div>

<div class="main-content">

    <h2 class="mb-4">
        Generate Payroll
    </h2>

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

                <label class="form-label">
                    Select Employee *
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
                            <?= (string)$employeeId === (string)$employee['id']
                                ? 'selected'
                                : '' ?>
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

            <div class="mb-4">

                <label class="form-label">
                    Payroll Month *
                </label>

                <input
                    type="month"
                    name="month_year"
                    class="form-control"
                    value="<?= htmlspecialchars($monthYear) ?>"
                    max="<?= date('Y-m') ?>"
                    required
                >

            </div>

            <div class="alert alert-info">

                <strong>Automatic calculation:</strong>

                <ul class="mb-0 mt-2">

                    <li>
                        Basic salary
                    </li>

                    <li>
                        Recorded overtime
                    </li>

                    <li>
                        Sunday allowances
                    </li>

                    <li>
                        Recorded bonuses
                    </li>

                    <li>
                        Recorded deductions
                    </li>

                </ul>

            </div>

            <div class="alert alert-secondary">

                <strong>Current payroll settings:</strong>

                <ul class="mb-0 mt-2">

                    <li>
                        Payroll days per month:
                        <?= htmlspecialchars(number_format($daysPerMonth, 2)) ?>
                    </li>

                    <li>
                        Working hours per day:
                        <?= htmlspecialchars(number_format($workingHoursPerDay, 2)) ?>
                    </li>

                    <li>
                        Default overtime multiplier:
                        <?= htmlspecialchars(number_format($defaultOvertimeMultiplier, 2)) ?>
                    </li>

                </ul>

            </div>

            <div class="alert alert-warning">

                Attendance-based deductions, unpaid leave
                and advance repayments are not included yet.

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Generate Payroll
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