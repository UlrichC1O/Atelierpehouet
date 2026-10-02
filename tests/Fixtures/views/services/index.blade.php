<h1>services</h1>
<p>services={{ $services->pluck('slug')->join(',') }} categories={{ count($categories) }}</p>
