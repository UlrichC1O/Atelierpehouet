<h1>{{ $service['title'] }}</h1>
<p>locale={{ app()->getLocale() }} prev={{ $prev['slug'] }} next={{ $next['slug'] }} related={{ $related->pluck('slug')->join(',') }} inspirations={{ count($inspirations) }}</p>
