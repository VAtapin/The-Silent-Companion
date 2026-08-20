<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'text_model' => env('OPENAI_TEXT_MODEL'),
    'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
    'monthly_budget' => (float) env('OPENAI_MONTHLY_BUDGET', 0),
    'user_daily_limit' => (int) env('OPENAI_USER_DAILY_LIMIT', 20),
    'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    'text_input_cost_per_million' => (float) env('OPENAI_TEXT_INPUT_COST_PER_MILLION', 0),
    'text_output_cost_per_million' => (float) env('OPENAI_TEXT_OUTPUT_COST_PER_MILLION', 0),
    'image_costs' => [
        'low' => (float) env('OPENAI_IMAGE_COST_LOW', 0.006),
        'medium' => (float) env('OPENAI_IMAGE_COST_MEDIUM', 0.053),
        'high' => (float) env('OPENAI_IMAGE_COST_HIGH', 0.211),
    ],
];
