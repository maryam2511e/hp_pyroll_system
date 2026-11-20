
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$stmt = $pdo->query("
    SELECT
        p.id,
        p.employee_id,
        p.month_year,
        p.gross_salary,
        p.net_salary,
        p.payment_status,
        e.employee_code,
        e.first_name,
        e.last_name
    FROM payroll_management p
    LEFT JOIN employees e ON p.employee_id = e.id
    ORDER BY p.id DESC
");

$payrollRecords = $stmt->fetchAll();

$totalPayroll = count($payrollRecords);
$totalNetSalary = 0;

foreach ($payrollRecords as $record) {
    $totalNetSalary += (float)$record['net_salary'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management | HR & Payroll</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

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

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            height: 100%;
        }

        .table-card {
            background: white;
            padding: 20px;
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

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h2 class="mb-0">Payroll Management</h2>

        <a href="add.php" class="btn btn-primary">
            Generate Payroll
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Payroll record saved successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
        <div class="alert alert-danger">
            Payroll record not found.
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="summary-card">
                <h6 class="text-muted">Total Payroll Records</h6>
                <h3><?= $totalPayroll ?></h3>
            </div>
        </div>

        <div class="col-md-6">
            <div class="summary-card">
                <h6 class="text-muted">Total Net Payroll</h6>
                <h3>
                    Rs. <?= number_format($totalNetSalary, 2) ?>
                </h3>
            </div>
        </div>

    </div>

    <div class="table-card">

        <h5 class="mb-3">Payroll Records</h5>

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Month</th>
                        <th>Gross Salary</th>
                        <th>Net Salary</th>
                        <th>Payment Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($payrollRecords)): ?>

                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No payroll records found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($payrollRecords as $record): ?>

                            <?php
                            $paymentStatus = $record['payment_status'] ?? 'Pending';

                            $statusClass = 'secondary';

                            if ($paymentStatus === 'Paid') {
                                $statusClass = 'success';
                            } elseif ($paymentStatus === 'Pending') {
                                $statusClass = 'warning';
                            } elseif ($paymentStatus === 'Partial') {
                                $statusClass = 'info';
                            }
                            ?>

                            <tr>
                                <td><?= (int)$record['id'] ?></td>

                                <td>
                                    <?= htmlspecialchars($record['employee_code'] ?? 'N/A') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''))
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($record['month_year']) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format((float)$record['gross_salary'], 2) ?>
                                </td>

                                <td>
                                    Rs. <?= number_format((float)$record['net_salary'], 2) ?>
                                </td>

                                <td>
                                    <span class="badge bg-<?= $statusClass ?>">
                                        <?= htmlspecialchars($paymentStatus) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="view.php?id=<?= (int)$record['id'] ?>"
                                        class="btn btn-sm btn-info"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
