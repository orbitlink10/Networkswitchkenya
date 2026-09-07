<?php

return [
    'name' => env('BUSINESS_NAME', env('APP_NAME', 'Network Switches Kenya')),
    'legal_name' => env('BUSINESS_LEGAL_NAME'),
    'phone' => env('BUSINESS_PHONE'),
    'whatsapp' => env('BUSINESS_WHATSAPP', env('BUSINESS_PHONE')),
    'email' => env('BUSINESS_EMAIL'),
    'address' => env('BUSINESS_ADDRESS'),
    'hours' => env('BUSINESS_HOURS'),
    'delivery_coverage' => env('BUSINESS_DELIVERY_COVERAGE'),
    'payment_options' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BUSINESS_PAYMENT_OPTIONS', ''))
    ))),
    'social_profiles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BUSINESS_SOCIAL_PROFILES', ''))
    ))),
];
