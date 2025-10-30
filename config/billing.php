<?php

return [
    'dev' => [
        'force_plan' => env('BILLING_FORCE_PLAN', null),

        'flags' => [
            'export' => (bool) env('BILLING_FEATURE_EXPORT', false),
            'fx'     => (bool) env('BILLING_FEATURE_FX', false),
        ],
    ],
];
