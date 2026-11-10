<?php

return [
    'timeout' => 5, 'connect_timeout' => 2, 'requests_per_minute' => 30,
    'qrz' => ['enabled' => (bool) env('QRZ_ENABLED', false), 'username' => env('QRZ_USERNAME'), 'password' => env('QRZ_PASSWORD')],
    'hamqth' => ['enabled' => (bool) env('HAMQTH_ENABLED', false), 'username' => env('HAMQTH_USERNAME'), 'password' => env('HAMQTH_PASSWORD')],
];
