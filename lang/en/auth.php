<?php

return [
    'app_title' => 'Sign in',

    'title'       => 'Sign in',
    'sub'         => 'Welcome back to :name. Use your account email and password.',
    'brand_sub'   => 'Back office',

    'email'         => 'Email',
    'password'      => 'Password',
    'show_password' => 'Show password',
    'hide_password' => 'Hide password',
    'remember_me'   => 'Remember me',
    'sign_in'      => 'Sign in',
    'signing_in'   => 'Signing in…',
    'forgot'       => 'Forgot password?',
    'forgot_coming_soon' => 'Password reset will arrive in a future release.',

    'password_reset' => [
        'request_title'           => 'Reset your password',
        'request_sub'             => 'Enter the email you sign in with. We\'ll send you a link to reset your password.',
        'reset_title'             => 'Set a new password',
        'reset_sub'               => 'Pick a strong password — at least 8 characters.',
        'send_link'               => 'Send reset link',
        'update_password'         => 'Update password',
        'new_password'            => 'New password',
        'confirm_password'        => 'Confirm new password',
        'back_to_login'           => 'Back to sign in',
        'link_sent_or_unknown'    => 'If an account exists for that email, we\'ve sent a password-reset link. Check your inbox (and spam folder).',
        'success'                 => 'Password updated — you can sign in with your new password now.',
    ],

    'failed'   => 'These credentials don\'t match our records.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'pin_sub'              => 'Enter your 6-digit PIN.',
    'pin_invalid'          => 'That PIN wasn\'t recognized.',
    'use_pin_instead'      => 'Use PIN instead',
    'use_password_instead' => 'Use email & password instead',

    'toggle_theme' => 'Toggle theme',

    'footer' => [
        'help'    => 'Trouble signing in?',
        'contact' => 'Contact support',
    ],

    'demo' => [
        'notice'    => 'If you can\'t login to the demo, please :link to visit our website.',
        'link_text' => 'click here',

        'credentials_title' => 'Demo Credentials',
        'mode_badge'        => 'Demo Mode',
        'copy_and_fill'     => 'Copy & Fill',
        'filled'            => 'Filled',
        'role' => [
            'admin'   => 'Admin Credentials',
            'cashier' => 'Cashier Credentials',
        ],
        'email_label'    => 'Email',
        'password_label' => 'Password',
    ],
];
