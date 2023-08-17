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
function total_standard_working_day_by_working_hour($hour, $rules)
{
    $day = 0;
    $fullDay = !empty($rules['standard_working_hour']) ? doubleval($rules['standard_working_hour']) : 0;
    $halfDay = !empty($rules['standard_working_hour_haftday']) ? doubleval($rules['standard_working_hour_haftday']) : 0;
    if ($halfDay == 0 || $fullDay == 0) {
        return $day; 
    }

    if ($hour >= $fullDay) {
        $day = 1;
    } elseif ($hour >= $halfDay) {
        $day = 0.5;
    }
    
    return $day;
}

function convert_decimal_length($number, $length = 2)
{
    if (!is_numeric($number)) $number = 0;

    return number_format($number,$length,'.','');
}

function sum_number(...$vars)
{
    $sum = 0;

    foreach($vars as $var) {
        if (empty($var) || !is_numeric($var)) continue;
        $sum += floatval($var);
    }

    return $sum;
}