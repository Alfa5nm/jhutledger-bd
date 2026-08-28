<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$extensions = ['php', 'css', 'js'];
$maximumLineLength = 220;
$violations = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file->isFile() || !in_array($file->getExtension(), $extensions, true)) {
        continue;
    }
    if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $index => $line) {
        if (strlen($line) > $maximumLineLength) {
            $relativePath = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $violations[] = "{$relativePath}:" . ($index + 1) . ' exceeds 220 characters.';
        }
    }
}

if ($violations) {
    fwrite(STDERR, implode(PHP_EOL, $violations) . PHP_EOL);
    exit(1);
}

echo "PASS: PHP, CSS, and JavaScript lines stay within the project readability limit.\n";
