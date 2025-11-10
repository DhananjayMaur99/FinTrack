<?php

return [
    // Configurable TTL for issued API tokens (in minutes)
    // Default: 60 (1 hour). Adjust via TOKEN_TTL_MINUTES in .env.
    'ttl_minutes' => (int) env('TOKEN_TTL_MINUTES', 60),
];
