<?php
/**
 * Payroll attendance rules: Sundays as separate category, working-day-based LOP when Sundays are unpaid.
 */

function payroll_sundays_are_paid(array $settings): bool {
    if (!array_key_exists('sunday_is_paid_day', $settings)) {
        return true;
    }
    $v = strtolower(trim((string)$settings['sunday_is_paid_day']));
    return !in_array($v, ['0', 'false', 'no', ''], true);
}

function payroll_count_sundays_in_month(int $month, int $year): int {
    $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $count = 0;
    for ($d = 1; $d <= $days; $d++) {
        if ((int)date('w', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d))) === 0) {
            $count++;
        }
    }
    return $count;
}

function payroll_normalize_punch_date(string $raw): string {
    if (strpos($raw, ' ') !== false) {
        return substr($raw, 0, 10);
    }
    return $raw;
}

function payroll_saturday_is_unpaid(int $dayOfMonth, string $saturdayRule): bool {
    if ($saturdayRule === 'never_paid') {
        return true;
    }
    if ($saturdayRule === 'alternate_paid') {
        $week = (int)ceil($dayOfMonth / 7);
        // 1st, 3rd, 5th Saturdays = working; 2nd & 4th = paid weekly off
        return ($week !== 2 && $week !== 4);
    }
    return false;
}

/** @deprecated Use payroll_saturday_is_unpaid; kept for callers passing sunday flag */
function payroll_weekend_is_unpaid(int $dayOfWeek, int $dayOfMonth, bool $sundayPaid, string $saturdayRule): bool {
    if ($dayOfWeek === 0 && !$sundayPaid) {
        return true;
    }
    if ($dayOfWeek === 6) {
        return payroll_saturday_is_unpaid($dayOfMonth, $saturdayRule);
    }
    return false;
}

/**
 * Apply a single attendance_logs status to paid-day and summary counters.
 */
function payroll_apply_day_record(
    string $status,
    float &$paid_days,
    int &$present,
    int &$absent,
    int &$late,
    int &$late_count,
    float $late_percent,
    int $late_grace_count,
    float $half_day_percent,
    int $holidays_paid,
    int &$leave
): void {
    $s = strtolower($status);

    if (strpos($s, 'present') !== false) {
        $paid_days += 1.0;
        $present++;
        return;
    }
    if (strpos($s, 'late') !== false && strpos($s, 'absent') === false) {
        $late_count++;
        $late++;
        $present++;
        $paid_days += ($late_count <= $late_grace_count) ? 1.0 : $late_percent;
        return;
    }
    if (strpos($s, 'half') !== false) {
        $paid_days += $half_day_percent;
        $present++;
        return;
    }
    if (strpos($s, 'holiday') !== false) {
        if ($holidays_paid) {
            $paid_days += 1.0;
        }
        $leave++;
        return;
    }
    if (strpos($s, 'absent') !== false || strpos($s, 'late-absent') !== false) {
        $absent++;
        return;
    }

    $paid_days += 1.0;
    $leave++;
}

/**
 * @param array<string,string> $records punch_date => status (lowercase)
 * @param array<string,int> $approved_leaves date => is_paid (0|1)
 * @param array<string,string> $holidays date => reason
 * @param array<string,string> $hr_settings
 */
function payroll_calculate_attendance_metrics(
    int $month,
    int $year,
    array $records,
    array $approved_leaves,
    array $holidays,
    array $hr_settings,
    ?string $today = null
): array {
    $today = $today ?? date('Y-m-d');
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $sundays_are_paid = payroll_sundays_are_paid($hr_settings);
    $sunday_count = payroll_count_sundays_in_month($month, $year);
    $working_days = $days_in_month - $sunday_count;
    $holiday_sundays_paid = 0;
    if (!$sundays_are_paid && !empty($holidays)) {
        foreach ($holidays as $hDate => $_reason) {
            if (!is_string($hDate) || strlen($hDate) < 10) continue;
            // Holiday on excluded Sunday should still be paid; add it back into payable base.
            if (substr($hDate, 0, 7) === sprintf('%04d-%02d', $year, $month) && (int)date('w', strtotime($hDate)) === 0) {
                $holiday_sundays_paid++;
            }
        }
    }
    $pay_denominator = $sundays_are_paid ? $days_in_month : ($working_days + $holiday_sundays_paid);

    $late_percent = (float)($hr_settings['late_payment_percent'] ?? 100) / 100;
    $saturday_rule = $hr_settings['saturday_rule'] ?? 'always_paid';
    $half_day_percent = (float)($hr_settings['half_day_payment_percent'] ?? 50) / 100;
    $lwp_percent = (float)($hr_settings['lwp_payment_percent'] ?? 0) / 100;
    $holidays_paid = (int)($hr_settings['holidays_are_paid'] ?? 1);
    $late_grace_count = (int)($hr_settings['late_grace_count'] ?? 0);

    $paid_days = 0.0;
    $present = 0;
    $absent = 0;
    $late = 0;
    $leave = 0;
    $late_count = 0;
    $holiday_count = 0;

    for ($d = 1; $d <= $days_in_month; $d++) {
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $day_of_week = (int)date('w', strtotime($date_str));

        // Company holiday overrides everything (holiday wins)
        if (isset($holidays[$date_str])) {
            $paid_days += 1.0;
            $holiday_count++;
            $leave++;
            continue;
        }

        // Actual punch records always count toward paid days (even on unpaid Sat/Sun weekly off)
        if (isset($records[$date_str])) {
            payroll_apply_day_record(
                $records[$date_str],
                $paid_days,
                $present,
                $absent,
                $late,
                $late_count,
                $late_percent,
                $late_grace_count,
                $half_day_percent,
                $holidays_paid,
                $leave
            );
            continue;
        }

        // Unpaid-Sunday mode: Sundays without attendance are outside payroll
        if ($day_of_week === 0 && !$sundays_are_paid) {
            continue;
        }

        // Unpaid Saturday without attendance
        if ($day_of_week === 6 && $date_str <= $today && payroll_saturday_is_unpaid($d, $saturday_rule)) {
            $absent++;
            continue;
        }

        if (isset($approved_leaves[$date_str])) {
            $paid_days += ($approved_leaves[$date_str] === 1) ? 1.0 : $lwp_percent;
            $leave++;
        } elseif ($day_of_week === 0 && $date_str <= $today && $sundays_are_paid) {
            $paid_days += 1.0;
            $leave++;
        } elseif ($day_of_week === 6 && $date_str <= $today) {
            if ($saturday_rule === 'always_paid') {
                $paid_days += 1.0;
                $leave++;
            } elseif ($saturday_rule === 'alternate_paid') {
                $week_of_month = (int)ceil($d / 7);
                if ($week_of_month === 2 || $week_of_month === 4) {
                    $paid_days += 1.0;
                    $leave++;
                } else {
                    $absent++;
                }
            } else {
                $absent++;
            }
        } elseif ($date_str > $today) {
            // Future days — no LOP
        } else {
            $absent++;
        }
    }

    $lops = max(0, $pay_denominator - $paid_days);

    return [
        'days_in_month' => $days_in_month,
        'sunday_count' => $sunday_count,
        'holiday_count' => $holiday_count,
        'working_days' => $working_days,
        'sundays_are_paid' => $sundays_are_paid,
        'holiday_sundays_paid' => $holiday_sundays_paid,
        'pay_denominator' => $pay_denominator,
        'paid_days' => round($paid_days, 2),
        'lops' => round($lops, 2),
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'leave' => $leave,
    ];
}
