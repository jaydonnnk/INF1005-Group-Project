<?php
/**
 * env_loader.php — Simple .env File Loader
 * The Rolling Dice - Board Game Cafe
 * INF1005 Web Systems and Technologies
 *
 * Parses KEY=VALUE lines from a .env file and sets them
 * via putenv() and $_ENV. No external libraries required.
 */

/**
 * Load environment variables from a .env file into putenv() and $_ENV.
 *
 * @param string $path Absolute path to the .env file
 * @return void
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
