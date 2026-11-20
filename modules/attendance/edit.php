<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$error = "";
$attendance = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=invalid");
    exit;
}

$attendance_id = (int) $_GET['id'];

/*
|--------------------------------------------------------------------------
| Load Attendance Record
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
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
        WHERE a.id = ?
    ");

    $stmt->execute([$attendance_id]);

    $attendance = $stmt->fetch();

    if (!$attendance) {
        header("Location: index.php?error=invalid");
        exit;
    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    header("Location: index.php?error=database");
    exit;

}


/*
|--------------------------------------------------------------------------
| Load Active Employees
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            employee_code,
            first_name,
            last_name
        FROM employees
        WHERE employment_status = 'Active'
        ORDER BY first_name, last_name
    ");

    $employees = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log($e->getMessage());

    $employees = [];

}


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/

$employee_id = $attendance['employee_id'];
$attendance_date = $attendance['attendance_date'];
$status = $attendance['status'];
$check_in_time = $attendance['check_in_time'] ?? "";
$check_out_time = $attendance['check_out_time'] ?? "";


/*
|--------------------------------------------------------------------------
| Update Attendance
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $employee_id = isset($_POST["employee_id"])
        ? (int) $_POST["employee_id"]
        : 0;

    $attendance_date = trim($_POST["attendance_date"] ?? "");
    $status = trim($_POST["status"] ?? "");
    $check_in_time = trim($_POST["check_in_time"] ?? "");
    $check_out_time = trim($_POST["check_out_time"] ?? "");

    $allowed_statuses = [
        "Present",
        "Absent",
        "Leave",
        "Half-Day"
    ];

    // Employee validation
    if ($employee_id <= 0) {

        $error = "Please select an employee.";

    // Date validation
    } elseif (
        empty($attendance_date) ||
        !DateTime::createFromFormat("Y-m-d", $attendance_date)
    ) {

        $error = "Please select a valid attendance date.";

    // Future date validation
    } elseif ($attendance_date > date("Y-m-d")) {

        $error = "Attendance date cannot be a future date.";

    // Status validation
    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error = "Invalid attendance status.";

    // Check-in validation
    } elseif (
        $check_in_time !== "" &&
        !preg_match('/^\d{2}:\d{2}$/', $check_in_time)
    ) {

        $error = "Invalid check-in time.";

    // Check-out validation
    } elseif (
        $check_out_time !== "" &&
        !preg_match('/^\d{2}:\d{2}$/', $check_out_time)
    ) {

        $error = "Invalid check-out time.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Employee
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM employees
                WHERE id = ?
                AND employment_status = 'Active'
            ");

            $stmt->execute([$employee_id]);

            if (!$stmt->fetch()) {

                $error = "Selected employee is not available.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Prevent Duplicate Attendance
                |--------------------------------------------------------------------------
                | Same employee + same date is allowed for current record,
                | but not for another attendance record.
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM attendance
                    WHERE employee_id = ?
                    AND attendance_date = ?
                    AND id != ?
                ");

                $stmt->execute([
                    $employee_id,
                    $attendance_date,
                    $attendance_id
                ]);

                if ($stmt->fetch()) {

                    $error = "Attendance for this employee and date already exists.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Automatically Detect Sunday
                    |--------------------------------------------------------------------------
                    */

                    $dayOfWeek = date(
                        "N",
                        strtotime($attendance_date)
                    );

                    $is_sunday = ($dayOfWeek == 7) ? 1 : 0;


                    /*
                    |--------------------------------------------------------------------------
                    | Clear Times For Absent / Leave
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $status === "Absent" ||
                        $status === "Leave"
                    ) {

                        $check_in_time = "";
                        $check_out_time = "";

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Update Record
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        UPDATE attendance
                        SET
                            employee_id = ?,
                            attendance_date = ?,
                            status = ?,
                            check_in_time = ?,
                            check_out_time = ?,
                            is_sunday = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $employee_id,
                        $attendance_date,
                        $status,
                        $check_in_time !== "" ? $check_in_time : null,
                        $check_out_time !== "" ? $check_out_time : null,
                        $is_sunday,
                        $attendance_id
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Audit Log
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $audit = $pdo->prepare("
                            INSERT INTO audit_logs
                            (action, timestamp)
                            VALUES (?, CURRENT_TIMESTAMP)
                        ");

                        $audit->execute([
                            "Attendance ID {$attendance_id} updated for employee ID {$employee_id}"
                        ]);

                    } catch (PDOException $auditError) {

                        error_log($auditError->getMessage());

                    }


                    header("Location: index.php?success=updated");
                    exit;
                }
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $error = "Unable to update attendance record.";

        }
    }
}

$adminName = $_SESSION['admin_name'] ?? 'Super Admin';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Attendance | HR & Payroll</title>

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

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
            max-width: 1000px;
        }

        .form-label {
            font-weight: 600;
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

    <div class="topbar">

        <h5 class="mb-0">

            <i class="fas fa-edit text-primary"></i>

            Edit Attendance

        </h5>

        <div>

            <i class="fas fa-user-circle text-primary"></i>

            <strong>
                <?= htmlspecialchars($adminName) ?>
            </strong>

        </div>

    </div>


    <div class="form-card">

        <div class="mb-4">

            <h4>
                Update Attendance
            </h4>

            <p class="text-muted mb-0">
                Update the selected attendance record.
            </p>

        </div>


        <?php if ($error): ?>

            <div class="alert alert-danger">

                <i class="fas fa-exclamation-circle"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <div class="row g-3">

                <!-- Employee -->

                <div class="col-md-6">

                    <label class="form-label">
                        Employee
                    </label>

                    <select
                        name="employee_id"
                        class="form-select"
                        required>

                        <option value="">
                            Select Employee
                        </option>

                        <?php foreach ($employees as $employee): ?>

                            <option
                                value="<?= (int) $employee['id'] ?>"
                                <?= ($employee_id == $employee['id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars(
                                    $employee['employee_code']
                                    . ' - '
                                    . $employee['first_name']
                                    . ' '
                                    . $employee['last_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                        <?php
                        /*
                        |--------------------------------------------------------------------------
                        | Keep Current Employee Available
                        |--------------------------------------------------------------------------
                        | If an employee was made inactive after attendance
                        | was created, show that employee as selected.
                        |--------------------------------------------------------------------------
                        */

                        $currentEmployeeIsActive = false;

                        foreach ($employees as $employee) {

                            if ((int)$employee['id'] === (int)$attendance['employee_id']) {
                                $currentEmployeeIsActive = true;
                                break;
                            }

                        }

                        if (!$currentEmployeeIsActive):
                        ?>

                            <option
                                value="<?= (int) $attendance['employee_id'] ?>"
                                selected>

                                <?= htmlspecialchars(
                                    $attendance['employee_code']
                                    . ' - '
                                    . $attendance['first_name']
                                    . ' '
                                    . $attendance['last_name']
                                    . ' (Inactive)'
                                ) ?>

                            </option>

                        <?php endif; ?>

                    </select>

                </div>


                <!-- Date -->

                <div class="col-md-6">

                    <label class="form-label">
                        Attendance Date
                    </label>

                    <input
                        type="date"
                        name="attendance_date"
                        class="form-control"
                        value="<?= htmlspecialchars($attendance_date) ?>"
                        max="<?= date('Y-m-d') ?>"
                        required>

                </div>


                <!-- Status -->

                <div class="col-md-6">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                        required>

                        <option value="Present"
                            <?= $status === 'Present' ? 'selected' : '' ?>>
                            Present
                        </option>

                        <option value="Absent"
                            <?= $status === 'Absent' ? 'selected' : '' ?>>
                            Absent
                        </option>

                        <option value="Leave"
                            <?= $status === 'Leave' ? 'selected' : '' ?>>
                            Leave
                        </option>

                        <option value="Half-Day"
                            <?= $status === 'Half-Day' ? 'selected' : '' ?>>
                            Half-Day
                        </option>

                    </select>

                </div>


                <!-- Check In -->

                <div class="col-md-3">

                    <label class="form-label">
                        Check In
                    </label>

                    <input
                        type="time"
                        name="check_in_time"
                        class="form-control"
                        value="<?= htmlspecialchars($check_in_time) ?>">

                </div>


                <!-- Check Out -->

                <div class="col-md-3">

                    <label class="form-label">
                        Check Out
                    </label>

                    <input
                        type="time"
                        name="check_out_time"
                        class="form-control"
                        value="<?= htmlspecialchars($check_out_time) ?>">

                </div>

            </div>


            <div class="mt-4">

                <button type="submit"
                        class="btn btn-primary">

                    <i class="fas fa-save"></i>

                    Update Attendance

                </button>

                <a href="index.php"
                   class="btn btn-secondary">

                    <i class="fas fa-arrow-left"></i>

                    Back

                </a>

            </div>

        </form>

    </div>

</div>

</body>

</html>