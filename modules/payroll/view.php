<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';

$payrollId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payrollId || $payrollId <= 0) {
    header("Location: index.php?error=not_found");
    exit;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getPayrollSetting($pdo, $key, $default)
{
    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    $stmt->execute([$key]);

    $value = $stmt->fetchColumn();

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

// --------------------------------------------------
// Get payroll + employee
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        p.*,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.designation,
        e.employment_status,
        e.base_salary
    FROM payroll_management p
    LEFT JOIN employees e ON e.id = p.employee_id
    WHERE p.id = ?
");

$stmt->execute([$payrollId]);
$payroll = $stmt->fetch();

if (!$payroll) {
    header("Location: index.php?error=not_found");
    exit;
}

$employeeId = (int)$payroll['employee_id'];
$monthYear = $payroll['month_year'];

$monthStart = $monthYear . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

// --------------------------------------------------
// Load payroll settings
// --------------------------------------------------

$defaultOvertimeMultiplier = (float)getPayrollSetting(
    $pdo,
    'default_overtime_multiplier',
    1.5
);

if ($defaultOvertimeMultiplier <= 0) {
    $defaultOvertimeMultiplier = 1.5;
}

// --------------------------------------------------
// Basic salary
// --------------------------------------------------

$baseSalary = (float)($payroll['base_salary'] ?? 0);

// --------------------------------------------------
// Overtime
// --------------------------------------------------

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

// --------------------------------------------------
// Sunday allowance
// --------------------------------------------------

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

// --------------------------------------------------
// Bonus
// --------------------------------------------------

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

// --------------------------------------------------
// Deductions
// --------------------------------------------------

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

// --------------------------------------------------
// Calculated component totals
// --------------------------------------------------

$calculatedGross = round(
    $baseSalary +
    $overtimeAmount +
    $sundayAllowance +
    $bonusAmount,
    2
);

$calculatedNet = max(
    0,
    round($calculatedGross - $deductionAmount, 2)
);

// Saved payroll values are the official payroll values.
$grossSalary = (float)$payroll['gross_salary'];
$netSalary = (float)$payroll['net_salary'];

// --------------------------------------------------
// Payment totals
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payroll_payments
    WHERE payroll_id = ?
");

$stmt->execute([$payrollId]);

$totalPaid = (float)$stmt->fetchColumn();

$remainingBalance = max(
    0,
    round($netSalary - $totalPaid, 2)
);

// Dynamic payment status
if ($remainingBalance <= 0 && $netSalary > 0) {
    $paymentStatus = 'Paid';
} elseif ($totalPaid > 0) {
    $paymentStatus = 'Partial';
} else {
    $paymentStatus = 'Pending';
}

$approvalStatus = $payroll['approval_status'] ?? 'Draft';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$employeeName = trim(
    ($payroll['first_name'] ?? '') . ' ' .
    ($payroll['last_name'] ?? '')
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Payroll Details</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

</head>

<body class="bg-light">

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">

        <h3>Payroll Details</h3>

        <a href="index.php" class="btn btn-secondary">
            Back to Payroll
        </a>

    </div>

    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success">
            Payroll record created successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['payment_success'])): ?>

        <div class="alert alert-success">
            Salary payment recorded successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['approved'])): ?>

        <div class="alert alert-success">
            Payroll approved successfully.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>

        <div class="alert alert-danger">

            <?php

            $messages = [
                'invalid_token' =>
                    'Security token invalid. Please try again.',

                'already_approved' =>
                    'This payroll is already approved.',

                'approval_failed' =>
                    'Payroll approval failed. Please try again.'
            ];

            echo e(
                $messages[$_GET['error']] ??
                'An error occurred.'
            );

            ?>

        </div>

    <?php endif; ?>


    <!-- Payroll Information -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-primary text-white">

            <h5 class="mb-0">
                Payroll Information
            </h5>

        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">

                    <strong>Payroll ID:</strong>

                    <?= (int)$payroll['id'] ?>

                </div>


                <div class="col-md-6">

                    <strong>Employee Code:</strong>

                    <?= e($payroll['employee_code'] ?? 'N/A') ?>

                </div>


                <div class="col-md-6">

                    <strong>Employee Name:</strong>

                    <?= e($employeeName ?: 'N/A') ?>

                </div>


                <div class="col-md-6">

                    <strong>Designation:</strong>

                    <?= e($payroll['designation'] ?? 'N/A') ?>

                </div>


                <div class="col-md-6">

                    <strong>Employment Status:</strong>

                    <?= e(
                        $payroll['employment_status'] ??
                        'Unknown'
                    ) ?>

                </div>


                <div class="col-md-6">

                    <strong>Payroll Month:</strong>

                    <?= e($payroll['month_year']) ?>

                </div>

            </div>

        </div>

    </div>


    <!-- Salary Breakdown -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">
                Salary Breakdown
            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered align-middle mb-0">

                    <thead class="table-light">

                    <tr>

                        <th>
                            Description
                        </th>

                        <th class="text-end">
                            Amount
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <tr>

                        <td>
                            Basic Salary
                        </td>

                        <td class="text-end">
                            Rs.
                            <?= number_format(
                                $baseSalary,
                                2
                            ) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Overtime
                        </td>

                        <td class="text-end">
                            Rs.
                            <?= number_format(
                                $overtimeAmount,
                                2
                            ) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Sunday Allowance
                        </td>

                        <td class="text-end">
                            Rs.
                            <?= number_format(
                                $sundayAllowance,
                                2
                            ) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Bonus
                        </td>

                        <td class="text-end">
                            Rs.
                            <?= number_format(
                                $bonusAmount,
                                2
                            ) ?>
                        </td>

                    </tr>


                    <tr class="table-primary fw-bold">

                        <td>
                            Gross Salary
                        </td>

                        <td class="text-end">
                            Rs.
                            <?= number_format(
                                $grossSalary,
                                2
                            ) ?>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Deductions
                        </td>

                        <td class="text-end text-danger">
                            - Rs.
                            <?= number_format(
                                $deductionAmount,
                                2
                            ) ?>
                        </td>

                    </tr>


                    <tr class="table-success fw-bold">

                        <td>
                            Net Salary
                        </td>

                        <td class="text-end">
                            Rs.
                            <?= number_format(
                                $netSalary,
                                2
                            ) ?>
                        </td>

                    </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- Payment Summary -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-success text-white">

            <h5 class="mb-0">
                Payment Summary
            </h5>

        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-4">

                    <div class="border rounded p-3">

                        <small class="text-muted">
                            Net Salary
                        </small>

                        <h5 class="mb-0">
                            Rs.
                            <?= number_format(
                                $netSalary,
                                2
                            ) ?>
                        </h5>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="border rounded p-3">

                        <small class="text-muted">
                            Total Paid
                        </small>

                        <h5 class="mb-0 text-success">
                            Rs.
                            <?= number_format(
                                $totalPaid,
                                2
                            ) ?>
                        </h5>

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="border rounded p-3">

                        <small class="text-muted">
                            Remaining Balance
                        </small>

                        <h5 class="mb-0 text-danger">
                            Rs.
                            <?= number_format(
                                $remainingBalance,
                                2
                            ) ?>
                        </h5>

                    </div>

                </div>

            </div>

            <div class="mt-3">

                <strong>
                    Payment Status:
                </strong>

                <?php if ($paymentStatus === 'Paid'): ?>

                    <span class="badge bg-success">
                        Paid
                    </span>

                <?php elseif ($paymentStatus === 'Partial'): ?>

                    <span class="badge bg-warning text-dark">
                        Partial
                    </span>

                <?php else: ?>

                    <span class="badge bg-secondary">
                        Pending
                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- Approval -->

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-secondary text-white">

            <h5 class="mb-0">
                Approval
            </h5>

        </div>

        <div class="card-body">

            <div class="mb-3">

                <strong>
                    Approval Status:
                </strong>

                <?php if ($approvalStatus === 'Approved'): ?>

                    <span class="badge bg-success">
                        Approved
                    </span>

                <?php else: ?>

                    <span class="badge bg-warning text-dark">
                        Draft
                    </span>

                <?php endif; ?>

            </div>


            <?php if (!empty($payroll['approved_at'])): ?>

                <p class="mb-0">

                    <strong>
                        Approved At:
                    </strong>

                    <?= e($payroll['approved_at']) ?>

                </p>

            <?php endif; ?>

        </div>

    </div>


    <!-- Actions -->

    <div class="card shadow-sm">

        <div class="card-body">

            <div class="d-flex flex-wrap gap-2">


                <?php if ($approvalStatus === 'Draft'): ?>

                    <form method="POST"
                          action="approve.php"
                          onsubmit="return confirm('Are you sure you want to approve this payroll?');">

                        <input type="hidden"
                               name="payroll_id"
                               value="<?= (int)$payrollId ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= e($_SESSION['csrf_token']) ?>">

                        <button type="submit"
                                class="btn btn-success">

                            Approve Payroll

                        </button>

                    </form>

                <?php else: ?>

                    <button type="button"
                            class="btn btn-success"
                            disabled>

                        Payroll Approved

                    </button>

                <?php endif; ?>


                <a href="slip.php?id=<?= (int)$payrollId ?>"
                   class="btn btn-danger">

                    Download Salary Slip PDF

                </a>


                <?php if (
                    $approvalStatus === 'Approved' &&
                    $remainingBalance > 0
                ): ?>

                    <a href="record_payment.php?id=<?= (int)$payrollId ?>"
                       class="btn btn-primary">

                        Record Salary Payment

                    </a>

                <?php endif; ?>


                <a href="payment_history.php?id=<?= (int)$payrollId ?>"
                   class="btn btn-info text-white">

                    View Payment History

                </a>


                <a href="index.php"
                   class="btn btn-secondary">

                    Back to Payroll

                </a>

            </div>

        </div>

    </div>

</div>

</body>

</html>