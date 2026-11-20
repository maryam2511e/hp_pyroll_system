<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.employee_id,
        d.amount,
        d.reason,
        d.date,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.designation,
        e.employment_status
    FROM deduction_management d
    INNER JOIN employees e ON d.employee_id = e.id
    WHERE d.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$deduction = $stmt->fetch();

if (!$deduction) {
    header("Location: index.php?error=not_found");
    exit;
}

$employeeName = $deduction['first_name'] . " " . $deduction['last_name'];

$status = $deduction['employment_status'];

if ($status === "Active") {
    $statusClass = "bg-success";
} elseif ($status === "Inactive") {
    $statusClass = "bg-secondary";
} else {
    $statusClass = "bg-danger";
}

$amount = number_format((float)$deduction['amount'], 2);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Deduction - HR Payroll System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #f4f6f9;
            font-family: Arial, sans-serif;
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

        .sidebar h4 {
            margin-bottom: 25px;
        }

        .sidebar a {
            color: #dbe2ef;
            text-decoration: none;
            display: block;
            padding: 10px 12px;
            margin-bottom: 5px;
            border-radius: 6px;
        }

        .sidebar a:hover {
            background: #26344f;
            color: white;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .detail-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .detail-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            color: #212529;
            margin-bottom: 20px;
        }

        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
        }

        @media (max-width: 768px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<div class="sidebar">

    <h4>HR Payroll</h4>

    <a href="../../dashboard.php">Dashboard</a>

    <a href="../employees/index.php">Employees</a>

    <a href="../attendance/index.php">Attendance</a>

    <a href="../salary/index.php">Salary Management</a>

    <a href="../advances/index.php">Advances</a>

    <a href="index.php">Deductions</a>

    <a href="../overtime/index.php">Overtime</a>

    <a href="../bonuses/index.php">Bonuses</a>

    <a href="../leaves/index.php">Annual Leaves</a>

    <a href="../payroll/index.php">Payroll</a>

    <a href="../reports/index.php">Reports</a>

    <a href="../settings/index.php">Settings</a>

    <a href="../../logout.php">Logout</a>

</div>

<div class="main-content">

    <div class="page-title">

        <div class="d-flex justify-content-between align-items-center">

            <div>
                <h2>View Deduction</h2>
                <p class="text-muted mb-0">
                    Deduction details
                </p>
            </div>

            <div>

                <a href="edit.php?id=<?php echo $deduction['id']; ?>"
                   class="btn btn-warning">
                    Edit Deduction
                </a>

                <a href="index.php"
                   class="btn btn-secondary">
                    Back
                </a>

            </div>

        </div>

    </div>

    <div class="detail-card">

        <h5 class="mb-4">Deduction Information</h5>

        <div class="row">

            <div class="col-md-4">

                <div class="detail-label">
                    Deduction ID
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($deduction['id']); ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="detail-label">
                    Employee Code
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($deduction['employee_code']); ?>
                </div>

            </div>

            <div class="col-md-4">

                <div class="detail-label">
                    Employee Status
                </div>

                <div class="detail-value">

                    <span class="badge <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($status); ?>
                    </span>

                </div>

            </div>

        </div>

        <hr>

        <div class="row">

            <div class="col-md-6">

                <div class="detail-label">
                    Employee Name
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($employeeName); ?>
                </div>

            </div>

            <div class="col-md-6">

                <div class="detail-label">
                    Designation
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($deduction['designation']); ?>
                </div>

            </div>

        </div>

        <div class="row">

            <div class="col-md-6">

                <div class="detail-label">
                    Deduction Amount
                </div>

                <div class="detail-value amount">
                    <?php echo $amount; ?>
                </div>

            </div>

            <div class="col-md-6">

                <div class="detail-label">
                    Deduction Date
                </div>

                <div class="detail-value">
                    <?php echo htmlspecialchars($deduction['date']); ?>
                </div>

            </div>

        </div>

        <div class="row">

            <div class="col-12">

                <div class="detail-label">
                    Reason
                </div>

                <div class="detail-value">

                    <?php
                    echo nl2br(
                        htmlspecialchars($deduction['reason'])
                    );
                    ?>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>