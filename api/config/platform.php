<?php

return [
    'admin_emails' => array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', (string) env('PAATHNEXUS_PLATFORM_ADMIN_EMAILS', '')))))),
];
