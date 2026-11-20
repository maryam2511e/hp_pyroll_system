
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

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Payroll details
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.month_year,
        p.gross_salary,
        p.net_salary,
        p.payment_status,
        e.employee_code,
        e.first_name,
        e.last_name
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

// Payment history
$stmt = $pdo->prepare("
    SELECT *
    FROM payroll_payments
    WHERE payroll_id = ?
    ORDER BY payment_date DESC, id DESC
");

$stmt->execute([$payrollId]);
$payments = $stmt->fetchAll();

// Calculate totals
$totalPaid = 0;

foreach ($payments as $payment) {
    $totalPaid += (float)$payment['amount'];
}

$netSalary = (float)$payroll['net_salary'];
$remainingBalance = round($netSalary - $totalPaid, 2);

$status = $remainingBalance <= 0
    ? 'Paid'
    : ($totalPaid > 0 ? 'Partial' : 'Pending');

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Salary Payment History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3>Salary Payment History</h3>

        <a href="view.php?id=<?= (int)$payrollId ?>"
           class="btn btn-secondary">
            Back to Payroll Details
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Employee Payroll Information</h5>
        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-md-6">
                    <strong>Employee Code:</strong>
                    <?= e($payroll['employee_code'] ?? 'N/A') ?>
                </div>

                <div class="col-md-6">
                    <strong>Employee Name:</strong>
                    <?= e(($payroll['first_name'] ?? '') . ' ' . ($payroll['last_name'] ?? '')) ?>
                </div>

                <div class="col-md-6">
                    <strong>Payroll Month:</strong>
                    <?= e($payroll['month_year']) ?>
                </div>

                <div class="col-md-6">
                    <strong>Gross Salary:</strong>
                    <?= number_format((float)$payroll['gross_salary'], 2) ?>
                </div>

                <div class="col-md-6">
                    <strong>Net Salary:</strong>
                    <?= number_format($netSalary, 2) ?>
                </div>

                <div class="col-md-6">
                    <strong>Payment Status:</strong>

                    <?php if ($status === 'Paid'): ?>
                        <span class="badge bg-success">Paid</span>
                    <?php elseif ($status === 'Partial'): ?>
                        <span class="badge bg-warning text-dark">Partial</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Pending</span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Net Salary</h6>
                    <h4><?= number_format($netSalary, 2) ?></h4>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Total Paid</h6>
                    <h4 class="text-success">
                        <?= number_format($totalPaid, 2) ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Remaining Balance</h6>
                    <h4 class="text-danger">
                        <?= number_format(max(0, $remainingBalance), 2) ?>
                    </h4>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Payment Records</h5>

            <?php if ($remainingBalance > 0): ?>
                <a href="record_payment.php?id=<?= (int)$payrollId ?>"
                   class="btn btn-primary btn-sm">
                    Record Another Payment
                </a>
            <?php endif; ?>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Reference Number</th>
                            <th>Notes</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (count($payments) > 0): ?>

                            <?php foreach ($payments as $index => $payment): ?>

                                <tr>
                                    <td><?= $index + 1 ?></td>

                                    <td>
                                        <?= number_format((float)$payment['amount'], 2) ?>
                                    </td>

                                    <td>
                                        <?= e($payment['payment_date']) ?>
                                    </td>

                                    <td>
                                        <?= e($payment['payment_method']) ?>
                                    </td>

                                    <td>
                                        <?= e($payment['reference_number'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= e($payment['notes'] ?? '-') ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    No payment records found for this payroll.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>
    </div>

</div>

</body>
</html>
