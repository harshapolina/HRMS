<?php
/**
 * Shared payslip payload builder — mirrors hrmain.js openPayslipGeneratorModal + calculatePayslip.
 * Used by Run Payroll (bulk) so each employee gets a user_payslips record like Generate Payslip.
 */

function payroll_user_has_manual_structure(array $user): bool
{
    $keys = ['one_amt', 'two_amt', 'thrid_amt', 'forth_amt', 'fifth_amt', 'sixth_amt'];
    foreach ($keys as $key) {
        if (!empty($user[$key]) && (float)$user[$key] > 0) {
            return true;
        }
    }
    return false;
}

/**
 * @return array{basic:float,hra:float,conveyance:float,special:float,bonus:float,pf:float,pt:float,medical:float,custom:float}
 */
function payroll_compute_salary_components(array $user): array
{
    $monthlyCTC = (float)($user['salary'] ?? 0);
    $hasManual = payroll_user_has_manual_structure($user);

    $basic = $hasManual ? (float)($user['one_amt'] ?? 0) : round($monthlyCTC * 0.5);
    $hra = $hasManual ? (float)($user['two_amt'] ?? 0) : round($monthlyCTC * 0.2);
    $conveyance = $hasManual ? (float)($user['thrid_amt'] ?? 0) : round($monthlyCTC * 0.07);
    $pfEmployer = $hasManual ? (float)($user['fifth_amt'] ?? 0) : min(1800, round($basic * 0.12));
    $monthlyGross = $monthlyCTC - $pfEmployer;
    $special = $hasManual ? (float)($user['forth_amt'] ?? 0) : ($monthlyGross - ($basic + $hra + $conveyance));

    $totalDeds = $hasManual ? (float)($user['sixth_amt'] ?? 0) : ($pfEmployer + 200 + 817);
    $baseSplit = $pfEmployer + 200 + 817;
    $custom = max(0, $totalDeds - $baseSplit);

    return [
        'basic' => $basic,
        'hra' => $hra,
        'conveyance' => $conveyance,
        'special' => $special,
        'bonus' => 0,
        'pf' => $pfEmployer,
        'pt' => 200,
        'medical' => 817,
        'custom' => $custom,
    ];
}

/**
 * Build payroll row + user_payslips JSON from employee account and attendance metrics.
 *
 * @return array{payroll:array,user_payslip:array}|null
 */
function payroll_build_auto_payslip(array $user, array $metrics, string $monthYear): ?array
{
    $dateObj = DateTime::createFromFormat('M Y', $monthYear);
    if (!$dateObj) {
        return null;
    }

    $month = (int)$dateObj->format('n');
    $year = (int)$dateObj->format('Y');
    $components = payroll_compute_salary_components($user);

    $payBase = (float)($metrics['pay_denominator'] ?? 0);
    $paidDays = round((float)($metrics['paid_days'] ?? 0), 2);
    $lops = round((float)($metrics['lops'] ?? max(0, $payBase - $paidDays)), 2);

    $grossBase = $components['basic'] + $components['hra'] + $components['conveyance'] + $components['special'];
    $lopAmount = $payBase > 0 ? (int)round($grossBase * ($lops / $payBase)) : 0;

    $totalDeductions = $components['pf'] + $components['pt'] + $components['medical'] + $components['custom'] + $lopAmount;
    $netPay = round($grossBase + $components['bonus'] - $totalDeductions, 2);

    $payslipData = [
        'month' => $month,
        'year' => $year,
        'source' => 'run_payroll',
        'calendar_days' => (int)($metrics['days_in_month'] ?? 30),
        'sunday_count' => (int)($metrics['sunday_count'] ?? 0),
        'working_days' => (int)($metrics['working_days'] ?? 0),
        'sundays_are_paid' => !empty($metrics['sundays_are_paid']),
        'pay_denominator' => $payBase,
        'total_days' => $payBase,
        'paid_days' => $paidDays,
        'lops' => $lops,
        'lop_amount' => $lopAmount,
        'earnings' => [
            'basic' => $components['basic'],
            'hra' => $components['hra'],
            'conveyance' => $components['conveyance'],
            'special' => $components['special'],
            'bonus' => $components['bonus'],
        ],
        'deductions' => [
            'pf' => $components['pf'],
            'pt' => $components['pt'],
            'medical' => $components['medical'],
            'custom' => $components['custom'],
        ],
        'net_pay' => $netPay,
    ];

    return [
        'payroll' => [
            'eid' => $user['id'],
            'ename' => $user['username'],
            'month' => $monthYear,
            'base' => $grossBase,
            'present' => $paidDays,
            'total' => $payBase,
            'deductions' => $totalDeductions,
            'net' => $netPay,
            'status' => 'Processed',
        ],
        'user_payslip' => [
            'user_id' => (int)$user['id'],
            'month' => $month,
            'year' => $year,
            'net_pay' => $netPay,
            'payslip_data' => $payslipData,
        ],
    ];
}

/**
 * @return array{0:int,1:int}
 */
function payroll_parse_month_year(string $monthYear): array
{
    $dateObj = DateTime::createFromFormat('M Y', trim($monthYear));
    if (!$dateObj) {
        return [0, 0];
    }
    return [(int)$dateObj->format('n'), (int)$dateObj->format('Y')];
}

/**
 * Salary components from a payroll row (when user_payslips JSON is unavailable).
 *
 * @return array{basic:float,hra:float,conveyance:float,special:float,bonus:float,pf:float,pt:float,medical:float,custom:float}
 */
function payroll_components_from_payroll_row(array $row): array
{
    $hasManual = payroll_user_has_manual_structure($row);
    $monthlyGross = (float)($row['base_salary'] ?? 0);
    $bonus = (float)($row['incentives'] ?? 0);

    if ($hasManual) {
        return [
            'basic' => (float)($row['one_amt'] ?? 0),
            'hra' => (float)($row['two_amt'] ?? 0),
            'conveyance' => (float)($row['thrid_amt'] ?? 0),
            'special' => (float)($row['forth_amt'] ?? 0),
            'bonus' => $bonus,
            'pf' => (float)($row['fifth_amt'] ?? 0),
            'pt' => 0,
            'medical' => 0,
            'custom' => (float)($row['sixth_amt'] ?? 0),
        ];
    }

    $basic = round($monthlyGross * 0.50);
    $hra = round($monthlyGross * 0.20);
    $conveyance = round($monthlyGross * 0.07);
    $special = $monthlyGross - ($basic + $hra + $conveyance);

    return [
        'basic' => $basic,
        'hra' => $hra,
        'conveyance' => $conveyance,
        'special' => $special,
        'bonus' => $bonus,
        'pf' => 1800,
        'pt' => 200,
        'medical' => 817,
        'custom' => 0,
    ];
}

/**
 * Unified payslip view model — matches User360 preview (full earnings + explicit LOP).
 */
function payroll_resolve_payslip_display(array $row, ?array $payslipJson = null): array
{
    $employeeName = (string)($row['employee_name'] ?? '');
    $designation = (string)($row['designation'] ?? 'Employee');
    $monthYear = (string)($row['month_year'] ?? '');
    $empCode = (string)($row['emp_code'] ?? $row['employee_id'] ?? '');

    if (is_array($payslipJson) && isset($payslipJson['earnings'])) {
        $e = $payslipJson['earnings'];
        $d = $payslipJson['deductions'] ?? [];
        $basic = (float)($e['basic'] ?? 0);
        $hra = (float)($e['hra'] ?? 0);
        $conveyance = (float)($e['conveyance'] ?? 0);
        $special = (float)($e['special'] ?? 0);
        $bonus = (float)($e['bonus'] ?? 0);
        $pf = (float)($d['pf'] ?? 0);
        $pt = (float)($d['pt'] ?? 0);
        $medical = (float)($d['medical'] ?? 0);
        $custom = (float)($d['custom'] ?? 0);
        $lopAmount = (float)($payslipJson['lop_amount'] ?? 0);
        $lops = (float)($payslipJson['lops'] ?? 0);
        $payDenom = (float)($payslipJson['pay_denominator'] ?? $payslipJson['total_days'] ?? $row['total_days'] ?? 30);
        $totalEarnings = $basic + $hra + $conveyance + $special + $bonus;
        $totalDeductions = $pf + $pt + $medical + $custom + $lopAmount;
        $netPay = (float)($payslipJson['net_pay'] ?? $row['net_salary'] ?? ($totalEarnings - $totalDeductions));

        return [
            'month_year' => $monthYear,
            'employee_name' => $employeeName,
            'emp_code' => $empCode,
            'designation' => $designation,
            'pay_denominator' => $payDenom,
            'lops' => $lops,
            'calendar_days' => isset($payslipJson['calendar_days']) ? (int)$payslipJson['calendar_days'] : null,
            'sunday_count' => isset($payslipJson['sunday_count']) ? (int)$payslipJson['sunday_count'] : null,
            'sundays_are_paid' => !isset($payslipJson['sundays_are_paid']) || !empty($payslipJson['sundays_are_paid']),
            'earnings' => [
                'basic' => $basic,
                'hra' => $hra,
                'conveyance' => $conveyance,
                'special' => $special,
                'bonus' => $bonus,
            ],
            'deductions' => [
                'pf' => $pf,
                'pt' => $pt,
                'medical' => $medical,
                'custom' => $custom,
            ],
            'lop_amount' => $lopAmount,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ];
    }

    $c = payroll_components_from_payroll_row($row);
    $payBase = (float)($row['total_days'] ?? 30);
    $paidDays = (float)($row['present_days'] ?? 0);
    $lops = round(max(0, $payBase - $paidDays), 2);
    $grossBase = $c['basic'] + $c['hra'] + $c['conveyance'] + $c['special'];
    $lopAmount = $payBase > 0 ? (int)round($grossBase * ($lops / $payBase)) : 0;
    $totalEarnings = $grossBase + $c['bonus'];
    $totalDeductions = $c['pf'] + $c['pt'] + $c['medical'] + $c['custom'] + $lopAmount;
    $netPay = (float)($row['net_salary'] ?? round($totalEarnings - $totalDeductions, 2));

    return [
        'month_year' => $monthYear,
        'employee_name' => $employeeName,
        'emp_code' => $empCode,
        'designation' => $designation,
        'pay_denominator' => $payBase,
        'lops' => $lops,
        'calendar_days' => null,
        'sunday_count' => null,
        'sundays_are_paid' => true,
        'earnings' => [
            'basic' => $c['basic'],
            'hra' => $c['hra'],
            'conveyance' => $c['conveyance'],
            'special' => $c['special'],
            'bonus' => $c['bonus'],
        ],
        'deductions' => [
            'pf' => $c['pf'],
            'pt' => $c['pt'],
            'medical' => $c['medical'],
            'custom' => $c['custom'],
        ],
        'lop_amount' => $lopAmount,
        'total_earnings' => $totalEarnings,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay,
    ];
}
