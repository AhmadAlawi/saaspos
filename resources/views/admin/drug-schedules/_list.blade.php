@foreach ($rows as $r)
    @include('admin.drug-schedules._row', ['r' => $r])
@endforeach
