<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$employees = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            employee_code,
            first_name,
            last_name,
            email,
            phone,
            designation,
            joining_date,
            employment_status,
            base_salary
        FROM employees
        ORDER BY id DESC
    ");

    $employees = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log($e->getMessage());
    $dbError = "Unable to load employees.";

}

$adminName = $_SESSION['admin_name'] ?? 'Super Admin';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Employees | HR & Payroll</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .page-header {
            background: white;
            padding: 22px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
        }

        .page-header h3 {
            margin: 0;
            font-weight: bold;
        }

        .page-header p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
        }

        .table th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
            font-size: 14px;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-terminated {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 45px;
            margin-bottom: 15px;
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

            .table-card {
                padding: 10px;
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

    <a href="index.php" class="active">
        <i class="fas fa-users"></i>
        Employees
    </a>

    <a href="../attendance/index.php">
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
            <i class="fas fa-users text-primary"></i>
            Employees
        </h5>

        <div>

            <i class="fas fa-user-circle text-primary"></i>

            <strong>
                <?= htmlspecialchars($adminName) ?>
            </strong>

        </div>

    </div>


    <!-- Page Header -->

    <div class="page-header">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>

                <h3>
                    <i class="fas fa-users text-primary"></i>
                    Employee Management
                </h3>

                <p>
                    Manage company employees and their employment information.
                </p>

            </div>

            <div>

                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Employee
                </a>

            </div>

        </div>

    </div>


    <!-- Employee Table -->

    <div class="table-card">

        <?php if (isset($dbError)): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($dbError) ?>
            </div>

        <?php elseif (count($employees) > 0): ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Employee Code</th>

                            <th>Employee Name</th>

                            <th>Designation</th>

                            <th>Phone</th>

                            <th>Joining Date</th>

                            <th>Salary</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($employees as $employee): ?>

                        <?php

                        $status = $employee['employment_status'];

                        $statusClass = 'badge-active';

                        if ($status === 'Inactive') {
                            $statusClass = 'badge-inactive';
                        } elseif ($status === 'Terminated') {
                            $statusClass = 'badge-terminated';
                        }

                        ?>

                        <tr>

                            <td>
                                <?= (int)$employee['id'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($employee['employee_code']) ?>
                                </strong>
                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    $employee['first_name'] . ' ' . $employee['last_name']
                                ) ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($employee['designation'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($employee['phone'] ?? '-') ?>
                            </td>

                            <td>

                                <?php
                                if (!empty($employee['joining_date'])) {
                                    echo date(
                                        'd M Y',
                                        strtotime($employee['joining_date'])
                                    );
                                } else {
                                    echo '-';
                                }
                                ?>

                            </td>

                            <td>

                                PKR
                                <?= number_format(
                                    (float)$employee['base_salary'],
                                    2
                                ) ?>

                            </td>

                            <td>

                                <span class="badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>

                            </td>

                            <td>

                                <a href="view.php?id=<?= (int)$employee['id'] ?>"
                                   class="btn btn-sm btn-outline-primary"
                                   title="View">

                                    <i class="fas fa-eye"></i>

                                </a>

                                <a href="edit.php?id=<?= (int)$employee['id'] ?>"
                                   class="btn btn-sm btn-outline-secondary"
                                   title="Edit">

                                    <i class="fas fa-edit"></i>

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <i class="fas fa-users text-primary"></i>

                <h5>No Employees Found</h5>

                <p class="mb-3">
                    There are currently no employees in the system.
                </p>

                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add First Employee
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>