<?php

namespace App\Actions\Installer;

/**
 * Writes selected key/value pairs into the project's .env file.
 * Keys that already exist are updated in place; new keys are appended.
 */
class WriteEnvFile
{
    /** @param array<string, string|int|bool|null> $values */
    public function __invoke(array $values): void
    {
        $path = base_path('.env');
        $contents = file_exists($path) ? file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote((string) $value);

            if (preg_match("/^{$key}=.*/m", $contents)) {
                $contents = preg_replace("/^{$key}=.*/m", $line, $contents);
            } else {
                $contents .= "\n".$line;
            }
        }

        file_put_contents($path, $contents);
        @chmod($path, 0600);
    }

    private function quote(string $value): string
    {
        // Quote when the value contains whitespace or shell-special chars.
        return preg_match('/[\s#"\'\\\\]/', $value) ? '"'.addslashes($value).'"' : $value;
    }
}
