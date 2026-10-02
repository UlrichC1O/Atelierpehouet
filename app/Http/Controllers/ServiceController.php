<?php

namespace App\Http\Controllers;

use App\Support\ServiceCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Lang;

/**
 * The services index and the 18 service pages.
 */
final class ServiceController extends Controller
{
    /** Generated artworks shown on each service page. */
    private const INSPIRATIONS = 3;

    private const ARTWORK_SIZE = 800;

    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function index(): View
    {
        return view('services.index', [
            'services' => $this->catalog->all(),
            'categories' => $this->catalog->categories(),
        ]);
    }

    public function show(string $slug): View
    {
        $service = $this->catalog->find($slug);

        abort_if($service === null, 404);

        $neighbors = $this->catalog->neighbors($slug);

        return view('services.show', [
            'service' => $service,
            'prev' => $neighbors['prev'],
            'next' => $neighbors['next'],
            'related' => $this->catalog->related($slug),
            'inspirations' => $this->inspirations($service),
        ]);
    }

    /**
     * Three artworks in the service's art style: the pre-rendered files from
     * `php artisan atelier:generate-art` when present, the live art endpoint otherwise.
     *
     * @param  array<string, mixed>  $service
     * @return list<array{src: string, style: string, seed: string, title: string, width: int, height: int}>
     */
    private function inspirations(array $service): array
    {
        $style = (string) $service['art_style'];
        $line = 'generator.styles.'.$style.'.name';
        $name = Lang::has($line) ? (string) __($line) : ucfirst($style);
        $artworks = [];

        for ($n = 1; $n <= self::INSPIRATIONS; $n++) {
            $seed = $service['slug'].'-'.$n;
            $file = 'generated/services/'.$seed.'.svg';

            $artworks[] = [
                'src' => is_file(public_path($file))
                    ? asset($file)
                    : route('generator.art', ['style' => $style, 'seed' => $seed, 'size' => self::ARTWORK_SIZE]),
                'style' => $style,
                'seed' => $seed,
                'title' => $name.' · '.$n,
                'width' => self::ARTWORK_SIZE,
                'height' => self::ARTWORK_SIZE,
            ];
        }

        return $artworks;
    }
}
