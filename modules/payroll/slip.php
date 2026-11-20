<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$payrollId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payrollId || $payrollId <= 0) {
    die("Invalid payroll ID.");
}

// Helper function
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get payroll + employee
$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.employee_id,
        p.month_year,
        p.gross_salary,
        p.net_salary,
        p.payment_status,
        p.approval_status,
        p.approved_at,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.designation,
        e.joining_date,
        e.email,
        e.phone,
        e.base_salary
    FROM payroll_management p
    LEFT JOIN employees e ON e.id = p.employee_id
    WHERE p.id = ?
");

$stmt->execute([$payrollId]);
$payroll = $stmt->fetch();

if (!$payroll) {
    die("Payroll record not found.");
}

$employeeId = (int)$payroll['employee_id'];

$monthYear = $payroll['month_year'];

$monthStart = $monthYear . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

// --------------------------------------------------
// Load payroll settings
// --------------------------------------------------

function getPayrollSetting($pdo, $key, $default)
{
    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    $stmt->execute([$key]);

    $value = $stmt->fetchColumn();

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

$payrollDays = (float)getPayrollSetting(
    $pdo,
    'payroll_days_per_month',
    30
);

$workingHoursPerDay = (float)getPayrollSetting(
    $pdo,
    'working_hours_per_day',
    8
);

$defaultOvertimeMultiplier = (float)getPayrollSetting(
    $pdo,
    'default_overtime_multiplier',
    1.5
);

if ($payrollDays <= 0) {
    $payrollDays = 30;
}

if ($workingHoursPerDay <= 0) {
    $workingHoursPerDay = 8;
}

if ($defaultOvertimeMultiplier <= 0) {
    $defaultOvertimeMultiplier = 1.5;
}

// --------------------------------------------------
// Basic salary
// --------------------------------------------------

$baseSalary = (float)$payroll['base_salary'];

// --------------------------------------------------
// Overtime
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            hours *
            CASE
                WHEN multiplier IS NULL OR multiplier <= 0
                THEN ?
                ELSE multiplier
            END
        ), 0) AS overtime_amount
    FROM overtime_management
    WHERE employee_id = ?
      AND date BETWEEN ? AND ?
");

$stmt->execute([
    $defaultOvertimeMultiplier,
    $employeeId,
    $monthStart,
    $monthEnd
]);

$overtimeAmount = (float)$stmt->fetchColumn();

// --------------------------------------------------
// Sunday Allowance
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM sunday_allowance_mgmt
    WHERE employee_id = ?
      AND date BETWEEN ? AND ?
");

$stmt->execute([
    $employeeId,
    $monthStart,
    $monthEnd
]);

$sundayAllowance = (float)$stmt->fetchColumn();

// --------------------------------------------------
// Bonus
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM bonus_management
    WHERE employee_id = ?
      AND date BETWEEN ? AND ?
");

$stmt->execute([
    $employeeId,
    $monthStart,
    $monthEnd
]);

$bonusAmount = (float)$stmt->fetchColumn();

// --------------------------------------------------
// Deductions
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM deduction_management
    WHERE employee_id = ?
      AND date BETWEEN ? AND ?
");

$stmt->execute([
    $employeeId,
    $monthStart,
    $monthEnd
]);

$deductionAmount = (float)$stmt->fetchColumn();

// --------------------------------------------------
// Calculate component totals
// --------------------------------------------------

$calculatedGross = round(
    $baseSalary +
    $overtimeAmount +
    $sundayAllowance +
    $bonusAmount,
    2
);

$calculatedNet = max(
    0,
    round($calculatedGross - $deductionAmount, 2)
);

// Use saved payroll values as the official values.
// Component breakdown is shown separately.
$grossSalary = (float)$payroll['gross_salary'];
$netSalary = (float)$payroll['net_salary'];

// --------------------------------------------------
// Total payments
// --------------------------------------------------

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payroll_payments
    WHERE payroll_id = ?
");

$stmt->execute([$payrollId]);

$totalPaid = (float)$stmt->fetchColumn();

$remaining = max(
    0,
    round($netSalary - $totalPaid, 2)
);

// --------------------------------------------------
// Dynamic payment status
// --------------------------------------------------

if ($remaining <= 0 && $netSalary > 0) {
    $paymentStatus = 'Paid';
} elseif ($totalPaid > 0) {
    $paymentStatus = 'Partial';
} else {
    $paymentStatus = 'Pending';
}

$employeeName = trim(
    ($payroll['first_name'] ?? '') . ' ' .
    ($payroll['last_name'] ?? '')
);

// --------------------------------------------------
// Build PDF HTML
// --------------------------------------------------

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>

body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    color: #333;
}

.header {
    text-align: center;
    border-bottom: 2px solid #333;
    padding-bottom: 15px;
    margin-bottom: 25px;
}

h1 {
    margin-bottom: 5px;
}

h3 {
    margin-top: 22px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
}

th,
td {
    border: 1px solid #ccc;
    padding: 9px;
    text-align: left;
}

th {
    background: #f2f2f2;
}

.amount {
    text-align: right;
}

.total {
    font-weight: bold;
    background: #e8f0fe;
}

.net {
    font-weight: bold;
    background: #dff0d8;
}

.remaining {
    font-weight: bold;
    background: #fff3cd;
}

.status {
    margin-top: 20px;
    padding: 12px;
    background: #f2f2f2;
    border: 1px solid #ddd;
}

.footer {
    margin-top: 35px;
    text-align: center;
    font-size: 10px;
    color: #777;
}

.note {
    margin-top: 10px;
    font-size: 10px;
    color: #777;
}

</style>

</head>

<body>

<div class="header">

    <h1>Salary Slip</h1>

    <p>HR & Payroll Management System</p>

    <h3>
        Salary Month: ' . h($payroll['month_year']) . '
    </h3>

</div>

<h3>Employee Information</h3>

<table>

<tr>
    <th>Employee Code</th>
    <td>' . h($payroll['employee_code'] ?? 'N/A') . '</td>
</tr>

<tr>
    <th>Employee Name</th>
    <td>' . h($employeeName) . '</td>
</tr>

<tr>
    <th>Designation</th>
    <td>' . h($payroll['designation'] ?? 'N/A') . '</td>
</tr>

<tr>
    <th>Joining Date</th>
    <td>' . h($payroll['joining_date'] ?? 'N/A') . '</td>
</tr>

</table>

<h3>Salary Breakdown</h3>

<table>

<tr>
    <th>Description</th>
    <th class="amount">Amount</th>
</tr>

<tr>
    <td>Basic Salary</td>
    <td class="amount">
        ' . number_format($baseSalary, 2) . '
    </td>
</tr>

<tr>
    <td>Overtime</td>
    <td class="amount">
        ' . number_format($overtimeAmount, 2) . '
    </td>
</tr>

<tr>
    <td>Sunday Allowance</td>
    <td class="amount">
        ' . number_format($sundayAllowance, 2) . '
    </td>
</tr>

<tr>
    <td>Bonus</td>
    <td class="amount">
        ' . number_format($bonusAmount, 2) . '
    </td>
</tr>

<tr class="total">
    <td>Gross Salary</td>
    <td class="amount">
        ' . number_format($grossSalary, 2) . '
    </td>
</tr>

<tr>
    <td>Deductions</td>
    <td class="amount">
        - ' . number_format($deductionAmount, 2) . '
    </td>
</tr>

<tr class="net">
    <td>Net Salary</td>
    <td class="amount">
        ' . number_format($netSalary, 2) . '
    </td>
</tr>

</table>

<div class="note">
    Payroll calculated using current system settings.
</div>

<h3>Payment Summary</h3>

<table>

<tr>
    <th>Description</th>
    <th class="amount">Amount</th>
</tr>

<tr>
    <td>Total Paid</td>
    <td class="amount">
        ' . number_format($totalPaid, 2) . '
    </td>
</tr>

<tr class="remaining">
    <td>Remaining Balance</td>
    <td class="amount">
        ' . number_format($remaining, 2) . '
    </td>
</tr>

</table>

<div class="status">

<strong>Payment Status:</strong>
' . h($paymentStatus) . '
<br><br>

<strong>Approval Status:</strong>
' . h($payroll['approval_status'] ?? 'Draft') . '
<br><br>

<strong>Approved At:</strong>
' . h($payroll['approved_at'] ?? 'Not approved') . '

</div>

<div class="footer">

    This is a computer-generated salary slip.

</div>

</body>
</html>
';

// --------------------------------------------------
// Generate PDF
// --------------------------------------------------

$options = new Options();

$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$dompdf->stream(
    'Salary_Slip_' . $payrollId . '.pdf',
    ['Attachment' => true]
);

exit;