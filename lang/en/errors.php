<?php

// Error pages (resources/views/errors/*).
return [
    'back_home' => 'Back to the home page',
    'services' => 'See the services',
    'contact' => 'Write to us',
    'retry' => 'Try again',
    '404' => [
        'title' => 'Artwork not found',
        'text' => 'This page has come loose from the triangle. It may have moved, or it never existed.',
        'code' => 'Error 404',
    ],
    '419' => [
        'title' => 'Session expired',
        'text' => 'The page stayed open a little too long. Reload it and send your form again.',
        'code' => 'Error 419',
    ],
    '429' => [
        'title' => 'A little too fast',
        'text' => 'You made many requests in a short time. Please wait a minute before trying again.',
        'code' => 'Error 429',
    ],
    '500' => [
        'title' => 'The atelier is in a mess',
        'text' => 'An unexpected error occurred. We are tidying our brushes: please try again in a moment.',
        'code' => 'Error 500',
    ],
    '503' => [
        'title' => 'The atelier is getting a makeover',
        'text' => 'The site is under maintenance for a few moments. Thank you for your patience.',
        'code' => 'Maintenance',
    ],
];
