
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

$stmt = $pdo->query("
    SELECT
        d.id,
        d.employee_id,
        d.amount,
        d.reason,
        d.date,
        e.employee_code,
        e.first_name,
        e.last_name
    FROM deduction_management AS d
    LEFT JOIN employees AS e
        ON e.id = d.employee_id
    ORDER BY d.id DESC
");

$deductions = $stmt->fetchAll();

function formatAmount($amount)
{
    return number_format((float)$amount, 2);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Deductions - HR Payroll System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            background: #172033;
            color: white;
            padding: 25px 15px;
            top: 0;
            left: 0;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .sidebar h3 {
            font-size: 20px;
            margin-bottom: 25px;
            text-align: center;
        }

        .sidebar a {
            display: block;
            color: #dce3f0;
            text-decoration: none;
            padding: 11px 12px;
            border-radius: 6px;
            margin-bottom: 6px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #263653;
            color: white;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .page-header,
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .amount {
            font-weight: bold;
        }

        .reason {
            max-width: 350px;
            white-space: normal;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .table-card {
                padding: 10px;
            }
        }
    </style>
</head>

<body>

<div class="sidebar">

    <h3>HR Payroll</h3>

    <a href="../../dashboard.php">Dashboard</a>
    <a href="../employees/index.php">Employees</a>
    <a href="../attendance/index.php">Attendance</a>
    <a href="../salary/index.php">Salary Management</a>
    <a href="../advances/index.php">Advances</a>
    <a href="index.php" class="active">Deductions</a>
    <a href="../overtime/index.php">Overtime</a>
    <a href="../bonuses/index.php">Bonuses</a>
    <a href="../leaves/index.php">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>

</div>

<div class="main">

    <div class="page-header">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>
                <h2 class="mb-1">Deductions</h2>
                <p class="text-muted mb-0">
                    Manage employee salary deductions.
                </p>
            </div>

            <a href="add.php" class="btn btn-primary">
                Add Deduction
            </a>

        </div>

    </div>

    <?php if ($success === '1'): ?>
        <div class="alert alert-success">
            Deduction record saved successfully.
        </div>
    <?php endif; ?>

    <?php if ($error === 'not_found'): ?>
        <div class="alert alert-danger">
            Deduction record was not found. Please check the record ID.
        </div>
    <?php elseif ($error === 'invalid_id'): ?>
        <div class="alert alert-danger">
            Invalid deduction ID.
        </div>
    <?php endif; ?>

    <div class="table-card">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($deductions)): ?>

                    <?php foreach ($deductions as $deduction): ?>

                        <?php $deductionId = (int)$deduction['id']; ?>

                        <tr>

                            <td>
                                <?= $deductionId ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($deduction['employee_code'] ?? 'Employee unavailable') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    trim(
                                        ($deduction['first_name'] ?? '') . ' ' .
                                        ($deduction['last_name'] ?? '')
                                    ) ?: 'Employee unavailable'
                                ) ?>
                            </td>

                            <td class="amount">
                                <?= formatAmount($deduction['amount']) ?>
                            </td>

                            <td class="reason">
                                <?= htmlspecialchars($deduction['reason'] ?? '') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($deduction['date'] ?? '') ?>
                            </td>

                            <td>

                                <a
                                    href="view.php?id=<?= $deductionId ?>"
                                    class="btn btn-sm btn-info text-white"
                                >
                                    View
                                </a>

                                <a
                                    href="edit.php?id=<?= $deductionId ?>"
                                    class="btn btn-sm btn-warning"
                                >
                                    Edit
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            No deduction records found.
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
