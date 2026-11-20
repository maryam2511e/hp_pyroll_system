
<?php

function logActivity(PDO $pdo, string $action): void
{
    $adminId = $_SESSION['admin_id'] ?? null;

    // Get visitor IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (admin_id, ip_address, action)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $adminId,
        $ipAddress,
        $action
    ]);
}
