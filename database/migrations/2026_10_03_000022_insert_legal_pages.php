<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Two free pages the site needs, ready to fill in (docs/CMS.md §13 F28): "Mentions légales"
     * (/mentions-legales) and "Politique de confidentialité" (/confidentialite), with French and
     * English skeleton headings and hints. Created unpublished — nothing appears on the site until
     * the owner completes and publishes them — and only when their slug is free.
     */
    public function up(): void
    {
        $now = now();

        foreach ($this->pages() as $position => $page) {
            if (DB::table('custom_pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            DB::table('custom_pages')->insert($page + [
                'is_published' => false,
                'in_footer' => true,
                'position' => 90 + $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Pages are content: the owner may have completed them, so they are never deleted here. */
    public function down(): void {}

    /**
     * @return list<array<string, string>>
     */
    private function pages(): array
    {
        return [
            [
                'slug' => 'mentions-legales',
                'title_fr' => 'Mentions légales',
                'title_en' => 'Legal notice',
                'meta_fr' => 'Mentions légales du site des Ateliers Pehouet : éditeur, hébergement et propriété intellectuelle.',
                'meta_en' => 'Legal notice of the Ateliers Pehouet website: publisher, hosting and intellectual property.',
                'body_fr' => <<<'MD'
                    ## Éditeur du site

                    *À compléter : nom de la structure, forme juridique, adresse, numéro d’immatriculation, e-mail et téléphone de contact.*

                    ## Directeur de la publication

                    *À compléter : nom de la personne responsable des contenus du site.*

                    ## Hébergement

                    *À compléter : nom, adresse et coordonnées de l’hébergeur du site.*

                    ## Propriété intellectuelle

                    *À compléter : à qui appartiennent les œuvres, les photos et les textes de ce site, et à qui demander l’autorisation de les reproduire.*

                    ## Contact

                    *À compléter : comment joindre l’atelier pour toute question sur ce site.*
                    MD,
                'body_en' => <<<'MD'
                    ## Website publisher

                    *To be completed: name of the organisation, legal form, address, registration number, contact e-mail and phone.*

                    ## Publication director

                    *To be completed: name of the person responsible for the content of the website.*

                    ## Hosting

                    *To be completed: name, address and contact details of the website host.*

                    ## Intellectual property

                    *To be completed: who owns the artworks, photos and texts of this website, and whom to ask for permission to reproduce them.*

                    ## Contact

                    *To be completed: how to reach the atelier about this website.*
                    MD,
            ],
            [
                'slug' => 'confidentialite',
                'title_fr' => 'Politique de confidentialité',
                'title_en' => 'Privacy policy',
                'meta_fr' => 'Comment les Ateliers Pehouet traitent les données personnelles envoyées depuis ce site.',
                'meta_en' => 'How Ateliers Pehouet handles the personal data sent through this website.',
                'body_fr' => <<<'MD'
                    ## Responsable du traitement

                    *À compléter : qui collecte les données (nom de la structure, adresse, e-mail de contact).*

                    ## Données collectées

                    *À compléter : par exemple les informations envoyées avec le formulaire de contact (nom, e-mail, téléphone, message).*

                    ## Utilisation des données

                    *À compléter : à quoi servent ces informations, par exemple répondre aux demandes de devis et aux messages.*

                    ## Durée de conservation

                    *À compléter : combien de temps les messages sont gardés.*

                    ## Vos droits

                    *À compléter : comment demander l’accès, la correction ou la suppression de vos données.*

                    ## Cookies et stockage du navigateur

                    *À compléter : par exemple le cookie de session du site (langue choisie, formulaire de contact) et les préférences d’animation gardées dans le navigateur.*
                    MD,
                'body_en' => <<<'MD'
                    ## Data controller

                    *To be completed: who collects the data (name of the organisation, address, contact e-mail).*

                    ## Data collected

                    *To be completed: for example the details sent with the contact form (name, e-mail, phone, message).*

                    ## How the data is used

                    *To be completed: what this information is for, for example answering quote requests and messages.*

                    ## Retention period

                    *To be completed: how long messages are kept.*

                    ## Your rights

                    *To be completed: how to request access to, correction or deletion of your data.*

                    ## Cookies and browser storage

                    *To be completed: for example the website's session cookie (chosen language, contact form) and the animation preferences kept in the browser.*
                    MD,
            ],
        ];
    }
};
