<?php

/**
 * @description this function return range payslip by month, start date, end date
 * @param string $formLinkName
 * @return string
 */
function payroll_range_date($monthly, $formDate, $toDate)
{
    ($formDate > $toDate) ? $num = 1 : $num = 0;
    $fromSalary = date('Y-m-d', strtotime($formDate . '-' . $monthly . " -" . $num . " months"));
    $toSalary = date('Y-m-d', strtotime($toDate . '-' . $monthly));
    return [$fromSalary, $toSalary];
}

/**
 * @description this function return uri path to fetch components
 * @param string $formLinkName
 * @return string
 */
function total_standard_working_day_by_working_hour($hour, $fullDayHour, $halfDayHour)
{
    $day = 0;
    if ($halfDayHour == 0 || $fullDayHour == 0) {
        return $day; 
    }
    if (is_numeric($fullDayHour) && $hour >= $fullDayHour) {
        $day = 1;
    } elseif (is_numeric($halfDayHour) && $hour >= $halfDayHour) {
        $day = 0.5;
    }
    return $day;
}

function convert_decimal_length($number, $length = 2)
{
    if (!is_numeric($number)) $number = 0;

    return number_format($number,$length,'.','');
}

function sum_number(...$numbers)
{
    $sum = 0;
    foreach($numbers as $number) {
        if (empty($number) || !is_numeric($number)) continue;
        $sum += floatval($number);
    }
    return $sum;
}