
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.employee_id,
        b.amount,
        b.reason,
        b.date,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.designation,
        e.employment_status
    FROM bonus_management b
    LEFT JOIN employees e ON e.id = b.employee_id
    WHERE b.id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$bonus = $stmt->fetch();

if (!$bonus) {
    header("Location: index.php?error=not_found");
    exit;
}

$employeeName = trim(
    ($bonus['first_name'] ?? '') . ' ' .
    ($bonus['last_name'] ?? '')
);

if ($employeeName === '') {
    $employeeName = 'Employee Not Found';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bonus | HR & Payroll</title>

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
        <h2>Bonus Details</h2>

        <a href="index.php" class="btn btn-secondary">
            Back to Bonuses
        </a>
    </div>

    <div class="details-card">

        <div class="detail-row">
            <div class="detail-label">Bonus ID</div>
            <div><?= (int)$bonus['id'] ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Employee Code</div>
            <div><?= htmlspecialchars($bonus['employee_code'] ?? 'N/A') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Employee Name</div>
            <div><?= htmlspecialchars($employeeName) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Designation</div>
            <div><?= htmlspecialchars($bonus['designation'] ?? 'N/A') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Employment Status</div>
            <div><?= htmlspecialchars($bonus['employment_status'] ?? 'Employee Not Found') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Bonus Amount</div>
            <div><?= number_format((float)$bonus['amount'], 2) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Bonus Reason</div>
            <div><?= nl2br(htmlspecialchars($bonus['reason'] ?? '')) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-label">Bonus Date</div>
            <div><?= htmlspecialchars($bonus['date']) ?></div>
        </div>

        <div class="mt-4">
            <a href="edit.php?id=<?= (int)$bonus['id'] ?>"
               class="btn btn-warning">
                Edit Bonus
            </a>

            <a href="index.php" class="btn btn-secondary">
                Back
            </a>
        </div>

    </div>

</div>

</body>
</html>
