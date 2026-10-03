<?php

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — artist pages in the admin (docs/ARTISTS.md §6–§7)
|--------------------------------------------------------------------------
|
| The "Artists" screens: list and order, creation, profile (identity,
| portrait, presentation, publication), artworks, exhibitions; messages,
| activity log, field names for validation and the "where is this photo
| used" lines of the photo library. Same keys as lang/fr/admin_artists.php.
| :name = the artist, :title = the artwork or exhibition, :count = a number.
|
*/

return [

    'nav' => 'Artists',

    'status' => [
        'published' => 'Published',
        'draft' => 'Draft',
        'example' => 'Example',
        'hidden' => 'Hidden',
        'no_photo' => 'No photo',
    ],

    'actions' => [
        'add' => 'Add an artist',
        'example' => 'Create the example page',
        'edit' => 'Edit',
        'artworks' => 'Artworks',
        'exhibitions' => 'Exhibitions',
        'view' => 'View page',
        'preview' => 'Preview',
        'publish' => 'Publish',
        'hide' => 'Hide',
        'delete' => 'Delete',
    ],

    'a11y' => [
        'drag' => 'Move :name',
        'move_up' => 'Move :name up',
        'move_down' => 'Move :name down',
        'order' => 'Order of the artists on the site',
        'works_order' => 'Order of the artworks on the page',
    ],

    'counts' => [
        'artists' => '{0} No artists|{1} :count artist|[2,*] :count artists',
        'published' => '{0} none published|{1} :count published|[2,*] :count published',
        'artworks' => '{0} No artworks|{1} :count artwork|[2,*] :count artworks',
        'exhibitions' => '{0} No exhibitions|{1} :count exhibition|[2,*] :count exhibitions',
    ],

    'index' => [
        'title' => 'Artists',
        'lead' => 'One page per artist: their biography, a selection of works and their exhibitions. Published pages appear on the site in the order of this list.',
        'summary' => ':artists · :published',
        'order_hint' => 'To change the order, drag an artist by its handle or use the arrows: it is saved straight away.',
        'example_hint' => 'A complete fictional artist, to see what a finished page looks like.',
        'no_discipline' => 'Discipline to be added',
        'empty' => [
            'title' => 'No artists yet',
            'text' => 'Create the first page, or start with the example page: a complete fictional artist (biography, artworks, exhibitions) to explore, edit or delete.',
            'text_plain' => 'Create the first page: a name is enough to begin. You will then add their portrait, biography, artworks and exhibitions.',
        ],
    ],

    'create' => [
        'title' => 'New artist',
        'lead' => 'Start with the essentials: you will add the portrait, biography, artworks and exhibitions next.',
        'submit' => 'Create the page',
        'next_title' => 'What next?',
        'next' => [
            'Add a portrait and write the biography.',
            'Upload photos of the artworks, several at a time.',
            'Fill in the exhibitions, then publish the page.',
        ],
        'draft' => 'The page stays a draft, invisible on the site, until you publish it.',
    ],

    'edit' => [
        'lead' => 'The artist’s profile: identity, portrait, biography and publication.',
        'sections' => [
            'identity' => 'Identity',
            'portrait' => 'Portrait',
            'presentation' => 'Presentation',
            'publication' => 'Publication',
            'danger' => 'Delete the page',
        ],
        'example' => [
            'title' => 'Example page',
            'text' => ':name is a fictional artist, created to show you a complete page: biography, artworks, exhibitions. Take inspiration from it, turn it into a real page, or delete it when you no longer need it.',
            'preview' => 'View the example page',
            'delete' => 'Delete the example',
        ],
        'delete' => [
            'text' => 'The page will be deleted along with its artworks (:works) and exhibitions (:exhibitions). The photos stay in the photo library unless you tick the box.',
            'photos' => 'Also delete their photos (portrait, artworks, exhibitions) that are not used anywhere else on the site',
            'button' => 'Delete this artist',
            'confirm' => 'Permanently delete this page (:name), with its artworks and exhibitions? This cannot be undone.',
        ],
    ],

    'fields' => [
        'name' => [
            'label' => 'Artist’s name',
            'hint' => 'As it appears in large type at the top of the page.',
        ],
        'slug' => [
            'label' => 'Page address',
            'hint' => 'Lower-case letters, digits and hyphens. Left empty, it is made from the name.',
            'hint_edit' => 'Lower-case letters, digits and hyphens. Changing the address breaks links already shared.',
            'preview' => 'Address',
        ],
        'discipline' => [
            'label' => 'Discipline',
            'hint' => 'For example: painter, sculptor, photographer…',
        ],
        'location' => [
            'label' => 'Based in',
            'hint' => 'City and country, for example: Lyon, France.',
        ],
        'accent' => [
            'label' => 'Accent colour',
            'hint' => 'It colours the details of the page: rules, the portrait’s triangle, the monogram.',
        ],
        'website' => [
            'label' => 'Website',
            'hint' => 'Full address, starting with https://',
        ],
        'instagram' => [
            'label' => 'Instagram',
            'hint' => 'Full profile link, for example https://www.instagram.com/…',
        ],
        'statement' => [
            'label' => 'Artist statement',
            'hint' => 'One or two sentences about their approach, shown large under the name.',
        ],
        'bio' => [
            'label' => 'Biography',
            'hint' => 'Background, approach, influences… Three or four paragraphs are enough.',
        ],
        'meta' => [
            'label' => 'Description for Google',
            'hint' => 'About 160 characters, for search engines and shared links. Left empty, the statement is used.',
        ],
        'portrait' => [
            'label' => 'Portrait',
            'hint' => 'A portrait-format photo of the artist or their studio. Without one, the page shows their key work, or else a monogram of their initials.',
            'upload' => 'Upload a new photo',
            'upload_hint' => 'From a computer or a phone: it becomes the portrait at once.',
            'library' => 'Or choose a photo from the library',
            'save' => 'Use this photo',
            'immediate' => 'Portrait changes apply at once, without the “Save” button.',
        ],
        'is_published' => [
            'label' => 'Page published on the site',
            'hint' => 'Switched off, the page stays a draft that only signed-in administrators can see (Preview).',
        ],
        'en_hint' => 'Optional: without a translation, the French text is shown.',
    ],

    'accents' => [
        'blue' => 'Blue',
        'yellow' => 'Yellow',
        'red' => 'Red',
        'orange' => 'Orange',
        'amber' => 'Amber',
        'white' => 'White',
    ],

    'markdown' => [
        'summary' => 'Formatting the text',
        'intro' => 'Write normally: an empty line separates two paragraphs. For the rest, a few signs are enough.',
        'write' => 'You type',
        'result' => 'On the site',
        'rows' => [
            ['code' => '**important words**', 'result' => 'in bold'],
            ['code' => '*title of a work*', 'result' => 'in italics'],
            ['code' => '- an item', 'result' => 'a bulleted list'],
            ['code' => '[the text](https://…)', 'result' => 'a link'],
            ['code' => '## A subheading', 'result' => 'a subheading'],
            ['code' => '> a quotation', 'result' => 'a quotation'],
        ],
        'note' => 'HTML code is ignored, to keep the site safe.',
    ],

    'media' => [
        'choose' => 'Choose or add a photo',
        'clear' => 'Remove',
        'none' => '— No photo —',
        'select' => 'Photo from the library',
        'empty' => 'No photo',
        'current' => 'Chosen photo: :alt',
        'selected' => 'Photo no. :id',
        'edit' => 'Alternative text, focal point…',
        'size' => ':width × :height px',
        'library_hint' => 'For a new photo, first add it to the photo library (Photos menu), then choose it in this list.',
        'unavailable' => 'The photo library is not available yet: you will be able to choose a photo as soon as it is.',
    ],

    'subnav' => [
        'label' => 'Sections of the page — :name',
        'profile' => 'Profile',
        'artworks' => 'Artworks',
        'exhibitions' => 'Exhibitions',
    ],

    'artworks' => [
        'title' => ':name — Artworks',
        'lead' => 'One photo = one artwork. Add several at once, then complete each record: title, year, medium, dimensions, availability.',
        'upload' => 'Add artworks',
        'upload_card' => 'New artworks',
        'upload_hint' => 'One photo per artwork, several at once if you like, from a computer or a phone. Each photo becomes a record to complete. On the site, artworks are never cropped.',
        'library' => [
            'summary' => 'Use a photo already in the library',
            'hint' => 'Choose the photo, give it a title if you wish, then add the artwork.',
            'title' => 'Title of the artwork (optional)',
            'submit' => 'Add this artwork',
        ],
        'list_title' => 'The artworks',
        'order_hint' => 'Drag an artwork by its handle or use the arrows to choose the display order: it is saved straight away.',
        'empty' => [
            'title' => 'No artworks yet',
            'text' => 'Add photos of the artworks above: each one becomes a record to complete.',
        ],
        'untitled' => 'Untitled',
        'delete_confirm' => 'Delete the artwork “:title”? Its photo stays in the photo library.',
        'edit' => [
            'lead' => 'Artwork record · :name',
            'photo' => 'Photo of the artwork',
            'no_photo' => 'No photo for this artwork yet.',
            'natural' => 'Shown in full on the site, never cropped.',
            'replace' => 'Replace the file',
            'add_photo' => 'Add the photo',
            'replace_hint' => 'The new file replaces the old one everywhere this photo is used. The title and record do not change.',
            'details' => 'Artwork record',
            'other_photo' => 'Use another photo from the library',
            'other_photo_hint' => 'To link a photo already in the photo library to this record.',
            'publication' => 'Photo and publication',
            'danger' => 'Delete the artwork',
            'delete_text' => 'The record disappears from the page. The photo stays in the photo library unless you tick the box.',
            'delete_photo' => 'Also delete the photo from the library, if it is not used anywhere else',
            'delete' => 'Delete this artwork',
            'delete_confirm' => 'Permanently delete the artwork “:title”?',
        ],
        'fields' => [
            'title' => [
                'label' => 'Title',
                'hint' => 'Shown in italics, as in a catalogue.',
            ],
            'year' => [
                'label' => 'Year',
                'hint' => 'For example: 2025, or 2019–2021.',
            ],
            'medium' => [
                'label' => 'Medium',
                'hint' => 'For example: oil on canvas.',
            ],
            'dimensions' => [
                'label' => 'Dimensions',
                'hint' => 'For example: 100 × 80 cm.',
            ],
            'availability' => [
                'label' => 'Availability',
                'hint' => '“Not specified” shows nothing on the page.',
            ],
            'description' => [
                'label' => 'Notes',
                'hint' => 'A few lines about the work (optional), to unfold under the photo.',
            ],
            'is_published' => [
                'label' => 'Visible on the artist’s page',
                'hint' => 'Switched off, the artwork is hidden on the site but stays here.',
            ],
        ],
    ],

    'availability' => [
        'none' => 'Not specified',
        'available' => 'Available',
        'reserved' => 'Reserved',
        'sold' => 'Sold',
        'collection' => 'In a collection',
        'commission' => 'On commission',
    ],

    'exhibitions' => [
        'title' => ':name — Exhibitions',
        'lead' => 'Solo or group shows, residencies, fairs… Current and upcoming ones are highlighted on the page; all of them make up the artist’s CV.',
        'add' => 'Add an exhibition',
        'groups' => [
            'current' => 'Current',
            'upcoming' => 'Upcoming',
            'past' => 'Past',
        ],
        'empty' => [
            'title' => 'No exhibitions yet',
            'text' => 'Add their exhibitions, even old ones: a year is enough.',
        ],
        'delete_confirm' => 'Delete the exhibition “:title”?',
        'create' => [
            'title' => 'New exhibition',
            'lead' => 'For :name: a solo or group show, a residency, a fair…',
            'submit' => 'Add the exhibition',
        ],
        'edit' => [
            'lead' => 'Exhibition · :name',
        ],
        'sections' => [
            'about' => 'The exhibition',
            'dates' => 'Dates',
            'presentation' => 'Presentation',
            'visual' => 'Visual',
            'publication' => 'Publication',
            'danger' => 'Delete the exhibition',
        ],
        'dates_hint' => 'Dates are optional: a year is enough for an older exhibition. With a start date, the year follows that date.',
        'fields' => [
            'title' => [
                'label' => 'Title',
                'hint' => 'The title of the exhibition, shown in italics.',
            ],
            'kind' => [
                'label' => 'Type',
                'hint' => 'Shown above the title.',
            ],
            'venue' => [
                'label' => 'Venue',
                'hint' => 'Gallery, museum, art centre…',
            ],
            'city' => [
                'label' => 'City',
                'hint' => 'For example: Lyon.',
            ],
            'year' => [
                'label' => 'Year',
                'hint' => 'Required without a start date.',
            ],
            'starts_on' => [
                'label' => 'Start date',
                'hint' => 'Optional.',
            ],
            'ends_on' => [
                'label' => 'End date',
                'hint' => 'Optional; needs a start date.',
            ],
            'url' => [
                'label' => '“Find out more” link',
                'hint' => 'The event’s page: full address, starting with https://',
            ],
            'description' => [
                'label' => 'Presentation',
                'hint' => 'A few lines about the exhibition (optional).',
            ],
            'visual' => [
                'label' => 'Visual',
                'hint' => 'An installation view, the poster or an exhibited work, preferably in landscape format.',
                'upload' => 'Upload a new visual',
                'upload_hint' => 'From a computer or a phone: it becomes the exhibition’s visual at once.',
                'upload_after' => 'Save the exhibition first: you can then upload a photo from a computer or a phone.',
            ],
            'is_published' => [
                'label' => 'Visible on the artist’s page',
                'hint' => 'Switched off, the exhibition is hidden on the site but stays here.',
            ],
        ],
        'delete' => [
            'text' => 'The exhibition disappears from the page and the CV. Its visual stays in the photo library.',
            'button' => 'Delete this exhibition',
            'confirm' => 'Permanently delete the exhibition “:title”?',
        ],
    ],

    'kinds' => [
        'solo' => 'Solo exhibition',
        'group' => 'Group exhibition',
        'residency' => 'Residency',
        'fair' => 'Art fair',
        'other' => 'Other event',
    ],

    'flash' => [
        'created' => 'Page created for :name. Now add their portrait, biography, artworks and exhibitions.',
        'updated' => 'Changes saved for :name.',
        'deleted' => 'Page deleted: :name.',
        'published' => ':name is live! The page is visible on the site.',
        'hidden' => 'Page hidden: :name no longer appears on the site.',
        'reordered' => '{1} Order of the artists saved.|[2,*] New order of the :count artists saved.',
        'example_created' => '{0} The example page “:name” is ready, without photos (the photo library is not available). Explore it, edit it or delete it.|{1} The example page “:name” is ready, with :count imported photo. Explore it, edit it or delete it.|[2,*] The example page “:name” is ready, with :count imported photos. Explore it, edit it or delete it.',
        'example_exists' => 'The example page “:name” already exists: here it is.',
        'artwork_created' => 'Artwork “:title” added: complete its record.',
        'artwork_updated' => 'Artwork “:title” saved.',
        'artwork_deleted' => 'Artwork “:title” deleted.',
        'artworks_reordered' => '{1} Order of the artworks saved.|[2,*] New order of the :count artworks saved.',
        'image_replaced' => 'The photo of “:title” is replaced.',
        'exhibition_created' => 'Exhibition “:title” added.',
        'exhibition_updated' => 'Exhibition “:title” saved.',
        'exhibition_deleted' => 'Exhibition “:title” deleted.',
        'portrait_updated' => ':name’s portrait is updated.',
        'portrait_removed' => ':name’s portrait is removed: the page shows their key work or their monogram.',
        'exhibition_image' => 'The visual of “:title” is updated.',
    ],

    'errors' => [
        'migrate' => 'The artist pages need a database update: click “Update the database”.',
        'photo_required' => 'Choose a photo to upload, or a photo from the library.',
        'order' => 'This new order could not be saved: reload the page, then try again.',
        'example' => 'The example page could not be created. Please try again in a moment.',
        'photo' => [
            'not_image' => 'This file is not a readable image. Try a JPEG, PNG or WebP photo.',
            'type' => 'This format is not accepted. Send a JPEG, PNG, WebP or GIF photo.',
            'too_big' => 'This photo is too heavy. Try a lighter version.',
            'too_many_pixels' => 'This image is too large. Reduce its dimensions, then try again.',
            'storage' => 'The photo could not be saved. Please try again in a moment.',
            'quota' => 'The photo library is full: delete unused photos before adding new ones.',
            'interrupted' => 'The upload was interrupted. Check your connection, then try again.',
        ],
    ],

    // Messages of the validation rules specific to these forms (the others come from validation.php).
    'validation' => [
        'slug_regex' => 'The address may only contain lower-case letters, digits and hyphens (for example: camille-durand).',
        'slug_unique' => 'This address is already used by another artist: please choose another one.',
        'year_required' => 'Enter the year, or a start date.',
        'starts_required' => 'An end date needs a start date.',
        'ends_after' => 'The end date cannot come before the start date.',
        'https' => 'Enter a full address starting with https:// (for example: https://www.example.com).',
    ],

    'activity' => [
        'created' => 'Artist page created: :name',
        'updated' => 'Artist page edited: :name',
        'deleted' => 'Artist page deleted: :name',
        'published' => 'Artist page published: :name',
        'hidden' => 'Artist page hidden: :name',
        'reordered' => '{1} Order of the artists changed|[2,*] Order of the :count artists changed',
        'example' => 'Example page created: :name',
        'artwork_created' => 'Artwork added: “:title” (:name)',
        'artwork_updated' => 'Artwork edited: “:title” (:name)',
        'artwork_deleted' => 'Artwork deleted: “:title” (:name)',
        'artworks_reordered' => '{1} Order of the artworks changed (:name)|[2,*] Order of the :count artworks changed (:name)',
        'image_replaced' => 'Artwork photo replaced: “:title” (:name)',
        'exhibition_created' => 'Exhibition added: “:title” (:name)',
        'exhibition_updated' => 'Exhibition edited: “:title” (:name)',
        'exhibition_deleted' => 'Exhibition deleted: “:title” (:name)',
        'portrait_updated' => 'Portrait changed: :name',
        'portrait_removed' => 'Portrait removed: :name',
        'exhibition_image' => 'Exhibition visual changed: “:title” (:name)',
    ],

    'usage' => [
        'portrait' => 'Portrait — :name (artist page)',
        'artwork' => 'Artwork “:title” — :name',
        'exhibition' => 'Exhibition “:title” — :name',
    ],

    'attributes' => [
        'name' => 'artist’s name',
        'slug' => 'page address',
        'discipline_fr' => 'discipline (French)',
        'discipline_en' => 'discipline (English)',
        'location' => 'place of residence',
        'accent' => 'accent colour',
        'website' => 'website',
        'instagram' => 'Instagram',
        'statement_fr' => 'statement (French)',
        'statement_en' => 'statement (English)',
        'bio_fr' => 'biography (French)',
        'bio_en' => 'biography (English)',
        'meta_fr' => 'description for Google (French)',
        'meta_en' => 'description for Google (English)',
        'portrait_media_id' => 'portrait',
        'is_published' => 'publication',
        'photo' => 'photo',
        'media_id' => 'photo',
        'original_name' => 'file name',
        'title_fr' => 'title (French)',
        'title_en' => 'title (English)',
        'year' => 'year',
        'medium_fr' => 'medium (French)',
        'medium_en' => 'medium (English)',
        'dimensions' => 'dimensions',
        'description_fr' => 'text (French)',
        'description_en' => 'text (English)',
        'availability' => 'availability',
        'kind' => 'type of exhibition',
        'venue' => 'venue',
        'city' => 'city',
        'starts_on' => 'start date',
        'ends_on' => 'end date',
        'url' => '“Find out more” link',
        'order' => 'order',
    ],

];
