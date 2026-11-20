<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    http_response_code(400);
    exit("Invalid month selected.");
}

$monthStart = $month . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$autoload = __DIR__ . '/../../vendor/autoload.php';

if (!file_exists($autoload)) {
    exit("PDF library not installed.");
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

try {

    /*
    |--------------------------------------------------------------------------
    | Payroll settings
    |--------------------------------------------------------------------------
    */

    $daysPerMonth = 30;
    $workingHoursPerDay = 8;
    $defaultOvertimeMultiplier = 1.5;

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

    $settings = $settingsStmt->fetchAll();

    foreach ($settings as $setting) {

        if ($setting['setting_key'] === 'payroll_days_per_month') {

            if (
                is_numeric($setting['setting_value']) &&
                (float)$setting['setting_value'] > 0
            ) {
                $daysPerMonth = (float)$setting['setting_value'];
            }
        }

        if ($setting['setting_key'] === 'working_hours_per_day') {

            if (
                is_numeric($setting['setting_value']) &&
                (float)$setting['setting_value'] > 0
            ) {
                $workingHoursPerDay = (float)$setting['setting_value'];
            }
        }

        if ($setting['setting_key'] === 'default_overtime_multiplier') {

            if (
                is_numeric($setting['setting_value']) &&
                (float)$setting['setting_value'] > 0
            ) {
                $defaultOvertimeMultiplier =
                    (float)$setting['setting_value'];
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Payroll records + payment totals
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.employee_id,
            p.month_year,
            p.gross_salary,
            p.net_salary,
            p.approval_status,

            e.employee_code,
            CONCAT(
                COALESCE(e.first_name, ''),
                ' ',
                COALESCE(e.last_name, '')
            ) AS employee_name,
            e.designation,
            e.employment_status,
            e.base_salary,

            COALESCE(pay.total_paid, 0) AS total_paid

        FROM payroll_management p

        LEFT JOIN employees e
            ON e.id = p.employee_id

        LEFT JOIN (
            SELECT
                payroll_id,
                SUM(amount) AS total_paid
            FROM payroll_payments
            GROUP BY payroll_id
        ) pay
            ON pay.payroll_id = p.id

        WHERE p.month_year = ?

        ORDER BY e.employee_code ASC
    ");

    $stmt->execute([$month]);

    $records = $stmt->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Totals
    |--------------------------------------------------------------------------
    */

    $totalEmployees = count($records);

    $totalBasic = 0;
    $totalOvertime = 0;
    $totalSunday = 0;
    $totalBonus = 0;
    $totalDeductions = 0;

    $totalGross = 0;
    $totalNet = 0;
    $totalPaid = 0;
    $totalRemaining = 0;

    /*
    |--------------------------------------------------------------------------
    | Calculate component breakdown
    |--------------------------------------------------------------------------
    */

    foreach ($records as &$record) {

        $employeeId = (int)$record['employee_id'];

        $basicSalary = (float)$record['base_salary'];

        /*
        |--------------------------------------------------------------------------
        | Overtime
        |--------------------------------------------------------------------------
        */

        $overtimeStmt = $pdo->prepare("
            SELECT
                COALESCE(
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

        $overtimeStmt->execute([
            $defaultOvertimeMultiplier,
            $employeeId,
            $monthStart,
            $monthEnd
        ]);

        $overtimeHoursWeighted =
            (float)$overtimeStmt->fetchColumn();

        $hourlyRate = 0;

        if (
            $daysPerMonth > 0 &&
            $workingHoursPerDay > 0
        ) {
            $hourlyRate =
                $basicSalary /
                $daysPerMonth /
                $workingHoursPerDay;
        }

        $overtimeAmount =
            $overtimeHoursWeighted * $hourlyRate;

        /*
        |--------------------------------------------------------------------------
        | Sunday allowance
        |--------------------------------------------------------------------------
        */

        $sundayStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM sunday_allowance_mgmt
            WHERE employee_id = ?
            AND date BETWEEN ? AND ?
        ");

        $sundayStmt->execute([
            $employeeId,
            $monthStart,
            $monthEnd
        ]);

        $sundayAllowance =
            (float)$sundayStmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | Bonus
        |--------------------------------------------------------------------------
        */

        $bonusStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM bonus_management
            WHERE employee_id = ?
            AND date BETWEEN ? AND ?
        ");

        $bonusStmt->execute([
            $employeeId,
            $monthStart,
            $monthEnd
        ]);

        $bonusAmount =
            (float)$bonusStmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | Deductions
        |--------------------------------------------------------------------------
        */

        $deductionStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0)
            FROM deduction_management
            WHERE employee_id = ?
            AND date BETWEEN ? AND ?
        ");

        $deductionStmt->execute([
            $employeeId,
            $monthStart,
            $monthEnd
        ]);

        $deductionAmount =
            (float)$deductionStmt->fetchColumn();

        /*
        |--------------------------------------------------------------------------
        | Official payroll values
        |--------------------------------------------------------------------------
        */

        $grossSalary =
            (float)$record['gross_salary'];

        $netSalary =
            (float)$record['net_salary'];

        $paid =
            (float)$record['total_paid'];

        $remaining =
            max(0, $netSalary - $paid);

        /*
        |--------------------------------------------------------------------------
        | Dynamic payment status
        |--------------------------------------------------------------------------
        */

        if ($remaining <= 0 && $netSalary > 0) {

            $paymentStatus = 'Paid';

        } elseif ($paid > 0) {

            $paymentStatus = 'Partial';

        } else {

            $paymentStatus = 'Pending';
        }

        /*
        |--------------------------------------------------------------------------
        | Store calculated values
        |--------------------------------------------------------------------------
        */

        $record['basic_salary'] =
            $basicSalary;

        $record['overtime_amount'] =
            $overtimeAmount;

        $record['sunday_allowance'] =
            $sundayAllowance;

        $record['bonus_amount'] =
            $bonusAmount;

        $record['deduction_amount'] =
            $deductionAmount;

        $record['gross_salary'] =
            $grossSalary;

        $record['net_salary'] =
            $netSalary;

        $record['total_paid'] =
            $paid;

        $record['remaining_balance'] =
            $remaining;

        $record['calculated_status'] =
            $paymentStatus;

        /*
        |--------------------------------------------------------------------------
        | Totals
        |--------------------------------------------------------------------------
        */

        $totalBasic += $basicSalary;
        $totalOvertime += $overtimeAmount;
        $totalSunday += $sundayAllowance;
        $totalBonus += $bonusAmount;
        $totalDeductions += $deductionAmount;

        $totalGross += $grossSalary;
        $totalNet += $netSalary;
        $totalPaid += $paid;
        $totalRemaining += $remaining;
    }

    unset($record);

    /*
    |--------------------------------------------------------------------------
    | HTML escape helper
    |--------------------------------------------------------------------------
    */

    $escape = static function ($value) {

        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    };

    /*
    |--------------------------------------------------------------------------
    | Build PDF HTML
    |--------------------------------------------------------------------------
    */

    $html = '
    <!DOCTYPE html>
    <html>

    <head>

        <meta charset="UTF-8">

        <style>

            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 8px;
            }

            h1,
            h2 {
                text-align: center;
                margin-bottom: 5px;
            }

            .info {
                text-align: center;
                margin-bottom: 15px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }

            th {
                background: #343a40;
                color: white;
                padding: 5px 3px;
                border: 1px solid #000;
                font-size: 7px;
            }

            td {
                padding: 5px 3px;
                border: 1px solid #000;
                font-size: 7px;
            }

            .text-right {
                text-align: right;
            }

            .text-center {
                text-align: center;
            }

            .total-row {
                font-weight: bold;
                background: #eeeeee;
            }

            .summary {
                margin-top: 18px;
                line-height: 1.8;
            }

            .component-table {
                margin-top: 18px;
            }

            .footer {
                margin-top: 20px;
                font-size: 8px;
            }

        </style>

    </head>

    <body>

        <h1>
            HR & Payroll Management System
        </h1>

        <h2>
            Monthly Payroll Report
        </h2>

        <div class="info">
            <strong>Payroll Month:</strong>
            ' . $escape($month) . '
        </div>

        <!-- Component Summary -->

        <table class="component-table">

            <thead>

                <tr>

                    <th>Basic Salary</th>
                    <th>Overtime</th>
                    <th>Sunday Allowance</th>
                    <th>Bonus</th>
                    <th>Deductions</th>
                    <th>Gross Salary</th>
                    <th>Net Salary</th>
                    <th>Total Paid</th>
                    <th>Remaining</th>

                </tr>

            </thead>

            <tbody>

                <tr>

                    <td class="text-right">
                        Rs. ' . number_format($totalBasic, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalOvertime, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalSunday, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalBonus, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalDeductions, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalGross, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalNet, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalPaid, 2) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format($totalRemaining, 2) . '
                    </td>

                </tr>

            </tbody>

        </table>

        <!-- Detailed Payroll Table -->

        <table>

            <thead>

                <tr>

                    <th>#</th>
                    <th>Employee Code</th>
                    <th>Employee Name</th>
                    <th>Designation</th>
                    <th>Basic</th>
                    <th>Overtime</th>
                    <th>Sunday</th>
                    <th>Bonus</th>
                    <th>Deductions</th>
                    <th>Gross</th>
                    <th>Net</th>
                    <th>Paid</th>
                    <th>Remaining</th>
                    <th>Payment Status</th>
                    <th>Approval</th>

                </tr>

            </thead>

            <tbody>
    ';

    if (empty($records)) {

        $html .= '

            <tr>

                <td colspan="15" class="text-center">
                    No payroll records found.
                </td>

            </tr>

        ';

    } else {

        foreach ($records as $index => $record) {

            $html .= '

                <tr>

                    <td class="text-center">
                        ' . ($index + 1) . '
                    </td>

                    <td>
                        ' . $escape(
                            $record['employee_code'] ?? 'N/A'
                        ) . '
                    </td>

                    <td>
                        ' . $escape(
                            trim($record['employee_name'])
                                ?: 'N/A'
                        ) . '
                    </td>

                    <td>
                        ' . $escape(
                            $record['designation'] ?? 'N/A'
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['basic_salary'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['overtime_amount'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['sunday_allowance'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['bonus_amount'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['deduction_amount'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['gross_salary'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['net_salary'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['total_paid'],
                            2
                        ) . '
                    </td>

                    <td class="text-right">
                        Rs. ' . number_format(
                            (float)$record['remaining_balance'],
                            2
                        ) . '
                    </td>

                    <td class="text-center">
                        ' . $escape(
                            $record['calculated_status']
                        ) . '
                    </td>

                    <td class="text-center">
                        ' . $escape(
                            $record['approval_status'] ?? 'Draft'
                        ) . '
                    </td>

                </tr>

            ';
        }

        $html .= '

            <tr class="total-row">

                <td colspan="4" class="text-right">
                    TOTAL
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalBasic,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalOvertime,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalSunday,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalBonus,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalDeductions,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalGross,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalNet,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalPaid,
                        2
                    ) . '
                </td>

                <td class="text-right">
                    Rs. ' . number_format(
                        $totalRemaining,
                        2
                    ) . '
                </td>

                <td colspan="2"></td>

            </tr>

        ';
    }

    $html .= '

            </tbody>

        </table>

        <div class="summary">

            <strong>Total Employees:</strong>
            ' . $totalEmployees . '
            <br>

            <strong>Total Basic Salary:</strong>
            Rs. ' . number_format(
                $totalBasic,
                2
            ) . '
            <br>

            <strong>Total Overtime:</strong>
            Rs. ' . number_format(
                $totalOvertime,
                2
            ) . '
            <br>

            <strong>Total Sunday Allowance:</strong>
            Rs. ' . number_format(
                $totalSunday,
                2
            ) . '
            <br>

            <strong>Total Bonus:</strong>
            Rs. ' . number_format(
                $totalBonus,
                2
            ) . '
            <br>

            <strong>Total Deductions:</strong>
            Rs. ' . number_format(
                $totalDeductions,
                2
            ) . '
            <br>

            <strong>Total Gross Salary:</strong>
            Rs. ' . number_format(
                $totalGross,
                2
            ) . '
            <br>

            <strong>Total Net Salary:</strong>
            Rs. ' . number_format(
                $totalNet,
                2
            ) . '
            <br>

            <strong>Total Paid:</strong>
            Rs. ' . number_format(
                $totalPaid,
                2
            ) . '
            <br>

            <strong>Remaining Balance:</strong>
            Rs. ' . number_format(
                $totalRemaining,
                2
            ) . '

        </div>

        <div class="footer">

            Generated on:
            ' . date('Y-m-d H:i:s') . '

        </div>

    </body>

    </html>
    ';

    /*
    |--------------------------------------------------------------------------
    | Generate PDF
    |--------------------------------------------------------------------------
    */

    $options = new Options();

    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);

    $dompdf->loadHtml($html);

    $dompdf->setPaper('A4', 'landscape');

    $dompdf->render();

    $filename =
        'Payroll_Report_' . $month . '.pdf';

    $dompdf->stream(
        $filename,
        [
            'Attachment' => true
        ]
    );

    exit;

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    exit(
        "PDF report could not be generated. Please check the PHP error log."
    );
}