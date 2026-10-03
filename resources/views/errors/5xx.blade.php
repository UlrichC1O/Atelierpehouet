{{-- Any server error without a page of its own (502, 504…): the self-contained 500 page. --}}
@include('errors.minimal', ['code' => '500'])
