{{--
    Renders every unit row.

    Required variables:
      - $units          Collection<Unit>  ordered (with baseUnit eager-loaded if shown)
      - $baseUnitNames  Collection<int, string>  id → "Name (code)" lookup
--}}
@foreach ($units as $u)
    @include('admin.units._row', [
        'u'             => $u,
        'baseUnitNames' => $baseUnitNames,
    ])
@endforeach
