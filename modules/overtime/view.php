
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.employee_id,
        o.hours,
        o.date,
        o.multiplier,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.designation,
        e.employment_status
    FROM overtime_management o
    LEFT JOIN employees e ON e.id = o.employee_id
    WHERE o.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$overtime = $stmt->fetch();

if (!$overtime) {
    header("Location: index.php?error=not_found");
    exit;
}

$employeeName = trim(
    ($overtime['first_name'] ?? '') . ' ' .
    ($overtime['last_name'] ?? '')
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Overtime - HR Payroll System</title>

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

        .detail-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .detail-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            margin-bottom: 22px;
        }

        .highlight {
            font-size: 24px;
            font-weight: bold;
            color: #0d6efd;
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

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

        <div>
            <h2 class="mb-1">View Overtime</h2>
            <p class="text-muted mb-0">Overtime record details.</p>
        </div>

        <div>

            <a
                href="edit.php?id=<?= (int)$overtime['id'] ?>"
                class="btn btn-warning"
            >
                Edit Overtime
            </a>

            <a href="index.php" class="btn btn-secondary">
                Back
            </a>

        </div>

    </div>

    <div class="detail-card">

        <h5 class="mb-4">Overtime Information</h5>

        <div class="row">

            <div class="col-md-4">

                <div class="detail-label">Overtime ID</div>

                <div class="detail-value">
                    <?= (int)$overtime['id'] ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="detail-label">Employee Code</div>

                <div class="detail-value">
                    <?= htmlspecialchars(
                        $overtime['employee_code'] ?? 'Employee unavailable'
                    ) ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="detail-label">Employee Status</div>

                <div class="detail-value">

                    <?php
                    $status = $overtime['employment_status'] ?? 'Unavailable';

                    $statusClass = $status === 'Active'
                        ? 'bg-success'
                        : ($status === 'Inactive' ? 'bg-secondary' : 'bg-danger');
                    ?>

                    <span class="badge <?= $statusClass ?>">
                        <?= htmlspecialchars($status) ?>
                    </span>

                </div>

            </div>

        </div>

        <hr>

        <div class="row">

            <div class="col-md-6">

                <div class="detail-label">Employee Name</div>

                <div class="detail-value">
                    <?= htmlspecialchars($employeeName ?: 'Employee unavailable') ?>
                </div>

            </div>

            <div class="col-md-6">

                <div class="detail-label">Designation</div>

                <div class="detail-value">
                    <?= htmlspecialchars($overtime['designation'] ?? 'Not available') ?>
                </div>

            </div>

        </div>

        <div class="row">

            <div class="col-md-4">

                <div class="detail-label">Overtime Hours</div>

                <div class="detail-value highlight">
                    <?= number_format((float)$overtime['hours'], 2) ?> Hours
                </div>

            </div>

            <div class="col-md-4">

                <div class="detail-label">Multiplier</div>

                <div class="detail-value highlight">
                    <?= number_format((float)$overtime['multiplier'], 2) ?>x
                </div>

            </div>

            <div class="col-md-4">

                <div class="detail-label">Overtime Date</div>

                <div class="detail-value">
                    <?= htmlspecialchars($overtime['date']) ?>
                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>
