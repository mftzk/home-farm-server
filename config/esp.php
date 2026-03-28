<?php

return [
    'ip' => env('ESP_IP', '192.168.1.100'),
    'relay_ip' => env('ESP_RELAY_IP', '192.168.1.101'),
    'timeout' => (int) env('ESP_TIMEOUT', 5),
];
