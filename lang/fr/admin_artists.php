<?php

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — pages d’artistes dans l’administration (docs/ARTISTS.md §6–§7)
|--------------------------------------------------------------------------
|
| Écrans « Artistes » : liste et ordre, création, profil (identité, portrait,
| présentation, publication), œuvres, expositions ; messages, journal
| d’activité, noms des champs pour la validation et lignes « où cette photo
| est utilisée » de la photothèque. lang/en/admin_artists.php reprend
| exactement les mêmes clés. :name = l’artiste, :title = l’œuvre ou
| l’exposition, :count = un nombre.
|
*/

return [

    'nav' => 'Artistes',

    'status' => [
        'published' => 'Publié',
        'draft' => 'Brouillon',
        'example' => 'Exemple',
        'hidden' => 'Masquée',
        'no_photo' => 'Sans photo',
    ],

    'actions' => [
        'add' => 'Ajouter un artiste',
        'example' => 'Créer la page exemple',
        'edit' => 'Modifier',
        'artworks' => 'Œuvres',
        'exhibitions' => 'Expositions',
        'view' => 'Voir la page',
        'preview' => 'Aperçu',
        'publish' => 'Publier',
        'hide' => 'Masquer',
        'delete' => 'Supprimer',
    ],

    'a11y' => [
        'drag' => 'Déplacer :name',
        'move_up' => 'Monter :name',
        'move_down' => 'Descendre :name',
        'order' => 'Ordre des artistes sur le site',
        'works_order' => 'Ordre des œuvres sur la page',
    ],

    'counts' => [
        'artists' => '{0} Aucun artiste|{1} :count artiste|[2,*] :count artistes',
        'published' => '{0} aucun publié|{1} :count publié|[2,*] :count publiés',
        'artworks' => '{0} Aucune œuvre|{1} :count œuvre|[2,*] :count œuvres',
        'exhibitions' => '{0} Aucune exposition|{1} :count exposition|[2,*] :count expositions',
    ],

    'index' => [
        'title' => 'Artistes',
        'lead' => "Une page par artiste\u{202F}: sa biographie, une sélection d’œuvres et ses expositions. Les pages publiées apparaissent sur le site, dans l’ordre de cette liste.",
        'summary' => ':artists · :published',
        'order_hint' => "Pour changer l’ordre, glissez un artiste par sa poignée ou utilisez les flèches\u{202F}: c’est enregistré tout de suite.",
        'example_hint' => 'Un artiste fictif complet, pour voir à quoi ressemble une page terminée.',
        'no_discipline' => 'Discipline à préciser',
        'empty' => [
            'title' => 'Aucun artiste pour le moment',
            'text' => "Créez la première page, ou commencez par la page exemple\u{202F}: un artiste fictif complet (biographie, œuvres, expositions) à explorer, modifier ou supprimer.",
            'text_plain' => "Créez la première page\u{202F}: son nom suffit pour commencer. Vous ajouterez ensuite son portrait, sa biographie, ses œuvres et ses expositions.",
        ],
    ],

    'create' => [
        'title' => 'Nouvel artiste',
        'lead' => "Commencez par l’essentiel\u{202F}: vous ajouterez ensuite le portrait, la biographie, les œuvres et les expositions.",
        'submit' => 'Créer la page',
        'next_title' => "Et ensuite\u{202F}?",
        'next' => [
            'Ajoutez un portrait et rédigez la biographie.',
            'Envoyez les photos des œuvres, plusieurs à la fois.',
            'Complétez les expositions, puis publiez la page.',
        ],
        'draft' => 'La page reste un brouillon, invisible sur le site, tant que vous ne la publiez pas.',
    ],

    'edit' => [
        'lead' => "Profil de l’artiste\u{202F}: identité, portrait, biographie et publication.",
        'sections' => [
            'identity' => 'Identité',
            'portrait' => 'Portrait',
            'presentation' => 'Présentation',
            'publication' => 'Publication',
            'danger' => 'Supprimer la page',
        ],
        'example' => [
            'title' => 'Page d’exemple',
            'text' => ":name est un personnage fictif, créé pour vous montrer une page complète\u{202F}: biographie, œuvres, expositions. Inspirez-vous-en, transformez-la en vraie page ou supprimez-la quand vous n’en avez plus besoin.",
            'preview' => 'Voir la page exemple',
            'delete' => 'Supprimer l’exemple',
        ],
        'delete' => [
            'text' => 'La page sera supprimée avec ses œuvres (:works) et ses expositions (:exhibitions). Les photos restent dans la photothèque, sauf si vous cochez la case.',
            'photos' => 'Supprimer aussi ses photos (portrait, œuvres, expositions) qui ne servent nulle part ailleurs sur le site',
            'button' => 'Supprimer cet artiste',
            'confirm' => "Supprimer définitivement cette page (:name), ses œuvres et ses expositions\u{202F}? Cette action est irréversible.",
        ],
    ],

    'fields' => [
        'name' => [
            'label' => 'Nom de l’artiste',
            'hint' => 'Tel qu’il s’affiche en grand en haut de la page.',
        ],
        'slug' => [
            'label' => 'Adresse de la page',
            'hint' => 'Lettres minuscules, chiffres et tirets. Laissée vide, elle est créée à partir du nom.',
            'hint_edit' => 'Lettres minuscules, chiffres et tirets. Changer l’adresse rend les liens déjà partagés invalides.',
            'preview' => 'Adresse',
        ],
        'discipline' => [
            'label' => 'Discipline',
            'hint' => "Par exemple\u{202F}: peintre, sculptrice, photographe…",
        ],
        'location' => [
            'label' => 'Basé·e à',
            'hint' => "Ville et pays, par exemple\u{202F}: Lyon, France.",
        ],
        'accent' => [
            'label' => 'Couleur d’accent',
            'hint' => "Elle colore les détails de la page\u{202F}: filets, triangle du portrait, monogramme.",
        ],
        'website' => [
            'label' => 'Site web',
            'hint' => 'Adresse complète, commençant par https://',
        ],
        'instagram' => [
            'label' => 'Instagram',
            'hint' => 'Lien complet du profil, par exemple https://www.instagram.com/…',
        ],
        'statement' => [
            'label' => 'Phrase d’intention',
            'hint' => 'Une ou deux phrases sur sa démarche, affichées en grand sous le nom.',
        ],
        'bio' => [
            'label' => 'Biographie',
            'hint' => 'Parcours, démarche, influences… Trois ou quatre paragraphes suffisent.',
        ],
        'meta' => [
            'label' => 'Description pour Google',
            'hint' => 'Environ 160 caractères, pour les moteurs de recherche et les partages. Laissée vide, la phrase d’intention est utilisée.',
        ],
        'portrait' => [
            'label' => 'Portrait',
            'hint' => 'Une photo verticale de l’artiste ou de son atelier. Sans portrait, la page montre son œuvre phare, sinon un monogramme à ses initiales.',
            'upload' => 'Envoyer une nouvelle photo',
            'upload_hint' => "Depuis l’ordinateur ou le téléphone\u{202F}: elle devient aussitôt le portrait.",
            'library' => 'Ou choisir une photo de la photothèque',
            'save' => 'Utiliser cette photo',
            'immediate' => "Les changements de portrait s’appliquent tout de suite, sans le bouton «\u{202F}Enregistrer\u{202F}».",
        ],
        'is_published' => [
            'label' => 'Page publiée sur le site',
            'hint' => 'Désactivée, la page reste un brouillon que seuls les administrateurs connectés peuvent voir (Aperçu).',
        ],
        'en_hint' => "Facultatif\u{202F}: sans traduction, le texte français s’affiche.",
    ],

    'accents' => [
        'blue' => 'Bleu',
        'yellow' => 'Jaune',
        'red' => 'Rouge',
        'orange' => 'Orange',
        'amber' => 'Ambre',
        'white' => 'Blanc',
    ],

    'markdown' => [
        'summary' => 'Mettre en forme le texte',
        'intro' => "Écrivez normalement\u{202F}: une ligne vide sépare deux paragraphes. Pour le reste, quelques signes suffisent.",
        'write' => 'Vous écrivez',
        'result' => 'Sur le site',
        'rows' => [
            ['code' => '**mots importants**', 'result' => 'en gras'],
            ['code' => '*titre d’une œuvre*', 'result' => 'en italique'],
            ['code' => '- un élément', 'result' => 'une liste à puces'],
            ['code' => '[le texte](https://…)', 'result' => 'un lien'],
            ['code' => '## Un intertitre', 'result' => 'un intertitre'],
            ['code' => '> une citation', 'result' => 'une citation'],
        ],
        'note' => 'Le code HTML est ignoré, pour la sécurité du site.',
    ],

    'media' => [
        'choose' => 'Choisir ou ajouter une photo',
        'clear' => 'Retirer',
        'none' => '— Aucune photo —',
        'select' => 'Photo de la photothèque',
        'empty' => 'Aucune photo',
        'current' => "Photo choisie\u{202F}: :alt",
        'selected' => 'Photo n° :id',
        'edit' => 'Texte alternatif, point focal…',
        'size' => ':width × :height px',
        'library_hint' => 'Pour une nouvelle photo, ajoutez-la d’abord à la photothèque (menu Photos), puis choisissez-la dans cette liste.',
        'unavailable' => "La photothèque n’est pas encore disponible\u{202F}: vous pourrez choisir une photo dès qu’elle le sera.",
    ],

    'subnav' => [
        'label' => 'Sections de la page — :name',
        'profile' => 'Profil',
        'artworks' => 'Œuvres',
        'exhibitions' => 'Expositions',
    ],

    'artworks' => [
        'title' => ':name — Œuvres',
        'lead' => "Une photo = une œuvre. Ajoutez-en plusieurs à la fois, puis complétez chaque fiche\u{202F}: titre, année, technique, dimensions, disponibilité.",
        'upload' => 'Ajouter des œuvres',
        'upload_card' => 'Nouvelles œuvres',
        'upload_hint' => 'Une photo par œuvre, plusieurs à la fois si vous voulez, depuis l’ordinateur ou le téléphone. Chaque photo devient une fiche à compléter. Sur le site, les œuvres ne sont jamais recadrées.',
        'library' => [
            'summary' => 'Utiliser une photo déjà dans la photothèque',
            'hint' => 'Choisissez la photo, donnez un titre si vous le souhaitez, puis ajoutez l’œuvre.',
            'title' => 'Titre de l’œuvre (facultatif)',
            'submit' => 'Ajouter cette œuvre',
        ],
        'list_title' => 'Les œuvres',
        'order_hint' => "Glissez une œuvre par sa poignée ou utilisez les flèches pour choisir l’ordre d’affichage\u{202F}: c’est enregistré tout de suite.",
        'empty' => [
            'title' => 'Aucune œuvre pour le moment',
            'text' => "Ajoutez les photos des œuvres ci-dessus\u{202F}: chacune devient une fiche à compléter.",
        ],
        'untitled' => 'Sans titre',
        'delete_confirm' => "Supprimer l’œuvre «\u{202F}:title\u{202F}»\u{202F}? Sa photo reste dans la photothèque.",
        'edit' => [
            'lead' => 'Fiche de l’œuvre · :name',
            'photo' => 'Photo de l’œuvre',
            'no_photo' => 'Pas encore de photo pour cette œuvre.',
            'natural' => 'Affichée en entier sur le site, sans recadrage.',
            'replace' => 'Remplacer le fichier',
            'add_photo' => 'Ajouter la photo',
            'replace_hint' => 'Le nouveau fichier remplace l’ancien partout où cette photo est utilisée. Le titre et la fiche ne changent pas.',
            'details' => 'Fiche de l’œuvre',
            'other_photo' => 'Utiliser une autre photo de la photothèque',
            'other_photo_hint' => 'Pour associer à cette fiche une photo déjà présente dans la photothèque.',
            'publication' => 'Photo et publication',
            'danger' => 'Supprimer l’œuvre',
            'delete_text' => 'La fiche disparaît de la page. La photo reste dans la photothèque, sauf si vous cochez la case.',
            'delete_photo' => 'Supprimer aussi la photo de la photothèque, si elle ne sert nulle part ailleurs',
            'delete' => 'Supprimer cette œuvre',
            'delete_confirm' => "Supprimer définitivement l’œuvre «\u{202F}:title\u{202F}»\u{202F}?",
        ],
        'fields' => [
            'title' => [
                'label' => 'Titre',
                'hint' => 'Affiché en italique, comme dans un catalogue.',
            ],
            'year' => [
                'label' => 'Année',
                'hint' => "Par exemple\u{202F}: 2025, ou 2019–2021.",
            ],
            'medium' => [
                'label' => 'Technique',
                'hint' => "Par exemple\u{202F}: huile sur toile.",
            ],
            'dimensions' => [
                'label' => 'Dimensions',
                'hint' => "Par exemple\u{202F}: 100 × 80 cm.",
            ],
            'availability' => [
                'label' => 'Disponibilité',
                'hint' => "«\u{202F}Non précisée\u{202F}» n’affiche rien sur la page.",
            ],
            'description' => [
                'label' => 'Notice',
                'hint' => 'Quelques lignes sur l’œuvre (facultatif), à déplier sous la photo.',
            ],
            'is_published' => [
                'label' => 'Visible sur la page de l’artiste',
                'hint' => 'Désactivée, l’œuvre est masquée sur le site mais reste ici.',
            ],
        ],
    ],

    'availability' => [
        'none' => 'Non précisée',
        'available' => 'Disponible',
        'reserved' => 'Réservée',
        'sold' => 'Vendue',
        'collection' => 'En collection',
        'commission' => 'Sur commande',
    ],

    'exhibitions' => [
        'title' => ':name — Expositions',
        'lead' => "Expositions personnelles ou collectives, résidences, foires… Celles en cours et à venir sont mises en avant sur la page\u{202F}; toutes composent le parcours de l’artiste.",
        'add' => 'Ajouter une exposition',
        'groups' => [
            'current' => 'En cours',
            'upcoming' => 'À venir',
            'past' => 'Passées',
        ],
        'empty' => [
            'title' => 'Aucune exposition pour le moment',
            'text' => "Ajoutez ses expositions, même anciennes\u{202F}: une année suffit.",
        ],
        'delete_confirm' => "Supprimer l’exposition «\u{202F}:title\u{202F}»\u{202F}?",
        'create' => [
            'title' => 'Nouvelle exposition',
            'lead' => "Pour :name\u{202F}: une exposition personnelle ou collective, une résidence, une foire…",
            'submit' => 'Ajouter l’exposition',
        ],
        'edit' => [
            'lead' => 'Exposition · :name',
        ],
        'sections' => [
            'about' => 'L’exposition',
            'dates' => 'Dates',
            'presentation' => 'Présentation',
            'visual' => 'Visuel',
            'publication' => 'Publication',
            'danger' => 'Supprimer l’exposition',
        ],
        'dates_hint' => "Les dates sont facultatives\u{202F}: une année suffit pour une exposition ancienne. Avec une date de début, l’année suit cette date.",
        'fields' => [
            'title' => [
                'label' => 'Titre',
                'hint' => 'Le titre de l’exposition, affiché en italique.',
            ],
            'kind' => [
                'label' => 'Type',
                'hint' => 'Affiché au-dessus du titre.',
            ],
            'venue' => [
                'label' => 'Lieu',
                'hint' => 'Galerie, musée, centre d’art…',
            ],
            'city' => [
                'label' => 'Ville',
                'hint' => "Par exemple\u{202F}: Lyon.",
            ],
            'year' => [
                'label' => 'Année',
                'hint' => 'Obligatoire sans date de début.',
            ],
            'starts_on' => [
                'label' => 'Date de début',
                'hint' => 'Facultative.',
            ],
            'ends_on' => [
                'label' => 'Date de fin',
                'hint' => "Facultative\u{202F}; demande une date de début.",
            ],
            'url' => [
                'label' => "Lien «\u{202F}En savoir plus\u{202F}»",
                'hint' => "La page de l’événement\u{202F}: adresse complète, commençant par https://",
            ],
            'description' => [
                'label' => 'Présentation',
                'hint' => 'Quelques lignes sur l’exposition (facultatif).',
            ],
            'visual' => [
                'label' => 'Visuel',
                'hint' => 'Une vue de l’exposition, l’affiche ou une œuvre exposée, de préférence en format paysage.',
                'upload' => 'Envoyer un nouveau visuel',
                'upload_hint' => "Depuis l’ordinateur ou le téléphone\u{202F}: il devient aussitôt le visuel de l’exposition.",
                'upload_after' => "Enregistrez d’abord l’exposition\u{202F}: vous pourrez ensuite envoyer une photo depuis l’ordinateur ou le téléphone.",
            ],
            'is_published' => [
                'label' => 'Visible sur la page de l’artiste',
                'hint' => 'Désactivée, l’exposition est masquée sur le site mais reste ici.',
            ],
        ],
        'delete' => [
            'text' => 'L’exposition disparaît de la page et du parcours. Son visuel reste dans la photothèque.',
            'button' => 'Supprimer cette exposition',
            'confirm' => "Supprimer définitivement l’exposition «\u{202F}:title\u{202F}»\u{202F}?",
        ],
    ],

    'kinds' => [
        'solo' => 'Exposition personnelle',
        'group' => 'Exposition collective',
        'residency' => 'Résidence',
        'fair' => 'Foire d’art',
        'other' => 'Autre événement',
    ],

    'flash' => [
        'created' => 'Page créée pour :name. Ajoutez maintenant son portrait, sa biographie, ses œuvres et ses expositions.',
        'updated' => 'Modifications enregistrées pour :name.',
        'deleted' => "Page supprimée\u{202F}: :name.",
        'published' => ":name est en ligne\u{202F}! La page est visible sur le site.",
        'hidden' => "Page masquée\u{202F}: :name n’apparaît plus sur le site.",
        'reordered' => '{1} Ordre des artistes enregistré.|[2,*] Nouvel ordre des :count artistes enregistré.',
        'example_created' => "{0} La page exemple «\u{202F}:name\u{202F}» est prête, sans photos (la photothèque n’est pas disponible). Explorez-la, modifiez-la ou supprimez-la.|{1} La page exemple «\u{202F}:name\u{202F}» est prête, avec :count photo importée. Explorez-la, modifiez-la ou supprimez-la.|[2,*] La page exemple «\u{202F}:name\u{202F}» est prête, avec :count photos importées. Explorez-la, modifiez-la ou supprimez-la.",
        'example_exists' => "La page exemple «\u{202F}:name\u{202F}» existe déjà\u{202F}: la voici.",
        'artwork_created' => "Œuvre «\u{202F}:title\u{202F}» ajoutée\u{202F}: complétez sa fiche.",
        'artwork_updated' => "Œuvre «\u{202F}:title\u{202F}» enregistrée.",
        'artwork_deleted' => "Œuvre «\u{202F}:title\u{202F}» supprimée.",
        'artworks_reordered' => '{1} Ordre des œuvres enregistré.|[2,*] Nouvel ordre des :count œuvres enregistré.',
        'image_replaced' => "La photo de «\u{202F}:title\u{202F}» est remplacée.",
        'exhibition_created' => "Exposition «\u{202F}:title\u{202F}» ajoutée.",
        'exhibition_updated' => "Exposition «\u{202F}:title\u{202F}» enregistrée.",
        'exhibition_deleted' => "Exposition «\u{202F}:title\u{202F}» supprimée.",
        'portrait_updated' => 'Portrait de :name mis à jour.',
        'portrait_removed' => "Portrait de :name retiré\u{202F}: la page montre son œuvre phare ou son monogramme.",
        'exhibition_image' => "Visuel de «\u{202F}:title\u{202F}» mis à jour.",
    ],

    'errors' => [
        'migrate' => "Les pages d’artistes ont besoin d’une mise à jour de la base de données\u{202F}: cliquez sur «\u{202F}Mettre à jour la base de données\u{202F}».",
        'photo_required' => 'Choisissez une photo à envoyer, ou une photo de la photothèque.',
        'order' => "Ce nouvel ordre n’a pas pu être enregistré\u{202F}: rechargez la page, puis réessayez.",
        'example' => 'La page exemple n’a pas pu être créée. Réessayez dans un instant.',
        'photo' => [
            'not_image' => 'Ce fichier n’est pas une image lisible. Essayez une photo JPEG, PNG ou WebP.',
            'type' => 'Ce format n’est pas accepté. Envoyez une photo JPEG, PNG, WebP ou GIF.',
            'too_big' => 'Cette photo est trop lourde. Essayez une version plus légère.',
            'too_many_pixels' => 'Cette image est trop grande. Réduisez ses dimensions, puis réessayez.',
            'storage' => 'La photo n’a pas pu être enregistrée. Réessayez dans un instant.',
            'quota' => "La photothèque est pleine\u{202F}: supprimez des photos inutilisées avant d’en ajouter.",
            'interrupted' => 'L’envoi a été interrompu. Vérifiez votre connexion, puis réessayez.',
        ],
    ],

    // Messages des règles de validation propres à ces formulaires (les autres viennent de validation.php).
    'validation' => [
        'slug_regex' => "L’adresse ne peut contenir que des lettres minuscules, des chiffres et des tirets (par exemple\u{202F}: camille-durand).",
        'slug_unique' => "Cette adresse est déjà utilisée par un autre artiste\u{202F}: choisissez-en une autre.",
        'year_required' => 'Indiquez l’année, ou une date de début.',
        'starts_required' => 'Une date de fin demande une date de début.',
        'ends_after' => 'La date de fin ne peut pas précéder la date de début.',
        'https' => "Indiquez une adresse complète, commençant par https:// (par exemple\u{202F}: https://www.exemple.fr).",
    ],

    'activity' => [
        'created' => "Page d’artiste créée\u{202F}: :name",
        'updated' => "Page d’artiste modifiée\u{202F}: :name",
        'deleted' => "Page d’artiste supprimée\u{202F}: :name",
        'published' => "Page d’artiste publiée\u{202F}: :name",
        'hidden' => "Page d’artiste masquée\u{202F}: :name",
        'reordered' => '{1} Ordre des artistes modifié|[2,*] Ordre des :count artistes modifié',
        'example' => "Page exemple créée\u{202F}: :name",
        'artwork_created' => "Œuvre ajoutée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'artwork_updated' => "Œuvre modifiée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'artwork_deleted' => "Œuvre supprimée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'artworks_reordered' => '{1} Ordre des œuvres modifié (:name)|[2,*] Ordre des :count œuvres modifié (:name)',
        'image_replaced' => "Photo d’œuvre remplacée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'exhibition_created' => "Exposition ajoutée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'exhibition_updated' => "Exposition modifiée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'exhibition_deleted' => "Exposition supprimée\u{202F}: «\u{202F}:title\u{202F}» (:name)",
        'portrait_updated' => "Portrait modifié\u{202F}: :name",
        'portrait_removed' => "Portrait retiré\u{202F}: :name",
        'exhibition_image' => "Visuel d’exposition modifié\u{202F}: «\u{202F}:title\u{202F}» (:name)",
    ],

    'usage' => [
        'portrait' => 'Portrait — :name (page d’artiste)',
        'artwork' => "Œuvre «\u{202F}:title\u{202F}» — :name",
        'exhibition' => "Exposition «\u{202F}:title\u{202F}» — :name",
    ],

    'attributes' => [
        'name' => 'nom de l’artiste',
        'slug' => 'adresse de la page',
        'discipline_fr' => 'discipline (français)',
        'discipline_en' => 'discipline (anglais)',
        'location' => 'lieu de vie',
        'accent' => 'couleur d’accent',
        'website' => 'site web',
        'instagram' => 'Instagram',
        'statement_fr' => 'phrase d’intention (français)',
        'statement_en' => 'phrase d’intention (anglais)',
        'bio_fr' => 'biographie (français)',
        'bio_en' => 'biographie (anglais)',
        'meta_fr' => 'description pour Google (français)',
        'meta_en' => 'description pour Google (anglais)',
        'portrait_media_id' => 'portrait',
        'is_published' => 'publication',
        'photo' => 'photo',
        'media_id' => 'photo',
        'original_name' => 'nom du fichier',
        'title_fr' => 'titre (français)',
        'title_en' => 'titre (anglais)',
        'year' => 'année',
        'medium_fr' => 'technique (français)',
        'medium_en' => 'technique (anglais)',
        'dimensions' => 'dimensions',
        'description_fr' => 'texte (français)',
        'description_en' => 'texte (anglais)',
        'availability' => 'disponibilité',
        'kind' => 'type d’exposition',
        'venue' => 'lieu',
        'city' => 'ville',
        'starts_on' => 'date de début',
        'ends_on' => 'date de fin',
        'url' => "lien «\u{202F}En savoir plus\u{202F}»",
        'order' => 'ordre',
    ],

];
