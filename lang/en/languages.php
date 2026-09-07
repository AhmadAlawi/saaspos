<?php

return [
    'title'        => 'Languages',
    'sub'          => 'Translate the app. Add a language, export the labels, translate them in Excel, and re-upload.',
    'crumb_parent' => 'Configure',

    'actions' => [
        'new'         => 'New language',
        'edit'        => 'Edit',
        'save'        => 'Save changes',
        'create'      => 'Create language',
        'delete'      => 'Delete',
        'cancel'      => 'Cancel',
        'export'      => 'Export',
        'import'      => 'Import',
        'auto_translate' => 'Auto-translate',
        'set_default' => 'Set as default',
        'activate'    => 'Activate',
        'deactivate'  => 'Deactivate',
    ],

    'create' => ['title' => 'New language', 'sub' => 'Add a locale. You can translate it after creating it.'],
    'edit'   => ['title' => 'Edit language', 'sub' => 'Update this language’s details.'],

    'fields' => [
        'code'           => 'Code',
        'code_help'      => 'ISO locale code — e.g. en, ar, es, pt-BR.',
        'name'           => 'Name (English)',
        'name_ph'        => 'e.g. Arabic',
        'native_name'    => 'Native name',
        'native_name_ph' => 'e.g. العربية',
        'direction'      => 'Text direction',
        'direction_ltr'  => 'Left-to-right (LTR)',
        'direction_rtl'  => 'Right-to-left (RTL)',
        'is_active'      => 'Active',
        'is_active_help' => 'Inactive languages are hidden from the switcher.',
        'import_file'    => 'Translation file (.xlsx or .csv)',
    ],

    'list' => [
        'title'       => 'All languages (:count)',
        'search_ph'   => 'Search languages…',
        'empty_title' => 'No languages yet',
        'empty_sub'   => 'Add a language to start translating.',
        'keys'        => ':count translatable strings',
    ],

    'columns' => [
        'language' => 'Language',
        'direction'=> 'Direction',
        'progress' => 'Translated',
        'status'   => 'Status',
        'actions'  => 'Actions',
    ],

    'badges' => [
        'default'  => 'Default',
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'rtl'      => 'RTL',
    ],

    'import' => [
        'title' => 'Import translations for “:name”',
        'help'  => 'Upload the exported file with the Translation column filled in. Empty cells keep the English default.',
    ],

    'delete' => [
        'title'   => 'Delete “:name”?',
        'message' => 'The language and all its translations are removed. This can’t be undone.',
    ],

    'flash' => [
        'created'     => 'Language “:name” created.',
        'updated'     => 'Language “:name” updated.',
        'deleted'     => 'Language “:name” deleted.',
        'default_set' => '“:name” is now the default language.',
        'activated'   => 'Language “:name” activated.',
        'deactivated' => 'Language “:name” deactivated.',
        'switched'    => 'Language changed to :name.',
        'imported'    => 'Imported :imported translation(s) for “:name” (:skipped skipped).',
        'auto_translate_started' => 'Auto-translating “:name” in the background — this can take a few minutes for a full catalog. Refresh this page to watch the progress bar fill in.',
    ],

    'errors' => [
        'base'           => 'Can’t delete “:name” — English is the base language.',
        'default'        => 'Can’t delete “:name” — it’s the default language. Set another default first.',
        'default_active' => 'Can’t deactivate “:name” — it’s the default language.',
    ],

    'switcher' => [
        'label'   => 'Language',
        'heading' => 'Choose language',
        'manage'  => 'Manage languages',
    ],
];
