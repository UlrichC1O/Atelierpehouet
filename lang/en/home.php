<?php

// Home page (resources/views/pages/home.blade.php).
return [
    'meta' => 'Ateliers Pehouet, an art studio in the service of the community: murals, paintings, portraits, design, signs, sculpture, sets, classes and participatory projects.',
    'hero' => [
        'heading' => 'Ateliers Pehouet — Art in the service of the community',
        'prefix' => 'The atelier of',
        'words' => ['murals', 'sculpture', 'colour', 'signs', 'mosaic', 'the stage', 'teaching', 'community'],
        'cta_services' => 'Discover our services',
        'cta_create' => 'Create my artwork',
        'scroll' => 'Scroll down',
    ],
    'manifesto' => [
        'eyebrow' => 'Manifesto',
        'statement' => 'We believe that a wall, a square, a classroom or a celebration can become artworks that bring people together.',
        'text' => 'Our logo says it in five colours. This is how we read it.',
        'colours' => [
            'blue' => ['name' => 'Blue', 'meaning' => 'Trust and listening: every project starts by hearing you.'],
            'yellow' => ['name' => 'Yellow', 'meaning' => 'Light and ideas: the spark that transforms a place.'],
            'red' => ['name' => 'Red', 'meaning' => 'The energy of the gesture: painting, carving, assembling with passion.'],
            'white' => ['name' => 'White', 'meaning' => 'The seams that connect: art as a bond between people.'],
            'black' => ['name' => 'Black', 'meaning' => 'The ground and the canvas: the space every colour is born from.'],
        ],
    ],
    'services' => [
        'eyebrow' => 'Crafts',
        'title' => ':count services, one shared vision',
        'lead' => 'Painting, design, material, spaces, image and teaching: choose a family or browse the whole catalogue.',
        'all' => 'See all services',
        'filter_label' => 'Filter the services',
        'announce' => '{count} service(s) shown',
    ],
    'process' => [
        'eyebrow' => 'Method',
        'title' => 'From the first word to the unveiled work',
        'steps' => [
            ['title' => 'Listen', 'text' => 'Your place, your story, your wishes.'],
            ['title' => 'Sketch', 'text' => 'Sketches, mock-ups and palettes to approve.'],
            ['title' => 'Create', 'text' => 'The atelier’s craft, alone or with you.'],
            ['title' => 'Celebrate', 'text' => 'The work unveiled and shared.'],
        ],
    ],
    'numbers' => [
        'eyebrow' => 'In numbers',
        'title' => 'The atelier, plainly',
        'services' => 'art services',
        'families' => 'families of crafts',
        'colours' => 'founding colours',
        'animations' => 'animations on this site',
        'triangle' => 'triangle to bring it all together',
    ],
    'generator' => [
        'eyebrow' => 'Digital Atelier',
        'title' => 'One word, one artwork',
        'text' => 'Type a first name, a street, a date: our generative engine, written in Python, composes a unique artwork in the logo’s colours.',
        'cta' => 'Try it now',
        'alt' => 'Generative composition in the :style style',
    ],
    'gallery' => [
        'eyebrow' => 'Gallery',
        'title' => 'Colours set free',
        'text' => 'Triangles, stained glass, mosaics, shards: a collection of compositions born from our engine.',
        'cta' => 'Enter the gallery',
    ],
    'community' => [
        'eyebrow' => 'Community',
        'title' => 'Art is made together',
        'text' => 'Neighbourhood murals, children’s workshops, sets for celebrations, intergenerational projects: the atelier puts its craft in the service of places and the people who bring them to life.',
        'quote' => 'A shared artwork belongs to everyone who watched it being born.',
        'cta' => 'Discover our community projects',
    ],
    'cta' => [
        'title' => 'Let’s create together',
        'text' => 'A wall to awaken, a place to dress, a workshop to share: let’s talk about your project.',
        'button' => 'Request a quote',
        'secondary' => 'See the services',
    ],
];
