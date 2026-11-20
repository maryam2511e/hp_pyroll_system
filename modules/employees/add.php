
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once "../../config/database.php";
require_once __DIR__ . '/../../config/audit.php';

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $employee_code = trim($_POST['employee_code'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $joining_date = trim($_POST['joining_date'] ?? '');
    $employment_status = trim($_POST['employment_status'] ?? '');
    $base_salary = trim($_POST['base_salary'] ?? '');

    /*
     * Employee Code
     */
    if ($employee_code === '') {
        $errors[] = "Employee code is required.";
    }

    /*
     * First Name
     */
    if ($first_name === '') {
        $errors[] = "First name is required.";
    } elseif (!preg_match('/^[A-Za-z ]+$/', $first_name)) {
        $errors[] = "First name can contain alphabets and spaces only.";
    }

    /*
     * Last Name
     */
    if ($last_name === '') {
        $errors[] = "Last name is required.";
    } elseif (!preg_match('/^[A-Za-z ]+$/', $last_name)) {
        $errors[] = "Last name can contain alphabets and spaces only.";
    }

    /*
     * Designation
     */
    if ($designation === '') {
        $errors[] = "Designation is required.";
    } elseif (!preg_match('/^[A-Za-z ]+$/', $designation)) {
        $errors[] = "Designation can contain alphabets and spaces only.";
    }

    /*
     * Email
     */
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    /*
     * Phone
     */
    if ($phone === '') {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
        $errors[] = "Phone number must contain exactly 11 digits.";
    }

    /*
     * Joining Date
     */
    if ($joining_date === '') {
        $errors[] = "Joining date is required.";
    } else {
        $today = date('Y-m-d');

        if ($joining_date < $today) {
            $errors[] = "Joining date cannot be a previous date.";
        }
    }

    /*
     * Employment Status
     */
    $allowed_statuses = [
        'Active',
        'Inactive',
        'Terminated'
    ];

    if (!in_array($employment_status, $allowed_statuses, true)) {
        $errors[] = "Invalid employment status.";
    }

    /*
     * Base Salary
     */
    if (
        $base_salary === '' ||
        !is_numeric($base_salary) ||
        (float)$base_salary < 0
    ) {
        $errors[] = "Please enter a valid base salary.";
    }

    /*
     * Duplicate Employee Code Check
     */
    if ($employee_code !== '') {

        $check = $pdo->prepare("
            SELECT id
            FROM employees
            WHERE employee_code = ?
            LIMIT 1
        ");

        $check->execute([$employee_code]);

        if ($check->fetch()) {
            $errors[] = "This employee code already exists.";
        }
    }

    /*
     * Insert Employee and Record Audit Activity
     */
    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO employees (
                    employee_code,
                    first_name,
                    last_name,
                    email,
                    phone,
                    designation,
                    joining_date,
                    employment_status,
                    base_salary
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $employee_code,
                $first_name,
                $last_name,
                $email !== '' ? $email : null,
                $phone,
                $designation,
                $joining_date,
                $employment_status,
                (float)$base_salary
            ]);

            logActivity(
                $pdo,
                "Added employee: " . $employee_code .
                " - " . $first_name . " " . $last_name
            );

            $pdo->commit();

            header("Location: index.php?success=1");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $errors[] = "Employee could not be saved. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Add Employee | HR Payroll System</title>

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

        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 600;
        }

        .required {
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

            <h1 class="page-title">
                Add Employee
            </h1>

            <p class="text-muted mb-0">
                Create a new employee record
            </p>

        </div>

    </div>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <strong>Please fix the following:</strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?php echo htmlspecialchars($error); ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>

    <div class="form-card">

        <form method="POST" action="add.php">

            <div class="row">

                <!-- Employee Code -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Employee Code
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="employee_code"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['employee_code'] ?? ''); ?>"
                        required
                    >

                </div>

                <!-- Designation -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Designation
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="designation"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>"
                        pattern="[A-Za-z ]+"
                        title="Designation can contain alphabets and spaces only."
                        required
                    >

                </div>

                <!-- First Name -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        First Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                        pattern="[A-Za-z ]+"
                        title="First name can contain alphabets and spaces only."
                        required
                    >

                </div>

                <!-- Last Name -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Last Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="last_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                        pattern="[A-Za-z ]+"
                        title="Last name can contain alphabets and spaces only."
                        required
                    >

                </div>

                <!-- Email -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >

                </div>

                <!-- Phone -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Phone
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                        pattern="[0-9]{11}"
                        minlength="11"
                        maxlength="11"
                        inputmode="numeric"
                        title="Phone number must contain exactly 11 digits."
                        required
                    >

                </div>

                <!-- Joining Date -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Joining Date
                        <span class="required">*</span>
                    </label>

                    <input
                        type="date"
                        name="joining_date"
                        class="form-control"
                        value="<?php echo htmlspecialchars($_POST['joining_date'] ?? date('Y-m-d')); ?>"
                        min="<?php echo date('Y-m-d'); ?>"
                        required
                    >

                </div>

                <!-- Employment Status -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Employment Status
                        <span class="required">*</span>
                    </label>

                    <select
                        name="employment_status"
                        class="form-select"
                        required
                    >

                        <option value="Active"
                            <?php echo ($_POST['employment_status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>
                            Active
                        </option>

                        <option value="Inactive"
                            <?php echo ($_POST['employment_status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>
                            Inactive
                        </option>

                        <option value="Terminated"
                            <?php echo ($_POST['employment_status'] ?? '') === 'Terminated' ? 'selected' : ''; ?>>
                            Terminated
                        </option>

                    </select>

                </div>

                <!-- Base Salary -->
                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Base Salary
                        <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        name="base_salary"
                        class="form-control"
                        step="0.01"
                        min="0"
                        value="<?php echo htmlspecialchars($_POST['base_salary'] ?? ''); ?>"
                        required
                    >

                </div>

            </div>

            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary">
                    Save Employee
                </button>

                <a
                    href="index.php"
                    class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>
