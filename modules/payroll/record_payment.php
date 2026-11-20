
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';

$error = "";
$selectedPayrollId = (int)($_GET['id'] ?? 0);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selectedPayrollId = (int)($_POST['payroll_id'] ?? 0);
    $amount = trim($_POST['amount'] ?? '');
    $paymentDate = trim($_POST['payment_date'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash');
    $referenceNumber = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['csrf_token'], $csrfToken)
    ) {
        $error = "Invalid security token. Please refresh the page.";
    } elseif ($selectedPayrollId <= 0) {
        $error = "Please select a valid payroll record.";
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $error = "Payment amount must be greater than zero.";
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate) ||
        !strtotime($paymentDate) ||
        date('Y-m-d', strtotime($paymentDate)) !== $paymentDate ||
        $paymentDate > date('Y-m-d')
    ) {
        $error = "Please enter a valid payment date.";
    } elseif (!in_array($paymentMethod, ['Cash', 'Bank Transfer', 'Cheque', 'Other'], true)) {
        $error = "Invalid payment method.";
    } elseif (strlen($referenceNumber) > 100 || strlen($notes) > 1000) {
        $error = "Reference number or notes are too long.";
    } else {

        try {
            $pdo->beginTransaction();

            // Lock payroll and check approval status
            $stmt = $pdo->prepare("
                SELECT id, net_salary, approval_status
                FROM payroll_management
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$selectedPayrollId]);
            $payroll = $stmt->fetch();

            if (!$payroll) {
                throw new RuntimeException("Payroll record not found.");
            }

            if ($payroll['approval_status'] !== 'Approved') {
                throw new RuntimeException(
                    "Only approved payroll can receive payments."
                );
            }

            // Calculate already paid amount
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) AS total_paid
                FROM payroll_payments
                WHERE payroll_id = ?
            ");

            $stmt->execute([$selectedPayrollId]);
            $totalPaid = (float)$stmt->fetch()['total_paid'];

            $netSalary = (float)$payroll['net_salary'];
            $balance = round($netSalary - $totalPaid, 2);
            $paymentAmount = round((float)$amount, 2);

            if ($balance <= 0) {
                throw new RuntimeException(
                    "This payroll has already been fully paid."
                );
            }

            if ($paymentAmount > $balance) {
                throw new RuntimeException(
                    "Payment cannot exceed remaining balance: " .
                    number_format($balance, 2)
                );
            }

            // Insert payment
            $stmt = $pdo->prepare("
                INSERT INTO payroll_payments
                (
                    payroll_id,
                    amount,
                    payment_date,
                    payment_method,
                    reference_number,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $selectedPayrollId,
                $paymentAmount,
                $paymentDate,
                $paymentMethod,
                $referenceNumber !== '' ? $referenceNumber : null,
                $notes !== '' ? $notes : null
            ]);

            // Update payment status
            $newTotalPaid = round($totalPaid + $paymentAmount, 2);

            $newStatus = ($newTotalPaid >= $netSalary)
                ? 'Paid'
                : 'Partial';

            $stmt = $pdo->prepare("
                UPDATE payroll_management
                SET payment_status = ?
                WHERE id = ?
            ");

            $stmt->execute([$newStatus, $selectedPayrollId]);

            // Audit log
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (action, timestamp)
                VALUES (?, NOW())
            ");

            $stmt->execute([
                "Salary payment recorded. Payroll ID: " .
                $selectedPayrollId .
                ", Amount: " . number_format($paymentAmount, 2) .
                ", Admin ID: " . (int)$_SESSION['admin_id']
            ]);

            $pdo->commit();

            header(
                "Location: view.php?id=" .
                $selectedPayrollId .
                "&payment_success=1"
            );
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Payment error: " . $e->getMessage());

            $error = $e instanceof RuntimeException
                ? $e->getMessage()
                : "A database error occurred. Please try again.";
        }
    }
}

// Fetch only approved payroll records with remaining balance
$stmt = $pdo->query("
    SELECT
        p.id,
        p.month_year,
        p.net_salary,
        p.payment_status,
        p.approval_status,
        e.employee_code,
        e.first_name,
        e.last_name,
        COALESCE(pay.total_paid, 0) AS total_paid
    FROM payroll_management p
    LEFT JOIN employees e ON e.id = p.employee_id
    LEFT JOIN (
        SELECT payroll_id, SUM(amount) AS total_paid
        FROM payroll_payments
        GROUP BY payroll_id
    ) pay ON pay.payroll_id = p.id
    WHERE p.approval_status = 'Approved'
    ORDER BY p.id DESC
");

$allApprovedRecords = $stmt->fetchAll();

$payrollRecords = [];
$selectedRecord = null;

foreach ($allApprovedRecords as $record) {

    $balance = round(
        (float)$record['net_salary'] -
        (float)$record['total_paid'],
        2
    );

    if ($balance > 0) {
        $payrollRecords[] = $record;

        if ((int)$record['id'] === $selectedPayrollId) {
            $selectedRecord = $record;
        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Salary Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 750px;">

        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Record Salary Payment</h4>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <div class="mb-3">
                    <label class="form-label">Select Approved Payroll</label>

                    <select name="payroll_id" class="form-select" required>
                        <option value="">Select Employee Payroll</option>

                        <?php foreach ($payrollRecords as $record): ?>

                            <?php
                            $balance = round(
                                (float)$record['net_salary'] -
                                (float)$record['total_paid'],
                                2
                            );
                            ?>

                            <option
                                value="<?= (int)$record['id'] ?>"
                                <?= $selectedPayrollId === (int)$record['id'] ? 'selected' : '' ?>
                            >
                                <?= e($record['employee_code'] ?? 'N/A') ?>
                                -
                                <?= e(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')) ?>
                                |
                                <?= e($record['month_year']) ?>
                                |
                                Balance: <?= number_format($balance, 2) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($payrollRecords)): ?>
                        <small class="text-muted">
                            No approved payroll with an outstanding balance is available.
                        </small>
                    <?php endif; ?>
                </div>

                <?php if ($selectedRecord): ?>

                    <?php
                    $remainingBalance = round(
                        (float)$selectedRecord['net_salary'] -
                        (float)$selectedRecord['total_paid'],
                        2
                    );
                    ?>

                    <div class="alert alert-info">
                        <strong>Net Salary:</strong>
                        <?= number_format((float)$selectedRecord['net_salary'], 2) ?><br>

                        <strong>Already Paid:</strong>
                        <?= number_format((float)$selectedRecord['total_paid'], 2) ?><br>

                        <strong>Remaining Balance:</strong>
                        <?= number_format($remainingBalance, 2) ?>
                    </div>

                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Payment Amount</label>
                    <input
                        type="number"
                        name="amount"
                        class="form-control"
                        min="0.01"
                        step="0.01"
                        required
                        value="<?= e($_POST['amount'] ?? '') ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input
                        type="date"
                        name="payment_date"
                        class="form-control"
                        max="<?= date('Y-m-d') ?>"
                        required
                        value="<?= e($_POST['payment_date'] ?? date('Y-m-d')) ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Payment Method</label>

                    <select name="payment_method" class="form-select" required>
                        <?php
                        $methods = ['Cash', 'Bank Transfer', 'Cheque', 'Other'];
                        $currentMethod = $_POST['payment_method'] ?? 'Cash';
                        ?>

                        <?php foreach ($methods as $method): ?>
                            <option
                                value="<?= e($method) ?>"
                                <?= $currentMethod === $method ? 'selected' : '' ?>
                            >
                                <?= e($method) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reference Number (Optional)</label>
                    <input
                        type="text"
                        name="reference_number"
                        class="form-control"
                        maxlength="100"
                        value="<?= e($_POST['reference_number'] ?? '') ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea
                        name="notes"
                        class="form-control"
                        rows="3"
                        maxlength="1000"
                    ><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    <?= empty($payrollRecords) ? 'disabled' : '' ?>
                >
                    Save Payment
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Back to Payroll
                </a>

            </form>

        </div>
    </div>
</div>

</body>
</html>
