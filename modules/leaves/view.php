
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$leaveId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$leaveId || $leaveId <= 0) {
    header("Location: index.php?error=not_found");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        al.*,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.designation,
        e.employment_status
    FROM annual_leaves al
    LEFT JOIN employees e ON al.employee_id = e.id
    WHERE al.id = ?
    LIMIT 1
");

$stmt->execute([$leaveId]);
$leave = $stmt->fetch();

if (!$leave) {
    header("Location: index.php?error=not_found");
    exit;
}

$statusClass = 'secondary';

if ($leave['status'] === 'Approved') {
    $statusClass = 'success';
} elseif ($leave['status'] === 'Pending') {
    $statusClass = 'warning';
} elseif ($leave['status'] === 'Rejected') {
    $statusClass = 'danger';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Annual Leave | HR & Payroll</title>

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

        .details-card {
            max-width: 800px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .detail-row {
            padding: 13px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #212529;
            overflow-wrap: anywhere;
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

    <h2 class="mb-4">Annual Leave Details</h2>

    <div class="details-card">

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Leave Record #<?= (int)$leave['id'] ?></h5>

            <span class="badge bg-<?= $statusClass ?>">
                <?= htmlspecialchars($leave['status']) ?>
            </span>
        </div>

        <hr>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Employee Code</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars($leave['employee_code'] ?? 'Employee not found') ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Employee Name</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars(trim(($leave['first_name'] ?? '') . ' ' . ($leave['last_name'] ?? ''))) ?: 'Employee not found' ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Designation</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars($leave['designation'] ?? 'N/A') ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Employment Status</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars($leave['employment_status'] ?? 'Employee not found') ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Leave Type</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars($leave['leave_type']) ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Start Date</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars($leave['start_date']) ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">End Date</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars($leave['end_date']) ?>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Total Days</div>
            <div class="col-md-8 detail-value">
                <?= htmlspecialchars((string)$leave['total_days']) ?> Days
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Leave Status</div>
            <div class="col-md-8 detail-value">
                <span class="badge bg-<?= $statusClass ?>">
                    <?= htmlspecialchars($leave['status']) ?>
                </span>
            </div>
        </div>

        <div class="row detail-row">
            <div class="col-md-4 detail-label">Reason</div>
            <div class="col-md-8 detail-value">
                <?= nl2br(htmlspecialchars($leave['reason'] ?? 'No reason provided')) ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="edit.php?id=<?= (int)$leave['id'] ?>" class="btn btn-primary">
                Edit Leave
            </a>

            <a href="index.php" class="btn btn-secondary">
                Back to Leaves
            </a>
        </div>

    </div>

</div>

</body>
</html>
