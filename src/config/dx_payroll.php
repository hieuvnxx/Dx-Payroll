<?php

return [
    'employee' => [
        'beginning_date' => 'Beginning_Date',
        'shift' => 'shift',
    ],

    'constant_configuration' => [
        'tabularSections' => [
            'shift' => [
                'no' => 'no',
                'shift_name' => 'shift',
                'from_time' => 'from_time',
                'to_time' => 'to_time',
                'standard_working_hour' => 'standard_working_hour1',
                'standard_working_hour_halfday' => 'standard_working_hour_halfday',
            ],
        ],
        'tabularNameSections' => [
            'shift' => "Quy định ca, kíp",
        ]
    ],

    'monthly_working_time' => [
        'code'                                  => 'code',
        'employee'                              => 'employee',
        'salary_period'                         => 'salary_period',
        'standard_working_time'                 => 'standard_working_time',
        'overtime_meal_allowance'               => 'ot_meal_allowance',
        'total_working_days'                    => 'total_working_days',
        'standard_working_day_probation'        => 'standard_working_day_probation',
        'standard_working_day'                  => 'standard_working_day',
        'holidays'                              => 'holiday_count',
        'paid_leave'                            => 'paid_leave',
        'total_salary_working_day'              => 'total_salary_working_day',
        'weekday_hour'                          => 'weekday1',
        'weekend_hour'                          => 'weekend1',
        'holiday_hour'                          => 'holiday_hour1',
        'weekday_night_hour'                    => 'week_night1',
        'weekend_night_hour'                    => 'weekend_night1',
        'holiday_night_hour'                    => 'holiday_night1',
        'tabularSections' => [
            'attendance_details' => [
                'date'                  => 'Date',
                'punch_in'              => 'Punch_in',
                'punch_out'             => 'punch_out',
                'actual_working_day'    => 'actual_working_day',
                'paid_leave'            => 'paid_leave1',
                'holiday'               => 'holiday',
            ]
        ],
        'tabularNameSections' => [
            'attendance_details' => "Attendance details/Bảng công chi tiết",
        ]
    ],

    'payslip' => [
        'tabularSections' => [
            'attendance_details' => [
                'date'                  => 'Date',
                'punch_in'              => 'Punch_in',
                'punch_out'             => 'punch_out',
                'actual_working_day'    => 'actual_working_day',
                'paid_leave'            => 'paid_leave1',
                'holiday'               => 'holiday',
            ]
        ]
    ]
];
