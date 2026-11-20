
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
        o.id,
        o.employee_id,
        o.hours,
        o.date,
        o.multiplier,
        e.employee_code,
        e.first_name,
        e.last_name
    FROM overtime_management o
    LEFT JOIN employees e ON e.id = o.employee_id
    ORDER BY o.date DESC, o.id DESC
");

$overtimes = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Overtime Management - HR Payroll System</title>

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
    <a href="../deductions/index.php">Deductions</a>
    <a href="index.php" class="active">Overtime</a>
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
                <h2 class="mb-1">Overtime Management</h2>

                <p class="text-muted mb-0">
                    Manage employee overtime records.
                </p>
            </div>

            <a href="add.php" class="btn btn-primary">
                Add Overtime
            </a>

        </div>

    </div>

    <?php if ($success === '1'): ?>

        <div class="alert alert-success">
            Overtime record saved successfully.
        </div>

    <?php endif; ?>

    <?php if ($error === 'not_found'): ?>

        <div class="alert alert-danger">
            Overtime record not found.
        </div>

    <?php elseif ($error === 'invalid_id'): ?>

        <div class="alert alert-danger">
            Invalid overtime record ID.
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
                        <th>Overtime Hours</th>
                        <th>Multiplier</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (!empty($overtimes)): ?>

                    <?php foreach ($overtimes as $overtime): ?>

                        <tr>

                            <td>
                                <?= (int)$overtime['id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $overtime['employee_code'] ?? 'Unavailable'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    trim(
                                        ($overtime['first_name'] ?? '') . ' ' .
                                        ($overtime['last_name'] ?? '')
                                    ) ?: 'Unavailable'
                                ) ?>
                            </td>

                            <td>
                                <?= number_format((float)$overtime['hours'], 2) ?>
                            </td>

                            <td>
                                <?= number_format((float)$overtime['multiplier'], 2) ?>x
                            </td>

                            <td>
                                <?= htmlspecialchars($overtime['date']) ?>
                            </td>

                            <td>

                                <a
                                    href="view.php?id=<?= (int)$overtime['id'] ?>"
                                    class="btn btn-sm btn-info text-white"
                                >
                                    View
                                </a>

                                <a
                                    href="edit.php?id=<?= (int)$overtime['id'] ?>"
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
                            No overtime records found.
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
