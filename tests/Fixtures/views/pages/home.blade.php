<h1>home</h1>
<p>locale={{ app()->getLocale() }} services={{ $services->pluck('slug')->join(',') }} categories={{ implode('|', array_keys($categories)) }} preview={{ count($galleryPreview) }} animations={{ $animationCount }} styles={{ implode('|', $artStyles) }}</p>
@include('partials.header')
