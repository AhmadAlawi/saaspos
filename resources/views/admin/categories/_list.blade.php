{{--
    Renders every category row in tree order. Used by both:
      - the initial page render (index.blade.php)
      - CategoryController's JSON response after any save/delete,
        so the client can refresh the whole list in one swap.

    Required variables:
      - $categories     Collection<Category> in tree order, each with `tree_depth` set
      - $taxGroupNames  Collection<int,string> id → name lookup
--}}
@foreach ($categories as $c)
    @include('admin.categories._row', [
        'c'             => $c,
        'parentName'    => $categories->firstWhere('id', $c->parent_id)?->name,
        'taxGroupNames' => $taxGroupNames,
    ])
@endforeach
