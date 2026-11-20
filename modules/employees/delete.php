<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$employee_id = (int) $_GET['id'];

try {

    // Check employee exists
    $stmt = $pdo->prepare("
        SELECT id, employment_status
        FROM employees
        WHERE id = ?
    ");

    $stmt->execute([$employee_id]);

    $employee = $stmt->fetch();

    if (!$employee) {
        header("Location: index.php?error=not_found");
        exit;
    }

    // Do not permanently delete employee
    if ($employee['employment_status'] === 'Inactive') {
        header("Location: index.php?error=already_inactive");
        exit;
    }

    // Mark employee as inactive
    $stmt = $pdo->prepare("
        UPDATE employees
        SET employment_status = 'Inactive',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $stmt->execute([$employee_id]);

    // Audit log
    try {

        $audit = $pdo->prepare("
            INSERT INTO audit_logs (action, timestamp)
            VALUES (?, CURRENT_TIMESTAMP)
        ");

        $audit->execute([
            "Employee ID {$employee_id} marked as Inactive"
        ]);

    } catch (PDOException $e) {
        // Audit log failure should not stop deactivation
        error_log($e->getMessage());
    }

    header("Location: index.php?success=deactivated");
    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header("Location: index.php?error=database");
    exit;
}