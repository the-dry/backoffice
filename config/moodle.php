<?php

return [
    'base_url' => env('MOODLE_BASE_URL', 'YOUR_MOODLE_SITE_URL'), // Example: https://yourmoodle.com/webservice/rest/server.php
    'token' => env('MOODLE_WS_TOKEN', 'YOUR_MOODLE_WS_TOKEN'),
    'format' => 'json', // Moodle API response format
];
