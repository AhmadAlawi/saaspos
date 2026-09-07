<?php

namespace App\Actions\Installer;

/**
 * Runs server-side environment checks per docs/features/installer.md §3 Step 2.
 *
 * Returns a list of checks: each has a label, status (pass / warn / fail),
 * a current value, and a hint when it fails.
 */
class CheckRequirements
{
    /**
     * @return array{checks: list<array<string, mixed>>, can_continue: bool}
     */
    public function __invoke(): array
    {
        $checks = array_merge(
            $this->phpVersion(),
            $this->phpExtensions(),
            $this->phpSettings(),
            $this->folderPermissions(),
        );

        $canContinue = ! collect($checks)
            ->where('required', true)
            ->contains('status', 'fail');

        return ['checks' => $checks, 'can_continue' => $canContinue];
    }

    /** @return list<array<string, mixed>> */
    private function phpVersion(): array
    {
        return [[
            'group'    => 'PHP',
            'label'    => 'PHP version ≥ 8.2',
            'value'    => PHP_VERSION,
            'status'   => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'pass' : 'fail',
            'required' => true,
            'hint'     => 'Upgrade PHP to 8.2 or newer. Most shared hosts let you switch PHP versions in cPanel.',
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function phpExtensions(): array
    {
        $required    = ['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'json', 'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'intl'];
        $oneOf       = ['gd', 'imagick'];
        // `zip` temporarily demoted to recommended — see TODO above. Required for
        // shipping (auto-updater unpacks update.zip, OpenSpout reads xlsx as zip).
        // Re-promote once Apache's php.ini is sorted out.
        $recommended = ['iconv', 'mysqli', 'simplexml', 'xmlwriter', 'zip'];

        $rows = [];

        foreach ($required as $ext) {
            $loaded = extension_loaded($ext);
            $rows[] = [
                'group'    => 'PHP extensions',
                'label'    => $ext,
                'value'    => $loaded ? 'loaded' : 'missing',
                'status'   => $loaded ? 'pass' : 'fail',
                'required' => true,
                'hint'     => $loaded ? null : "Enable the {$ext} extension in php.ini (uncomment extension={$ext} or contact your host).",
            ];
        }

        $imageExtLoaded = array_filter($oneOf, fn ($e) => extension_loaded($e));
        $rows[] = [
            'group'    => 'PHP extensions',
            'label'    => 'gd or imagick',
            'value'    => $imageExtLoaded ? implode(', ', $imageExtLoaded) : 'none',
            'status'   => $imageExtLoaded ? 'pass' : 'fail',
            'required' => true,
            'hint'     => $imageExtLoaded ? null : 'Enable either the gd or imagick extension for image handling.',
        ];

        foreach ($recommended as $ext) {
            $loaded = extension_loaded($ext);
            $rows[] = [
                'group'    => 'PHP extensions',
                'label'    => $ext,
                'value'    => $loaded ? 'loaded' : 'missing',
                'status'   => $loaded ? 'pass' : 'warn',
                'required' => false,
                'hint'     => $loaded ? null : "Recommended but not required. Enable {$ext} for best behavior.",
            ];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function phpSettings(): array
    {
        $memoryBytes = $this->iniBytes('memory_limit');
        $maxExec     = (int) ini_get('max_execution_time');
        $uploadMax   = $this->iniBytes('upload_max_filesize');
        $postMax     = $this->iniBytes('post_max_size');

        return [
            [
                'group'    => 'PHP settings',
                'label'    => 'memory_limit ≥ 128M',
                'value'    => ini_get('memory_limit'),
                'status'   => $memoryBytes === -1 || $memoryBytes >= 128 * 1024 * 1024 ? 'pass' : 'fail',
                'required' => true,
                'hint'     => 'Set memory_limit=256M in php.ini.',
            ],
            [
                'group'    => 'PHP settings',
                'label'    => 'max_execution_time ≥ 60',
                'value'    => $maxExec === 0 ? 'unlimited' : (string) $maxExec,
                'status'   => $maxExec === 0 || $maxExec >= 60 ? 'pass' : 'fail',
                'required' => true,
                'hint'     => 'Set max_execution_time=120 in php.ini for migrations and large imports.',
            ],
            [
                'group'    => 'PHP settings',
                'label'    => 'upload_max_filesize ≥ 32M',
                'value'    => ini_get('upload_max_filesize'),
                'status'   => $uploadMax >= 32 * 1024 * 1024 ? 'pass' : 'warn',
                'required' => false,
                'hint'     => 'Set upload_max_filesize=32M (or higher) for product imports and image uploads.',
            ],
            [
                'group'    => 'PHP settings',
                'label'    => 'post_max_size ≥ 32M',
                'value'    => ini_get('post_max_size'),
                'status'   => $postMax >= 32 * 1024 * 1024 ? 'pass' : 'warn',
                'required' => false,
                'hint'     => 'Set post_max_size=32M (or higher).',
            ],
            [
                'group'    => 'PHP settings',
                'label'    => 'file_uploads = On',
                'value'    => ini_get('file_uploads') ? 'On' : 'Off',
                'status'   => ini_get('file_uploads') ? 'pass' : 'fail',
                'required' => true,
                'hint'     => 'Set file_uploads=On in php.ini.',
            ],
            [
                'group'    => 'PHP settings',
                'label'    => 'allow_url_fopen = On',
                'value'    => ini_get('allow_url_fopen') ? 'On' : 'Off',
                'status'   => ini_get('allow_url_fopen') ? 'pass' : 'fail',
                'required' => true,
                'hint'     => 'Set allow_url_fopen=On for license verification and updates.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function folderPermissions(): array
    {
        // Directories we'll attempt to create if missing — only the ones it's safe
        // for the installer to auto-provision (not storage/ or bootstrap/cache/,
        // those must already exist as part of the Laravel app skeleton).
        $autoCreate = ['public/uploads' => public_path('uploads')];

        foreach ($autoCreate as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }

        $paths = [
            'storage'              => storage_path(),
            'bootstrap/cache'      => base_path('bootstrap/cache'),
            'public/uploads'       => public_path('uploads'),
            '.env (file or dir)'   => base_path('.env'),
        ];

        $rows = [];
        foreach ($paths as $label => $path) {
            // For .env we accept either an existing writable file OR a writable parent dir.
            $writable = $label === '.env (file or dir)'
                ? (file_exists($path) ? is_writable($path) : is_writable(dirname($path)))
                : (is_dir($path) && is_writable($path));

            $rows[] = [
                'group'    => 'Folder permissions',
                'label'    => $label.' writable',
                'value'    => $writable ? 'writable' : 'not writable',
                'status'   => $writable ? 'pass' : 'fail',
                'required' => true,
                'hint'     => 'chmod 775 '.$path.' (or use your host\'s File Manager to set write permission).',
            ];
        }

        return $rows;
    }

    private function iniBytes(string $key): int
    {
        $value = ini_get($key);
        if ($value === false || $value === '') {
            return 0;
        }
        if ($value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $num  = (int) $value;

        return match ($unit) {
            'g'     => $num * 1024 ** 3,
            'm'     => $num * 1024 ** 2,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
