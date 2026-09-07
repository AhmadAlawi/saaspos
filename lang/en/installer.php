<?php

return [
    'title' => 'Installer',
    'subtitle' => 'First-run setup',
    'toggle_theme' => 'Toggle theme',
    'continue' => 'Continue',
    'back' => 'Back',
    'processing' => 'Processing…',
    'error_heading' => 'Please review the highlighted fields',
    'step_x_of_y' => 'Step :current of :total',

    'steps' => [
        'welcome' => 'Welcome',
        'requirements' => 'Requirements',
        'license' => 'Purchase code',
        'database' => 'Database',
        'admin' => 'Admin',
        'demo' => 'Demo data',
    ],

    'welcome' => [
        'heading' => 'Welcome',
        'subheading' => 'Pick your installer language. You can change this later in Settings.',
        'language_label' => 'Installer language',
    ],

    'requirements' => [
        'heading' => 'System requirements',
        'subheading' => 'We check your PHP version, extensions, and folder permissions before going further.',
        'recheck' => 'Re-check',
        'fix_required' => 'Fix the items above to continue.',
    ],

    'license' => [
        'heading' => 'Purchase code',
        'subheading' => 'Enter the purchase code from your CodeCanyon receipt to activate your copy.',
        'dev_notice_title' => 'Development mode',
        'dev_notice' => 'Developer bypass is ON (POS_LICENSE_DEV_BYPASS=true): any non-empty code is accepted and the validation server is not contacted. Turn it off in .env for real validation.',
        'key_label' => 'Purchase code',
        'key_hint' => 'Found on CodeCanyon under Downloads → License certificate & purchase code. The code is case-sensitive — avoid trailing spaces.',
        'validate' => 'Validate and continue',
        'validating' => 'Verifying purchase code…',
        'errors' => [
            'required'     => 'Purchase code is required.',
            'invalid'      => 'This purchase code is not valid for this product.',
            'unreachable'  => 'Could not reach the validation server. Please check your connection and try again.',
            'no_server'    => 'No validation server is configured.',
            'http'         => 'The validation server returned an error (HTTP :status). Please try again shortly.',
            'bad_response' => 'The validation server sent an unexpected response. Please try again.',
        ],
    ],

    'database' => [
        'heading' => 'Database connection',
        'subheading' => 'Enter your MySQL credentials. We will write them to .env, run migrations, and seed the lookup tables.',
        'host' => 'Host',
        'port' => 'Port',
        'name' => 'Database name',
        'name_hint' => 'The database must already exist with character set utf8mb4 and collation utf8mb4_unicode_ci.',
        'username' => 'Username',
        'password' => 'Password',
        'progress_title' => 'What happens next',
        'progress_notice' => 'After you continue we will test the connection and save your credentials. We then build the database tables on the next screen, a few at a time, so the install never times out on shared hosting.',
        'save_and_continue' => 'Test connection and continue',
        'running' => 'Testing connection…',
        'connection_failed' => 'Connection failed: :error',
        'unexpected_error' => 'Something went wrong testing the connection. Please try again.',
    ],

    'migrate' => [
        'heading' => 'Setting up the database',
        'subheading' => 'Building tables and seeding system data. This runs in small batches and resumes on its own — please keep this tab open.',
        'preparing' => 'Preparing…',
        'creating_tables' => 'Creating tables (:ran of :total)…',
        'seeding' => 'Seeding system data (:done of :total)…',
        'complete' => 'Database ready. Continuing…',
        'failed_title' => 'Setup hit a problem',
        'retry' => 'Retry',
    ],

    'admin' => [
        'heading' => 'Create your admin & company',
        'subheading' => 'Set up your login, your company details, and your first store — everything you need to start selling. You can add more stores, users, and details later.',
        'section_user' => 'Super-admin user',
        'section_company' => 'Company',
        'section_store' => 'First store',
        'name' => 'Full name',
        'email' => 'Email',
        'password' => 'Password',
        'password_hint' => 'Minimum 10 characters with at least one letter, one number, and one symbol.',
        'password_confirm' => 'Confirm password',
        'company_name' => 'Company name',
        'country' => 'Country',
        'currency' => 'Base currency',
        'timezone' => 'Default timezone',
        'industry' => 'Primary industry',
        'store_name' => 'Store name',
        'store_code' => 'Store code',
        'store_address' => 'Store address',
        'creating' => 'Creating admin and company…',
    ],

    'demo' => [
        'heading' => 'Seed demo data?',
        'subheading' => "Choose whether to populate your install with demo data. You can always wipe and reseed later.",
        'stub_notice_title' => 'Demo seeders not implemented yet',
        'stub_notice' => 'Your choice is recorded; the install will complete either way.',
        'finish' => 'Finish installation',
        'finishing' => 'Finishing up…',
        'options' => [
            'full' => [
                'title' => 'Full demo data',
                'description' => '~150 products, ~50 customers, ~30 suppliers, and 90 days of randomized sales. Dashboards look populated immediately.',
            ],
            'minimal' => [
                'title' => 'Minimal demo data',
                'description' => 'A starter catalogue of ~30 products and 5 customers. No historical sales.',
            ],
            'none' => [
                'title' => 'No demo data',
                'description' => 'Empty install. Add your real products and customers from scratch.',
            ],
        ],
    ],

    'complete' => [
        'welcome'             => 'Installation complete. Welcome.',
        'storage_link_failed' => 'Installed — but uploaded images may not display: this server would not let us create the public/storage link. Visit /storage-link, or ask your host to enable the symlink() function.',
    ],

    'footer' => [
        'need_help' => 'Need help?',
        'contact_support' => 'Contact support',
    ],

    'error' => [
        'heading'          => 'Something went wrong',
        'subheading'       => 'The installer hit an unexpected problem. Your site isn\'t broken — you can retry the step below.',
        'during'           => 'The installer hit a problem during the ":step" step. You can retry it below.',
        'unknown'          => 'An unexpected error occurred.',
        'migration_failed' => 'Setting up the database tables failed.',
        'seed_failed'      => 'Loading the initial data failed.',
        'diagnostic_label' => 'Diagnostic details',
        'diagnostic_hint'  => 'Copy this and include it if you contact support — it helps us pinpoint the problem fast.',
        'copy'             => 'Copy',
        'copied'           => 'Copied!',
        'retry'            => 'Retry this step',
        'start_over'       => 'Start over',
    ],

    'help' => [
        'open'  => 'Need help?',
        'title' => 'Installation help',
        'close' => 'Close',
        'blank_q'    => 'I see a blank page or "404 Not Found" after uploading',
        'blank_a'    => 'Your server needs URL rewriting. On Apache, make sure mod_rewrite is enabled and the site allows .htaccess overrides (AllowOverride All). On Nginx, add a try_files rule pointing to index.php. Your host can enable this in a minute.',
        'db_q'       => 'The database step says the connection failed',
        'db_a'       => 'Double-check the host (often "localhost" or "127.0.0.1"), database name, username and password from your hosting control panel. The database must already exist, and the user must have full privileges on it.',
        'rerun_q'    => 'How do I run the installer again?',
        'rerun_a'    => 'Delete the file storage/app/private/.install-locked on your server, then visit the site again. (This does not erase data — only re-opens the wizard.)',
        'license_q'  => 'Where do I find my license key?',
        'license_a'  => 'Open your CodeCanyon account, go to the Downloads section, and download the License certificate for this item. The Purchase code inside that file is your license key — paste it on the license step.',
    ],
];
