<?php

return [
    'report_recipients' => env('STATISTICS_REPORT_RECIPIENTS', ''),
    'report_schedule' => env('STATISTICS_REPORT_SCHEDULE', '0 7 * * 3'), // 7AM on each Wednesday
];
