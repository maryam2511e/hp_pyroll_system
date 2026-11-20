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
        a.id,
        a.employee_id,
        a.amount,
        a.request_date,
        a.status,
        e.employee_code,
        e.first_name,
        e.last_name
    FROM advance_management a
    INNER JOIN employees e ON e.id = a.employee_id
    ORDER BY a.id DESC
");

$advances = $stmt->fetchAll();

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

    <title>Advances - HR Payroll System</title>

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

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .amount {
            font-weight: bold;
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

    <a href="index.php" class="active">Advances</a>

    <a href="../deductions/index.php">Deductions</a>

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

                <h2 class="mb-1">
                    Salary Advances
                </h2>

                <p class="text-muted mb-0">
                    Manage employee salary advance requests and payments.
                </p>

            </div>

            <a href="add.php" class="btn btn-primary">
                Add Advance
            </a>

        </div>

    </div>

    <?php if ($success === '1'): ?>

        <div class="alert alert-success">
            Advance record saved successfully.
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
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
                        <th>Request Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (count($advances) > 0): ?>

                    <?php foreach ($advances as $advance): ?>

                        <tr>

                            <td>
                                <?= (int)$advance['id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($advance['employee_code']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $advance['first_name'] . ' ' . $advance['last_name']
                                ) ?>
                            </td>

                            <td class="amount">
                                <?= formatAmount($advance['amount']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($advance['request_date']) ?>
                            </td>

                            <td>

                                <?php

                                $status = $advance['status'];

                                if ($status === 'Paid') {
                                    $badgeClass = 'bg-success';
                                } elseif ($status === 'Approved') {
                                    $badgeClass = 'bg-primary';
                                } elseif ($status === 'Pending') {
                                    $badgeClass = 'bg-warning text-dark';
                                } else {
                                    $badgeClass = 'bg-secondary';
                                }

                                ?>

                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>

                            </td>

                            <td>

                                <a
                                    href="view.php?id=<?= (int)$advance['id'] ?>"
                                    class="btn btn-sm btn-info text-white"
                                >
                                    View
                                </a>

                                <a
                                    href="edit.php?id=<?= (int)$advance['id'] ?>"
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
                            No advance records found.
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