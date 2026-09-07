<x-admin-layout
    active="languages"
    :title="__('languages.edit.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('languages.crumb_parent')],
        ['label' => __('languages.title'), 'href' => route('admin.languages.index')],
        ['label' => $language->name],
    ]">

    @include('admin.languages._form', ['mode' => 'edit', 'language' => $language])
</x-admin-layout>
