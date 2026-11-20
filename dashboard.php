
<?php

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/config/database.php';

// Default dashboard values
$totalEmployees = 0;
$activeEmployees = 0;
$todayPresent = 0;
$pendingLeaves = 0;
$monthlyPayroll = 0;
$salaryExpense = 0;

try {

    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) FROM employees");
    $totalEmployees = $stmt->fetchColumn();

    // Active employees
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM employees
        WHERE employment_status = 'Active'
    ");
    $activeEmployees = $stmt->fetchColumn();

    // Today's present employees
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance
        WHERE attendance_date = CURDATE()
        AND status = 'Present'
    ");
    $stmt->execute();
    $todayPresent = $stmt->fetchColumn();

    // Pending leaves
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM annual_leaves
        WHERE status = 'Pending'
    ");
    $pendingLeaves = $stmt->fetchColumn();

    // Current month payroll
    $currentMonth = date('Y-m');

    $stmt = $pdo->prepare("
        SELECT COUNT(*), COALESCE(SUM(net_salary), 0)
        FROM payroll_management
        WHERE month_year = ?
    ");

    $stmt->execute([$currentMonth]);
    $payrollData = $stmt->fetch(PDO::FETCH_NUM);

    $monthlyPayroll = $payrollData[0];
    $salaryExpense = $payrollData[1];

} catch (PDOException $e) {
    error_log($e->getMessage());
}

$adminName = $_SESSION['admin_name'] ?? 'Super Admin';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | HR & Payroll</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          rel="stylesheet">

    <style>

        body {
            background: #f4f6fa;
            font-family: Arial, sans-serif;
            margin: 0;
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
            font-weight: bold;
            margin-bottom: 35px;
            padding-left: 10px;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 13px 12px;
            border-radius: 7px;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #2563eb;
            color: white;
        }

        .sidebar a i {
            width: 25px;
        }

        .main-content {
            margin-left: 250px;
            padding: 25px;
        }

        .topbar {
            background: white;
            padding: 18px 22px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
            height: 100%;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 27px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #64748b;
            font-size: 14px;
            margin: 0;
        }

        .welcome-box {
            background: #2563eb;
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .welcome-box h3 {
            font-weight: bold;
        }

        @media(max-width: 768px) {

            .sidebar {
                width: 70px;
                padding: 20px 8px;
            }

            .sidebar h4 {
                font-size: 0;
                text-align: center;
            }

            .sidebar h4 i {
                font-size: 22px;
            }

            .sidebar a {
                text-align: center;
                font-size: 0;
                padding: 14px 5px;
            }

            .sidebar a i {
                font-size: 17px;
                width: auto;
            }

            .main-content {
                margin-left: 70px;
                padding: 12px;
            }

            .topbar {
                padding: 15px;
            }

            .topbar h5 {
                font-size: 15px;
            }

        }

    </style>

</head>

<body>

<!-- Sidebar -->

<div class="sidebar">

    <h4>
        <i class="fas fa-building"></i>
        HR System
    </h4>

    <a href="dashboard.php" class="active">
        <i class="fas fa-home"></i>
        Dashboard
    </a>

    <a href="modules/employees/index.php">
        <i class="fas fa-users"></i>
        Employees
    </a>

    <a href="modules/attendance/index.php">
        <i class="fas fa-calendar-check"></i>
        Attendance
    </a>

    <a href="modules/salary/index.php">
        <i class="fas fa-money-bill"></i>
        Salary Management
    </a>

    <a href="modules/advances/index.php">
        <i class="fas fa-hand-holding-dollar"></i>
        Advances
    </a>

    <a href="modules/deductions/index.php">
        <i class="fas fa-file-invoice-dollar"></i>
        Deductions
    </a>

    <a href="modules/overtime/index.php">
        <i class="fas fa-clock"></i>
        Overtime
    </a>

    <a href="modules/bonuses/index.php">
        <i class="fas fa-gift"></i>
        Bonuses
    </a>

    <a href="modules/leaves/index.php">
        <i class="fas fa-calendar-alt"></i>
        Annual Leaves
    </a>

    <a href="modules/payroll/index.php">
        <i class="fas fa-wallet"></i>
        Payroll
    </a>

    <a href="modules/reports/index.php">
        <i class="fas fa-chart-bar"></i>
        Reports
    </a>

    <a href="modules/settings/index.php">
        <i class="fas fa-cog"></i>
        Settings
    </a>

    <!-- Audit Logs - New Link -->
    <a href="modules/audit_logs/index.php">
        <i class="fas fa-clipboard-list"></i>
        Audit Logs
    </a>

    <a href="logout.php">
        <i class="fas fa-sign-out-alt"></i>
        Logout
    </a>

</div>

<!-- Main Content -->

<div class="main-content">

    <div class="topbar">

        <h5 class="mb-0">
            <i class="fas fa-home text-primary"></i>
            Dashboard
        </h5>

        <div>
            <i class="fas fa-user-circle text-primary"></i>

            <strong>
                <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
            </strong>
        </div>

    </div>

    <div class="welcome-box">

        <h3>
            Welcome back,
            <?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>!
        </h3>

        <p class="mb-0">
            Here's what's happening in your company today.
        </p>

    </div>

    <div class="row g-4">

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="fas fa-users"></i>
                </div>
                <h3><?= number_format($totalEmployees) ?></h3>
                <p>Total Employees</p>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="fas fa-user-check"></i>
                </div>
                <h3><?= number_format($activeEmployees) ?></h3>
                <p>Active Employees</p>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-info-subtle text-info">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?= number_format($todayPresent) ?></h3>
                <p>Present Today</p>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="fas fa-calendar-minus"></i>
                </div>
                <h3><?= number_format($pendingLeaves) ?></h3>
                <p>Pending Leaves</p>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-danger-subtle text-danger">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3><?= number_format($monthlyPayroll) ?></h3>
                <p>Payroll Records This Month</p>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="fas fa-wallet"></i>
                </div>
                <h3>PKR <?= number_format((float)$salaryExpense) ?></h3>
                <p>Monthly Net Salary Expense</p>
            </div>
        </div>

    </div>

    <div class="text-center mt-5 text-muted">
        <small>
            HR & Payroll Management System
            &copy; <?= date('Y') ?>
        </small>
    </div>

</div>

</body>
</html>
