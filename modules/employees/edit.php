```php
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
        base_salary
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
     * Only alphabets and spaces allowed.
     */
    if ($first_name === '') {

        $errors[] = "First name is required.";

    } elseif (!preg_match('/^[A-Za-z ]+$/', $first_name)) {

        $errors[] = "First name can contain alphabets and spaces only.";

    }


    /*
     * Last Name
     * Only alphabets and spaces allowed.
     */
    if ($last_name === '') {

        $errors[] = "Last name is required.";

    } elseif (!preg_match('/^[A-Za-z ]+$/', $last_name)) {

        $errors[] = "Last name can contain alphabets and spaces only.";

    }


    /*
     * Designation
     * Only alphabets and spaces allowed.
     */
    if ($designation !== '' && !preg_match('/^[A-Za-z ]+$/', $designation)) {

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
     * Exactly 11 digits.
     */
    if ($phone === '') {

        $errors[] = "Phone number is required.";

    } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {

        $errors[] = "Phone number must contain exactly 11 digits.";

    }


    /*
     * Joining Date
     * Previous dates are not allowed.
     */
    if ($joining_date === '') {

        $errors[] = "Joining date is required.";

    } else {

        $today = date('Y-m-d');

        $joining_date_timestamp = strtotime($joining_date);
        $today_timestamp = strtotime($today);

        if (
            $joining_date_timestamp === false ||
            $joining_date < $today
        ) {
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
            AND id != ?
            LIMIT 1
        ");

        $check->execute([
            $employee_code,
            $employee_id
        ]);

        if ($check->fetch()) {

            $errors[] = "This employee code is already in use.";

        }
    }


    /*
     * Update Employee
     */
    if (empty($errors)) {

        $update = $pdo->prepare("
            UPDATE employees
            SET
                employee_code = ?,
                first_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                designation = ?,
                joining_date = ?,
                employment_status = ?,
                base_salary = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $update->execute([
            $employee_code,
            $first_name,
            $last_name,
            $email !== '' ? $email : null,
            $phone,
            $designation !== '' ? $designation : null,
            $joining_date,
            $employment_status,
            (float)$base_salary,
            $employee_id
        ]);

        header("Location: view.php?id=" . $employee_id . "&updated=1");
        exit;
    }


    /*
     * Validation error ki surat mein
     * entered values form mein retain hongi.
     */
    $employee['employee_code'] = $employee_code;
    $employee['first_name'] = $first_name;
    $employee['last_name'] = $last_name;
    $employee['email'] = $email;
    $employee['phone'] = $phone;
    $employee['designation'] = $designation;
    $employee['joining_date'] = $joining_date;
    $employee['employment_status'] = $employment_status;
    $employee['base_salary'] = $base_salary;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Employee | HR Payroll System</title>

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
                Edit Employee
            </h1>

            <p class="text-muted mb-0">
                Update employee information
            </p>

        </div>


        <div>

            <a href="view.php?id=<?php echo $employee_id; ?>"
               class="btn btn-secondary">
                Cancel
            </a>

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


        <form method="POST"
              action="edit.php?id=<?php echo $employee_id; ?>">


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
                        value="<?php echo htmlspecialchars($employee['employee_code']); ?>"
                        required
                    >

                </div>


                <!-- Designation -->

                <div class="col-md-6 mb-3">

                    <label class="form-label">
                        Designation
                    </label>

                    <input
                        type="text"
                        name="designation"
                        class="form-control"
                        value="<?php echo htmlspecialchars($employee['designation'] ?? ''); ?>"
                        pattern="[A-Za-z ]+"
                        title="Designation can contain alphabets and spaces only."
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
                        value="<?php echo htmlspecialchars($employee['first_name']); ?>"
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
                        value="<?php echo htmlspecialchars($employee['last_name']); ?>"
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
                        value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"
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
                        value="<?php echo htmlspecialchars($employee['joining_date']); ?>"
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
                            <?php echo $employee['employment_status'] === 'Active' ? 'selected' : ''; ?>>
                            Active
                        </option>

                        <option value="Inactive"
                            <?php echo $employee['employment_status'] === 'Inactive' ? 'selected' : ''; ?>>
                            Inactive
                        </option>

                        <option value="Terminated"
                            <?php echo $employee['employment_status'] === 'Terminated' ? 'selected' : ''; ?>>
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
                        value="<?php echo htmlspecialchars($employee['base_salary']); ?>"
                        required
                    >

                </div>

            </div>


            <div class="mt-4">

                <button
                    type="submit"
                    class="btn btn-primary">
                    Update Employee
                </button>


                <a
                    href="view.php?id=<?php echo $employee_id; ?>"
                    class="btn btn-secondary">
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>


</body>

</html>
```