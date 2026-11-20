
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$stmt = $pdo->query("
    SELECT
        l.id,
        l.employee_id,
        l.leave_type,
        l.start_date,
        l.end_date,
        l.total_days,
        l.status,
        l.reason,
        e.employee_code,
        e.first_name,
        e.last_name
    FROM annual_leaves l
    LEFT JOIN employees e ON e.id = l.employee_id
    ORDER BY l.start_date DESC, l.id DESC
");

$leaves = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annual Leaves | HR & Payroll</title>

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
    <a href="../bonuses/index.php">Bonuses</a>
    <a href="index.php" class="active">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>
</div>

<div class="main-content">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2>Annual Leave Management</h2>
            <p class="text-muted mb-0">Manage employee leave records and approvals.</p>
        </div>

        <a href="add.php" class="btn btn-primary">
            + Add Leave
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            Leave record saved successfully.
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
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Days</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($leaves) > 0): ?>

                    <?php foreach ($leaves as $leave): ?>

                        <?php
                            $leaveId = (int)$leave['id'];
                            $status = strtolower($leave['status'] ?? '');
                        ?>

                        <tr>
                            <td><?= $leaveId ?></td>

                            <td>
                                <?= htmlspecialchars($leave['employee_code'] ?? 'N/A') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    trim(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? ''))
                                ) ?: 'Employee Not Found' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($leave['leave_type']) ?>
                            </td>

                            <td><?= htmlspecialchars($leave['start_date']) ?></td>

                            <td><?= htmlspecialchars($leave['end_date']) ?></td>

                            <td><?= number_format((float)$leave['total_days'], 2) ?></td>

                            <td>
                                <?php if ($status === 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($status === 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <?= htmlspecialchars($leave['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="view.php?id=<?= $leaveId ?>"
                                   class="btn btn-sm btn-info text-white">
                                    View
                                </a>

                                <a href="edit.php?id=<?= $leaveId ?>"
                                   class="btn btn-sm btn-warning">
                                    Edit
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            No leave records found.
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
