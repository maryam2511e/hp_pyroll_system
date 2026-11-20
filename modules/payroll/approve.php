
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$payrollId = filter_input(INPUT_POST, 'payroll_id', FILTER_VALIDATE_INT);
$csrfToken = $_POST['csrf_token'] ?? '';

if (!$payrollId || $payrollId <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

// CSRF protection
if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    header("Location: view.php?id=" . $payrollId . "&error=invalid_token");
    exit;
}

try {

    $pdo->beginTransaction();

    // Lock payroll record
    $stmt = $pdo->prepare("
        SELECT id, approval_status
        FROM payroll_management
        WHERE id = ?
        FOR UPDATE
    ");

    $stmt->execute([$payrollId]);
    $payroll = $stmt->fetch();

    if (!$payroll) {
        $pdo->rollBack();
        header("Location: index.php?error=not_found");
        exit;
    }

    if ($payroll['approval_status'] === 'Approved') {
        $pdo->rollBack();
        header("Location: view.php?id=" . $payrollId . "&error=already_approved");
        exit;
    }

    // Approve payroll
    $stmt = $pdo->prepare("
        UPDATE payroll_management
        SET approval_status = 'Approved',
            approved_at = NOW(),
            approved_by = ?
        WHERE id = ?
          AND approval_status = 'Draft'
    ");

    $stmt->execute([
        (int)$_SESSION['admin_id'],
        $payrollId
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException("Payroll approval could not be completed.");
    }

    // Audit log
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (action, timestamp)
        VALUES (?, NOW())
    ");

    $stmt->execute([
        "Payroll approved. Payroll ID: " . $payrollId .
        ", Admin ID: " . (int)$_SESSION['admin_id']
    ]);

    $pdo->commit();

    header("Location: view.php?id=" . $payrollId . "&approved=1");
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Payroll approval error: " . $e->getMessage());

    header("Location: view.php?id=" . $payrollId . "&error=approval_failed");
    exit;
}
