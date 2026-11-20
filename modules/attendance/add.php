<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

$error = "";

$employee_id = "";
$attendance_date = date("Y-m-d");
$status = "Present";
$check_in_time = "";
$check_out_time = "";
$is_sunday = 0;

try {

    // Load active employees
    $stmt = $pdo->query("
        SELECT id, employee_code, first_name, last_name
        FROM employees
        WHERE employment_status = 'Active'
        ORDER BY first_name, last_name
    ");

    $employees = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log($e->getMessage());

    $employees = [];
    $error = "Unable to load employees.";

}


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

    // Validate employee
    if ($employee_id <= 0) {

        $error = "Please select an employee.";

    // Validate date
    } elseif (
        empty($attendance_date) ||
        !DateTime::createFromFormat("Y-m-d", $attendance_date)
    ) {

        $error = "Please select a valid attendance date.";

    // Prevent future attendance
    } elseif ($attendance_date > date("Y-m-d")) {

        $error = "Attendance date cannot be a future date.";

    // Validate status
    } elseif (!in_array($status, $allowed_statuses, true)) {

        $error = "Invalid attendance status.";

    // Validate check-in time
    } elseif (
        $check_in_time !== "" &&
        !preg_match('/^\d{2}:\d{2}$/', $check_in_time)
    ) {

        $error = "Invalid check-in time.";

    // Validate check-out time
    } elseif (
        $check_out_time !== "" &&
        !preg_match('/^\d{2}:\d{2}$/', $check_out_time)
    ) {

        $error = "Invalid check-out time.";

    } else {

        try {

            // Check employee exists and is active
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

                // Prevent duplicate attendance
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM attendance
                    WHERE employee_id = ?
                    AND attendance_date = ?
                ");

                $stmt->execute([
                    $employee_id,
                    $attendance_date
                ]);

                if ($stmt->fetch()) {

                    $error = "Attendance for this employee and date already exists.";

                } else {

                    // Automatically detect Sunday
                    $dayOfWeek = date(
                        "N",
                        strtotime($attendance_date)
                    );

                    $is_sunday = ($dayOfWeek == 7) ? 1 : 0;

                    // For Absent/Leave, clear check-in/out
                    if (
                        $status === "Absent" ||
                        $status === "Leave"
                    ) {
                        $check_in_time = "";
                        $check_out_time = "";
                    }

                    // For Half-Day, check-in/out are optional

                    $stmt = $pdo->prepare("
                        INSERT INTO attendance
                        (
                            employee_id,
                            attendance_date,
                            status,
                            check_in_time,
                            check_out_time,
                            is_sunday
                        )
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $employee_id,
                        $attendance_date,
                        $status,
                        $check_in_time !== "" ? $check_in_time : null,
                        $check_out_time !== "" ? $check_out_time : null,
                        $is_sunday
                    ]);

                    // Audit log
                    try {

                        $audit = $pdo->prepare("
                            INSERT INTO audit_logs
                            (action, timestamp)
                            VALUES (?, CURRENT_TIMESTAMP)
                        ");

                        $audit->execute([
                            "Attendance added for employee ID {$employee_id} on {$attendance_date}"
                        ]);

                    } catch (PDOException $auditError) {

                        error_log($auditError->getMessage());

                    }

                    header("Location: index.php?success=1");
                    exit;
                }
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $error = "Unable to save attendance record.";

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

    <title>Mark Attendance | HR & Payroll</title>

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

            <i class="fas fa-calendar-check text-primary"></i>

            Mark Attendance

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
                Add Attendance
            </h4>

            <p class="text-muted mb-0">
                Mark daily attendance for an active employee.
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

                    Save Attendance

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