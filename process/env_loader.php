<?php
/**
 * Simple .env File Loader
 * 
 *
 * Parses KEY=VALUE lines from a .env file and sets them
 * via putenv() and $_ENV. No external libraries required.
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split on first '=' only
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}
