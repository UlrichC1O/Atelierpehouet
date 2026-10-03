<?php

// Error pages (resources/views/errors/*).
return [
    'back_home' => 'Back to the home page',
    'services' => 'See the services',
    'contact' => 'Write to us',
    'retry' => 'Try again',
    '401' => [
        'title' => 'Sign-in required',
        'text' => 'This page is private. Please sign in to see it.',
        'code' => 'Error 401',
    ],
    '402' => [
        'title' => 'Not available',
        'text' => 'This page is not available right now.',
        'code' => 'Error 402',
    ],
    '403' => [
        'title' => 'Access denied',
        'text' => 'You don’t have permission to see this page.',
        'code' => 'Error 403',
    ],
    // Any other client error (400, 405, 410, 413…): the code shows the actual status.
    '4xx' => [
        'title' => 'Request not possible',
        'text' => 'The link may be incomplete, or the page may have changed. Go back home or write to us.',
        'code' => 'Error :code',
    ],
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
