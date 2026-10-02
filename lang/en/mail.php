<?php

/*
|--------------------------------------------------------------------------
| E-mails sent by the site (B · App\Mail)
|--------------------------------------------------------------------------
*/

return [

    'contact' => [
        'subject' => 'New request — :name',
        'preheader' => 'A new request has just come in through the website form.',
        'tagline' => 'Art in the service of the community',
        'eyebrow' => 'Contact form',
        'heading' => 'New quote request',
        'intro' => ':name wrote to you from the website on :date.',
        'line' => ':label: :value',
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'service' => 'Service',
            'budget' => 'Budget',
            'locale' => 'Message language',
            'message' => 'Message',
        ],
        'not_specified' => 'Not specified',
        'reply' => 'Reply to :name',
        'footer' => 'Sent from the contact form on :site. Simply reply to this email to write to :name directly.',
        'failed' => 'Your message could not be sent just now. Please try again a little later.',
        'budgets' => [
            'small' => 'Small budget',
            'medium' => 'Mid-range budget',
            'large' => 'Large budget',
            'unsure' => 'To be decided together',
        ],
    ],

];
