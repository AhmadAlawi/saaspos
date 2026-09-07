{{--
    Renders every brand row. Used by:
      - the initial page render (index.blade.php)
      - BrandController's JSON response after any save/delete.

    Required variables:
      - $brands  Collection<Brand>  ordered
--}}
@foreach ($brands as $b)
    @include('admin.brands._row', ['b' => $b])
@endforeach
