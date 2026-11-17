<?php

return [
    // 10-minute payment deadline from PRD ("Overview of the Transaction Process").
    'payment_window_minutes' => 10,

    // 48 hours hold after end-of-event before releasing payment to the seller.
    'release_hold_hours' => 48,

    // Seller notification + transfer grace period for the 30-minute rule.
    'short_transfer_minutes' => 30,

    // Withdrawals execute within 2 business days.
    'withdrawal_business_days' => 2,

    'confidence' => [
        // Python script; override with env var if needed on prod.
        'python_binary' => env('RESALE_PYTHON_BIN', 'python3'),
        'python_script' => env('RESALE_CONFIDENCE_SCRIPT', base_path('scripts/confidence_level.py')),

        // Probability thresholds. Adjust when the model is tuned.
        'medium_threshold' => env('RESALE_CONFIDENCE_MEDIUM', 0.15),
        'high_threshold' => env('RESALE_CONFIDENCE_HIGH', 0.30),
        'ban_threshold' => env('RESALE_CONFIDENCE_BAN', 0.60),
    ],
];
