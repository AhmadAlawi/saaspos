{{--
    Renders every terminal row. Used by:
      - the initial page render (index.blade.php)
      - TerminalController's JSON response after any save/delete.

    Required variables:
      - $terminals  Collection<Terminal>  ordered, with `store`
--}}
@foreach ($terminals as $t)
    @include('admin.terminals._row', ['t' => $t])
@endforeach
