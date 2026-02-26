<?php declare(strict_types=1);

$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

foreach ($autoloadCandidates as $autoloadFile) {
    if (is_file($autoloadFile)) {
        require_once $autoloadFile;

        return;
    }
}

fwrite(STDERR, "FibOutboxBridge phpstan-bootstrap: no vendor/autoload.php found.\n");
