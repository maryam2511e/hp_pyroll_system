<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$advance_id = $_GET['id'] ?? '';

if (!ctype_digit((string)$advance_id) || (int)$advance_id <= 0) {
    header("Location: index.php?error=Invalid advance ID");
    exit;
}

$advance_id = (int)$advance_id;

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.employee_id,
        a.amount,
        a.request_date,
        a.status,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.email,
        e.phone,
        e.designation,
        e.employment_status
    FROM advance_management a
    INNER JOIN employees e
        ON e.id = a.employee_id
    WHERE a.id = ?
");

$stmt->execute([$advance_id]);

$advance = $stmt->fetch();

if (!$advance) {
    header("Location: index.php?error=Advance record not found");
    exit;
}

function formatAmount($amount)
{
    return number_format((float)$amount, 2);
}

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

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Advance - HR Payroll System</title>

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
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .detail-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .label {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .value {
            font-size: 16px;
            font-weight: 500;
        }

        .amount {
            font-size: 24px;
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

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">

        <div>

            <h2>View Salary Advance</h2>

            <p class="text-muted mb-0">
                Advance record details
            </p>

        </div>

        <div class="d-flex gap-2">

            <a
                href="edit.php?id=<?= (int)$advance['id'] ?>"
                class="btn btn-warning"
            >
                Edit
            </a>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                Back
            </a>

        </div>

    </div>

    <div class="detail-card">

        <div class="detail-title">
            Advance Information
        </div>

        <div class="row g-4">

            <div class="col-md-4">

                <div class="label">
                    Advance ID
                </div>

                <div class="value">
                    <?= (int)$advance['id'] ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Request Date
                </div>

                <div class="value">
                    <?= htmlspecialchars($advance['request_date']) ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Status
                </div>

                <div class="value">

                    <span class="badge <?= $badgeClass ?>">
                        <?= htmlspecialchars($status) ?>
                    </span>

                </div>

            </div>

            <div class="col-md-12">

                <div class="label">
                    Advance Amount
                </div>

                <div class="amount">
                    <?= formatAmount($advance['amount']) ?>
                </div>

            </div>

        </div>

    </div>

    <div class="detail-card">

        <div class="detail-title">
            Employee Information
        </div>

        <div class="row g-4">

            <div class="col-md-4">

                <div class="label">
                    Employee Code
                </div>

                <div class="value">
                    <?= htmlspecialchars($advance['employee_code']) ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Employee Name
                </div>

                <div class="value">
                    <?= htmlspecialchars(
                        $advance['first_name'] . ' ' . $advance['last_name']
                    ) ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Designation
                </div>

                <div class="value">
                    <?= htmlspecialchars($advance['designation']) ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Email
                </div>

                <div class="value">
                    <?= $advance['email']
                        ? htmlspecialchars($advance['email'])
                        : 'N/A' ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Phone
                </div>

                <div class="value">
                    <?= $advance['phone']
                        ? htmlspecialchars($advance['phone'])
                        : 'N/A' ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="label">
                    Employment Status
                </div>

                <div class="value">
                    <?= htmlspecialchars($advance['employment_status']) ?>
                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>