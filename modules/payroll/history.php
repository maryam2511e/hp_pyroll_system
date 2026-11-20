
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$payrollId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payrollId || $payrollId <= 0) {
    die("Invalid payroll ID.");
}

$stmt = $pdo->prepare("
    SELECT p.id, p.month_year, p.net_salary,
           e.employee_code, e.first_name, e.last_name
    FROM payroll_management p
    LEFT JOIN employees e ON e.id = p.employee_id
    WHERE p.id = ?
");
$stmt->execute([$payrollId]);
$payroll = $stmt->fetch();

if (!$payroll) {
    die("Payroll record not found.");
}

$stmt = $pdo->prepare("
    SELECT id, amount, payment_date, payment_method,
           reference_number, notes, created_at
    FROM payroll_payments
    WHERE payroll_id = ?
    ORDER BY payment_date DESC, id DESC
");
$stmt->execute([$payrollId]);
$payments = $stmt->fetchAll();

$totalPaid = 0;

foreach ($payments as $payment) {
    $totalPaid += (float)$payment['amount'];
}

$remaining = max(0, (float)$payroll['net_salary'] - $totalPaid);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Payment History</h3>
        <a href="view.php?id=<?= (int)$payrollId ?>" class="btn btn-secondary">
            Back to Payroll
        </a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5>Employee Information</h5>

            <p class="mb-1">
                <strong>Employee Code:</strong>
                <?= e($payroll['employee_code'] ?? 'N/A') ?>
            </p>

            <p class="mb-1">
                <strong>Employee Name:</strong>
                <?= e(trim(($payroll['first_name'] ?? '') . ' ' . ($payroll['last_name'] ?? '')) ?: 'N/A') ?>
            </p>

            <p class="mb-1">
                <strong>Payroll Month:</strong>
                <?= e($payroll['month_year']) ?>
            </p>

            <p class="mb-1">
                <strong>Net Salary:</strong>
                Rs. <?= number_format((float)$payroll['net_salary'], 2) ?>
            </p>

            <p class="mb-1">
                <strong>Total Paid:</strong>
                Rs. <?= number_format($totalPaid, 2) ?>
            </p>

            <p class="mb-0">
                <strong>Remaining Balance:</strong>
                Rs. <?= number_format($remaining, 2) ?>
            </p>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Payment Transactions</h5>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($payments): ?>
                        <?php foreach ($payments as $index => $payment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>Rs. <?= number_format((float)$payment['amount'], 2) ?></td>
                                <td><?= e($payment['payment_date']) ?></td>
                                <td><?= e($payment['payment_method']) ?></td>
                                <td><?= e($payment['reference_number'] ?: '-') ?></td>
                                <td><?= e($payment['notes'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                No payment transactions found.
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
