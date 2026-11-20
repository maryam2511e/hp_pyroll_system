
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// Pagination
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Search
$search = trim($_GET['search'] ?? '');

try {
    $countSql = "
        SELECT COUNT(*)
        FROM audit_logs
        WHERE action LIKE ?
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(['%' . $search . '%']);
    $totalRecords = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($totalRecords / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $stmt = $pdo->prepare("
        SELECT id, action, timestamp
        FROM audit_logs
        WHERE action LIKE ?
        ORDER BY timestamp DESC, id DESC
        LIMIT $perPage OFFSET $offset
    ");

    $stmt->execute(['%' . $search . '%']);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Audit logs could not be loaded.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Logs | HR Payroll</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Audit Logs</h2>
            <p class="text-muted mb-0">
                Super Admin activity history
            </p>
        </div>

        <a href="../../dashboard.php" class="btn btn-secondary">
            Back to Dashboard
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            <form method="GET" class="row g-2 mb-4">
                <div class="col-md-6">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search activity..."
                        value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        Search
                    </button>

                    <a href="index.php" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <div class="mb-3">
                <strong>Total Records:</strong>
                <?= number_format($totalRecords) ?>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Activity</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">
                                No audit records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?= (int)$log['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($log['timestamp'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">

                        <?php
                        $query = http_build_query([
                            'search' => $search,
                            'page' => max(1, $page - 1)
                        ]);
                        ?>

                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $query ?>">
                                Previous
                            </a>
                        </li>

                        <li class="page-item disabled">
                            <span class="page-link">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                        </li>

                        <?php
                        $query = http_build_query([
                            'search' => $search,
                            'page' => min($totalPages, $page + 1)
                        ]);
                        ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $query ?>">
                                Next
                            </a>
                        </li>

                    </ul>
                </nav>
            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>
