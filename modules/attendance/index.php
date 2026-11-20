<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$success = "";
$error = "";

if (isset($_GET['success'])) {
    $success = "Attendance record saved successfully.";
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'duplicate') {
        $error = "Attendance for this employee and date already exists.";
    } elseif ($_GET['error'] === 'invalid') {
        $error = "Invalid request.";
    } else {
        $error = "Something went wrong.";
    }
}

try {

    $stmt = $pdo->query("
        SELECT
            a.id,
            a.employee_id,
            a.attendance_date,
            a.status,
            a.check_in_time,
            a.check_out_time,
            a.is_sunday,
            e.employee_code,
            e.first_name,
            e.last_name
        FROM attendance a
        INNER JOIN employees e
            ON a.employee_id = e.id
        ORDER BY a.attendance_date DESC, a.id DESC
    ");

    $attendanceRecords = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log($e->getMessage());

    $attendanceRecords = [];
    $error = "Unable to load attendance records.";

}

$adminName = $_SESSION['admin_name'] ?? 'Super Admin';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Attendance | HR & Payroll</title>

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

        .page-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }

        .table-responsive {
            margin-top: 20px;
        }

        .table th {
            white-space: nowrap;
            background: #f8fafc;
        }

        .badge-present {
            background: #dcfce7;
            color: #166534;
        }

        .badge-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-leave {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-half {
            background: #dbeafe;
            color: #1e40af;
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

    <a href="../../dashboard.php">
        <i class="fas fa-home"></i>
        Dashboard
    </a>

    <a href="../employees/index.php">
        <i class="fas fa-users"></i>
        Employees
    </a>

    <a href="index.php" class="active">
        <i class="fas fa-calendar-check"></i>
        Attendance
    </a>

    <a href="../salary/index.php">
        <i class="fas fa-money-bill"></i>
        Salary Management
    </a>

    <a href="../advances/index.php">
        <i class="fas fa-hand-holding-dollar"></i>
        Advances
    </a>

    <a href="../deductions/index.php">
        <i class="fas fa-file-invoice-dollar"></i>
        Deductions
    </a>

    <a href="../overtime/index.php">
        <i class="fas fa-clock"></i>
        Overtime
    </a>

    <a href="../bonuses/index.php">
        <i class="fas fa-gift"></i>
        Bonuses
    </a>

    <a href="../leaves/index.php">
        <i class="fas fa-calendar-alt"></i>
        Annual Leaves
    </a>

    <a href="../payroll/index.php">
        <i class="fas fa-wallet"></i>
        Payroll
    </a>

    <a href="../reports/index.php">
        <i class="fas fa-chart-bar"></i>
        Reports
    </a>

    <a href="../settings/index.php">
        <i class="fas fa-cog"></i>
        Settings
    </a>

    <a href="../../logout.php">
        <i class="fas fa-sign-out-alt"></i>
        Logout
    </a>

</div>


<!-- Main Content -->

<div class="main-content">

    <!-- Topbar -->

    <div class="topbar">

        <h5 class="mb-0">
            <i class="fas fa-calendar-check text-primary"></i>
            Attendance Management
        </h5>

        <div>
            <i class="fas fa-user-circle text-primary"></i>

            <strong>
                <?= htmlspecialchars($adminName) ?>
            </strong>
        </div>

    </div>


    <!-- Page Card -->

    <div class="page-card">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>

                <h4 class="mb-1">
                    Attendance Records
                </h4>

                <p class="text-muted mb-0">
                    Manage employee daily attendance.
                </p>

            </div>

            <a href="add.php" class="btn btn-primary">

                <i class="fas fa-plus"></i>

                Mark Attendance

            </a>

        </div>


        <?php if ($success): ?>

            <div class="alert alert-success mt-4">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-danger mt-4">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Employee</th>

                        <th>Code</th>

                        <th>Date</th>

                        <th>Status</th>

                        <th>Check In</th>

                        <th>Check Out</th>

                        <th>Sunday</th>

                        <th>Action</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($attendanceRecords)): ?>

                    <tr>

                        <td colspan="9" class="text-center text-muted py-4">

                            No attendance records found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($attendanceRecords as $record): ?>

                        <tr>

                            <td>
                                <?= (int) $record['id'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $record['first_name'] . ' ' . $record['last_name']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($record['employee_code']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($record['attendance_date']) ?>
                            </td>

                            <td>

                                <?php

                                $status = $record['status'];

                                if ($status === 'Present') {
                                    $badgeClass = 'badge-present';
                                } elseif ($status === 'Absent') {
                                    $badgeClass = 'badge-absent';
                                } elseif ($status === 'Leave') {
                                    $badgeClass = 'badge-leave';
                                } else {
                                    $badgeClass = 'badge-half';
                                }

                                ?>

                                <span class="badge <?= $badgeClass ?>">

                                    <?= htmlspecialchars($status) ?>

                                </span>

                            </td>

                            <td>
                                <?= $record['check_in_time']
                                    ? htmlspecialchars($record['check_in_time'])
                                    : '-' ?>
                            </td>

                            <td>
                                <?= $record['check_out_time']
                                    ? htmlspecialchars($record['check_out_time'])
                                    : '-' ?>
                            </td>

                            <td>

                                <?php if ($record['is_sunday']): ?>

                                    <span class="badge bg-info">
                                        Yes
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        No
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <a href="edit.php?id=<?= (int) $record['id'] ?>"
                                   class="btn btn-sm btn-outline-primary">

                                    <i class="fas fa-edit"></i>

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>

</html>