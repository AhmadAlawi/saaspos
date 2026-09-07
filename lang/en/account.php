<?php

return [

    'title' => 'Profile & preferences',
    'sub'   => 'Manage your account details, language, and password.',

    'sections' => [
        'profile'         => 'Profile',
        'profile_sub'     => 'Your name, contact details, and photo.',
        'preferences'     => 'Preferences',
        'preferences_sub' => 'How the admin behaves for you.',
        'password'        => 'Password',
        'password_sub'    => 'Change the password you sign in with.',
    ],

    'fields' => [
        'avatar'             => 'Profile photo',
        'avatar_hint'        => 'Square images look best. Max 8 MB.',
        'name'               => 'Full name',
        'email'              => 'Email',
        'phone'              => 'Phone',
        'language'           => 'Language',
        'language_system'    => 'System default',
        'default_store'      => 'Default store',
        'default_store_none' => 'No default (ask each time)',
        'current_password'   => 'Current password',
        'new_password'       => 'New password',
        'confirm_password'   => 'Confirm new password',
    ],

    'actions' => [
        'save'            => 'Save changes',
        'change_password' => 'Change password',
    ],

    'flash' => [
        'updated'          => 'Profile updated.',
        'password_updated' => 'Password changed.',
    ],

    'errors' => [
        'store_not_accessible' => "You don't have access to that store.",
        'locale_invalid'       => 'That language is not available.',
    ],

];
