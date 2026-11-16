<?php

declare(strict_types=1);

// Laravel Installer v5.33+ generates APP_KEY explicitly after create-project.
// Composer's post-create-project-cmd still runs for custom starter kits, so
// generating the key here as well would rotate it unnecessarily.
if (getenv('LARAVEL_INSTALLER_DEFER_HOOKS') === '1') {
    return;
}

$exitCode = 0;
passthru(escapeshellarg(PHP_BINARY) . ' artisan key:generate --ansi', $exitCode);

exit($exitCode);
