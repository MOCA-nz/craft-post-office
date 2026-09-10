<?php

/**
 * Pins the test database before Craft boots.
 *
 * App::env() reads $_SERVER before $_ENV and getenv(), and DDEV exports CRAFT_DB_* into
 * $_SERVER, so setting only putenv() leaves the DDEV value winning and the suite runs against
 * the development database.
 */

use craft\helpers\App;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$pins = [
    'CRAFT_DB_DATABASE' => 'db_test',
    'CRAFT_DB_TABLE_PREFIX' => '',
];

foreach ($pins as $name => $value) {
    $_SERVER[$name] = $value;
    $_ENV[$name] = $value;
    putenv("$name=$value");
}

// Fail closed. A pin can be bypassed by a new invocation path or a CI runner with different
// env precedence, and the failure mode is silent writes to a live install. Check the
// resolved value, not the one just set, so paths where the pin did not take are caught.
$database = App::env('CRAFT_DB_DATABASE');

if ($database !== 'db_test') {
    fwrite(STDERR, "ABORT: tests resolved database '$database', expected 'db_test'.\n");
    exit(1);
}

// Boot Craft here rather than relying on craft-pest's Pest plugin firing. The root `Craft`
// class is not PSR-4 autoloaded (craftcms/cms only maps `craft\\`), so it exists only after
// this bootstrap runs. InstallsCraft checks for CRAFT_BASE_PATH and skips its own boot when
// it is already defined, so this cooperates rather than duplicating work.
require_once dirname(__DIR__) . '/vendor/markhuot/craft-pest-core/src/bootstrap/bootstrap.php';

// Craft's init just set the process timezone from the install config, which is
// America/Los_Angeles on a fresh install. Re-pin so DateTimes in tests match stored UTC.
date_default_timezone_set('UTC');

// craft-pest boots Craft but never installs the plugin under test. Idempotent, and it writes
// to project config, so it has to run here rather than in a test hook inside a rolled-back
// transaction.
$plugins = Craft::$app->getPlugins();

if (!$plugins->isPluginInstalled('post-office')) {
    $plugins->installPlugin('post-office');
}
