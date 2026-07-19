<?php

/**
 * USD price per 1,000,000 tokens, by model. Update when a provider changes
 * pricing — chat_message.model is a per-row snapshot so historical cost
 * stays correct without backfill; only add/adjust entries here.
 */
return [
    'default' => [
        'input' => 0.10,
        'output' => 0.40,
    ],

    'models' => [
        'gemini-2.0-flash-lite' => ['input' => 0.075, 'output' => 0.30],
        'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
    ],
];
