<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$employee_id = (int) $_GET['id'];

$stmt = $pdo->prepare("
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
        base_salary,
        created_at,
        updated_at
    FROM employees
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$employee_id]);

$employee = $stmt->fetch();

if (!$employee) {
    header("Location: index.php");
    exit;
}

$full_name = trim(
    $employee['first_name'] . " " . $employee['last_name']
);

$status = $employee['employment_status'];

if ($status === "Active") {
    $status_class = "bg-success";
} elseif ($status === "Inactive") {
    $status_class = "bg-secondary";
} else {
    $status_class = "bg-danger";
}

$created_at = !empty($employee['created_at'])
    ? date("d M Y, h:i A", strtotime($employee['created_at']))
    : "-";

$updated_at = !empty($employee['updated_at'])
    ? date("d M Y, h:i A", strtotime($employee['updated_at']))
    : "-";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Employee | HR Payroll System</title>

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
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            color: #d9deea;
            text-decoration: none;
            padding: 10px 15px;
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
            font-size: 28px;
            font-weight: 600;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #172033;
        }

        .info-label {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 500;
            color: #212529;
        }

        .salary {
            font-size: 20px;
            font-weight: 700;
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
    <a href="index.php">Employees</a>
    <a href="../attendance/index.php">Attendance</a>
    <a href="../salary/index.php">Salary Management</a>
    <a href="../advances/index.php">Advances</a>
    <a href="../deductions/index.php">Deductions</a>
    <a href="../overtime/index.php">Overtime</a>
    <a href="../bonuses/index.php">Bonuses</a>
    <a href="../annual_leaves/index.php">Annual Leaves</a>
    <a href="../payroll/index.php">Payroll</a>
    <a href="../reports/index.php">Reports</a>
    <a href="../settings/index.php">Settings</a>
    <a href="../../logout.php">Logout</a>

</div>


<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h1 class="page-title">Employee Details</h1>

            <p class="text-muted mb-0">
                View complete employee information
            </p>
        </div>

        <div>

            <a href="edit.php?id=<?php echo $employee['id']; ?>"
               class="btn btn-primary">
                Edit Employee
            </a>

            <a href="index.php"
               class="btn btn-secondary">
                Back
            </a>

        </div>

    </div>


    <div class="info-card mb-4">

        <div class="section-title">
            Basic Information
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Employee Code
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($employee['employee_code']); ?>
                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Full Name
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($full_name); ?>
                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Designation
                </div>

                <div class="info-value">
                    <?php echo !empty($employee['designation'])
                        ? htmlspecialchars($employee['designation'])
                        : "-"; ?>
                </div>

            </div>

        </div>

    </div>


    <div class="info-card mb-4">

        <div class="section-title">
            Contact Information
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Email
                </div>

                <div class="info-value">

                    <?php echo !empty($employee['email'])
                        ? htmlspecialchars($employee['email'])
                        : "-"; ?>

                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Phone
                </div>

                <div class="info-value">

                    <?php echo !empty($employee['phone'])
                        ? htmlspecialchars($employee['phone'])
                        : "-"; ?>

                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Joining Date
                </div>

                <div class="info-value">

                    <?php echo !empty($employee['joining_date'])
                        ? date("d M Y", strtotime($employee['joining_date']))
                        : "-"; ?>

                </div>

            </div>

        </div>

    </div>


    <div class="info-card mb-4">

        <div class="section-title">
            Employment Information
        </div>

        <div class="row">

            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Employment Status
                </div>

                <div class="info-value">

                    <span class="badge <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($status); ?>
                    </span>

                </div>

            </div>


            <div class="col-md-4 mb-3">

                <div class="info-label">
                    Base Salary
                </div>

                <div class="info-value salary">

                    <?php echo number_format(
                        (float)$employee['base_salary'],
                        2
                    ); ?>

                </div>

            </div>

        </div>

    </div>


    <div class="info-card">

        <div class="section-title">
            Record Information
        </div>

        <div class="row">

            <div class="col-md-6 mb-3">

                <div class="info-label">
                    Created At
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($created_at); ?>
                </div>

            </div>


            <div class="col-md-6 mb-3">

                <div class="info-label">
                    Last Updated
                </div>

                <div class="info-value">
                    <?php echo htmlspecialchars($updated_at); ?>
                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>