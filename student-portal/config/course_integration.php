<?php

return [
    'enabled' => (bool) env('COURSE_INTEGRATION_ENABLED', false),
    'sandbox' => (bool) env('COURSE_INTEGRATION_SANDBOX', false),
    'allowed_email' => strtolower(trim((string) env('COURSE_INTEGRATION_ALLOWED_EMAIL', ''))),
    'webhook_key' => env('COURSE_INTEGRATION_WEBHOOK_KEY', ''),
    'project_id' => env('COURSE_INTEGRATION_PROJECT_ID', ''),
    'page_id' => env('COURSE_INTEGRATION_PAGE_ID', ''),
    'form_ids' => array_filter(explode(',', env('COURSE_INTEGRATION_FORM_IDS', ''))),
    'course_slug' => env('COURSE_INTEGRATION_COURSE_SLUG', ''),
    'landing_url' => env('COURSE_INTEGRATION_LANDING_URL', ''),
    'mail_enabled' => (bool) env('PORTAL_MAIL_ENABLED', false),
    'enkod_key' => env('ENKOD_API_KEY', ''),
    'enkod_message_id' => (int) env('ENKOD_MESSAGE_ID', 0),
    'from' => env('ENKOD_FROM', ''),
];
