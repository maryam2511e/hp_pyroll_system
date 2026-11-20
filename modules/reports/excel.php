
<?php
ob_start();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(400);
    exit("Invalid month selected.");
}

try {
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    // Load payroll settings
    $settingsStmt = $pdo->prepare("
        SELECT setting_key, setting_value
        FROM system_settings
        WHERE setting_key IN (
            'payroll_days_per_month',
            'working_hours_per_day',
            'default_overtime_multiplier'
        )
    ");
    $settingsStmt->execute();

    $settings = [];

    foreach ($settingsStmt->fetchAll() as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }

    $payrollDays = isset($settings['payroll_days_per_month'])
        && is_numeric($settings['payroll_days_per_month'])
        && (float)$settings['payroll_days_per_month'] > 0
        ? (float)$settings['payroll_days_per_month'] : 30;

    $workingHours = isset($settings['working_hours_per_day'])
        && is_numeric($settings['working_hours_per_day'])
        && (float)$settings['working_hours_per_day'] > 0
        ? (float)$settings['working_hours_per_day'] : 8;

    $defaultMultiplier = isset($settings['default_overtime_multiplier'])
        && is_numeric($settings['default_overtime_multiplier'])
        && (float)$settings['default_overtime_multiplier'] > 0
        ? (float)$settings['default_overtime_multiplier'] : 1.5;

    // Load payroll records
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.employee_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.designation,
            e.base_salary,
            p.month_year,
            p.gross_salary,
            p.net_salary,
            p.approval_status,
            COALESCE(pay.total_paid, 0) AS total_paid
        FROM payroll_management p
        LEFT JOIN employees e ON e.id = p.employee_id
        LEFT JOIN (
            SELECT payroll_id, SUM(amount) AS total_paid
            FROM payroll_payments
            GROUP BY payroll_id
        ) pay ON pay.payroll_id = p.id
        WHERE p.month_year = ?
        ORDER BY e.employee_code ASC
    ");

    $stmt->execute([$month]);
    $records = $stmt->fetchAll();

    // Prepare component queries
    $overtimeStmt = $pdo->prepare("
        SELECT COALESCE(
            SUM(
                hours *
                CASE
                    WHEN multiplier IS NULL OR multiplier <= 0
                    THEN ?
                    ELSE multiplier
                END
            ),
            0
        )
        FROM overtime_management
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $sundayStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM sunday_allowance_mgmt
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $bonusStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM bonus_management
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $deductionStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM deduction_management
        WHERE employee_id = ?
        AND date BETWEEN ? AND ?
    ");

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setTitle('Payroll Report');

    // Report title
    $sheet->setCellValue('A1', 'HR & Payroll Management System');
    $sheet->setCellValue('A2', 'Payroll Report - ' . $month);

    $sheet->mergeCells('A1:P1');
    $sheet->mergeCells('A2:P2');

    $sheet->getStyle('A1:P2')->getFont()->setBold(true);
    $sheet->getStyle('A1')->getFont()->setSize(16);
    $sheet->getStyle('A2')->getFont()->setSize(13);

    $sheet->getStyle('A1:P2')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Column headings
    $headers = [
        'Payroll ID',
        'Employee Code',
        'Employee Name',
        'Designation',
        'Month',
        'Basic Salary',
        'Overtime',
        'Sunday Allowance',
        'Bonus',
        'Deductions',
        'Gross Salary',
        'Net Salary',
        'Total Paid',
        'Remaining Balance',
        'Payment Status',
        'Approval Status'
    ];

    $sheet->fromArray($headers, null, 'A4');

    $sheet->getStyle('A4:P4')->getFont()
        ->setBold(true)
        ->getColor()->setARGB('FFFFFFFF');

    $sheet->getStyle('A4:P4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF1F4E78');

    $sheet->getStyle('A4:P4')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->freezePane('A5');

    $row = 5;

    $totalBasic = 0;
    $totalOvertime = 0;
    $totalSunday = 0;
    $totalBonus = 0;
    $totalDeductions = 0;
    $totalGross = 0;
    $totalNet = 0;
    $totalPaid = 0;
    $totalRemaining = 0;

    foreach ($records as $record) {
        $employeeId = (int)$record['employee_id'];
        $basic = (float)($record['base_salary'] ?? 0);

        $name = trim(
            ($record['first_name'] ?? '') . ' ' .
            ($record['last_name'] ?? '')
        );

        // Overtime calculation
        $overtimeStmt->execute([
            $defaultMultiplier,
            $employeeId,
            $startDate,
            $endDate
        ]);

        $overtimeUnits = (float)$overtimeStmt->fetchColumn();

        $hourlyRate = $basic / $payrollDays / $workingHours;
        $overtime = $overtimeUnits * $hourlyRate;

        // Sunday allowance
        $sundayStmt->execute([
            $employeeId,
            $startDate,
            $endDate
        ]);
        $sunday = (float)$sundayStmt->fetchColumn();

        // Bonuses
        $bonusStmt->execute([
            $employeeId,
            $startDate,
            $endDate
        ]);
        $bonus = (float)$bonusStmt->fetchColumn();

        // Deductions
        $deductionStmt->execute([
            $employeeId,
            $startDate,
            $endDate
        ]);
        $deductions = (float)$deductionStmt->fetchColumn();

        // Official saved payroll amounts
        $gross = (float)$record['gross_salary'];
        $net = (float)$record['net_salary'];

        // Payment calculation
        $paid = (float)$record['total_paid'];
        $remaining = max(0, $net - $paid);

        if ($remaining <= 0) {
            $paymentStatus = 'Paid';
        } elseif ($paid > 0) {
            $paymentStatus = 'Partial';
        } else {
            $paymentStatus = 'Pending';
        }

        // Update totals
        $totalBasic += $basic;
        $totalOvertime += $overtime;
        $totalSunday += $sunday;
        $totalBonus += $bonus;
        $totalDeductions += $deductions;
        $totalGross += $gross;
        $totalNet += $net;
        $totalPaid += $paid;
        $totalRemaining += $remaining;

        // Export row
        $sheet->fromArray([
            $record['id'],
            $record['employee_code'] ?? 'N/A',
            $name ?: 'N/A',
            $record['designation'] ?? 'N/A',
            $record['month_year'],
            $basic,
            $overtime,
            $sunday,
            $bonus,
            $deductions,
            $gross,
            $net,
            $paid,
            $remaining,
            $paymentStatus,
            $record['approval_status'] ?? 'Draft'
        ], null, 'A' . $row);

        $row++;
    }

    // Totals row
    $sheet->setCellValue('E' . $row, 'TOTAL');

    $sheet->setCellValue('F' . $row, $totalBasic);
    $sheet->setCellValue('G' . $row, $totalOvertime);
    $sheet->setCellValue('H' . $row, $totalSunday);
    $sheet->setCellValue('I' . $row, $totalBonus);
    $sheet->setCellValue('J' . $row, $totalDeductions);
    $sheet->setCellValue('K' . $row, $totalGross);
    $sheet->setCellValue('L' . $row, $totalNet);
    $sheet->setCellValue('M' . $row, $totalPaid);
    $sheet->setCellValue('N' . $row, $totalRemaining);

    $sheet->getStyle('E' . $row . ':N' . $row)
        ->getFont()->setBold(true);

    $sheet->getStyle('A' . $row . ':P' . $row)
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE2F0D9');

    // Number formatting
    if ($row > 5) {
        $sheet->getStyle('F5:N' . $row)
            ->getNumberFormat()
            ->setFormatCode('#,##0.00');

        // Correct AutoFilter method
        $sheet->setAutoFilter('A4:P' . ($row - 1));
    }

    // Auto column width
    foreach (range('A', 'P') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Prepare Excel download
    $filename = 'Payroll_Report_' . $month . '.xlsx';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    $spreadsheet->disconnectWorksheets();
    exit;

} catch (Throwable $e) {
    error_log($e->getMessage());

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    exit("Excel report could not be generated. Please check the PHP error log.");
}
