
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$stmt = $pdo->query("
    SELECT
        b.id,
        b.employee_id,
        b.amount,
        b.reason,
        b.date,
        e.employee_code,
        e.first_name,
        e.last_name
    FROM bonus_management b
    LEFT JOIN employees e ON e.id = b.employee_id
    ORDER BY b.date DESC, b.id DESC
");

$bonuses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Management | HR & Payroll</title>

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

        .content-card {
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
    <a href="index.php" class="active">Bonuses</a>
    <a href="../leaves/index.php">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>
</div>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2>Bonus Management</h2>
            <p class="text-muted mb-0">Manage employee bonuses.</p>
        </div>

        <a href="add.php" class="btn btn-primary">
            + Add Bonus
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Bonus record saved successfully.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            Unable to complete the requested action.
        </div>
    <?php endif; ?>

    <div class="content-card">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Bonus Amount</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($bonuses) > 0): ?>

                    <?php foreach ($bonuses as $bonus): ?>

                        <?php $bonusId = (int)$bonus['id']; ?>

                        <tr>
                            <td><?= $bonusId ?></td>

                            <td>
                                <?= htmlspecialchars($bonus['employee_code'] ?? 'N/A') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    trim(($bonus['first_name'] ?? '') . ' ' . ($bonus['last_name'] ?? ''))
                                ) ?: 'Employee Not Found' ?>
                            </td>

                            <td>
                                <?= number_format((float)$bonus['amount'], 2) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($bonus['reason'] ?? '') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($bonus['date']) ?>
                            </td>

                            <td>
                                <a href="view.php?id=<?= $bonusId ?>"
                                   class="btn btn-sm btn-info text-white">
                                    View
                                </a>

                                <a href="edit.php?id=<?= $bonusId ?>"
                                   class="btn btn-sm btn-warning">
                                    Edit
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            No bonus records found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
