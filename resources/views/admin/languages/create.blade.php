<x-admin-layout
    active="languages"
    :title="__('languages.create.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('languages.crumb_parent')],
        ['label' => __('languages.title'), 'href' => route('admin.languages.index')],
        ['label' => __('languages.actions.new')],
    ]">

    @include('admin.languages._form', ['mode' => 'create', 'language' => null])
</x-admin-layout>
