<?php

use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;

/**
 * Pest loads this file during test discovery, before PHPUnit's bootstrap runs, so nothing
 * here may touch Craft. Booting Craft and installing the plugin happen in tests/bootstrap.php.
 */

// RefreshesDatabase is NOT part of TestCase. Without it every write in the suite commits
// permanently to whatever database Craft booted against, and the tests still pass.
uses(TestCase::class, RefreshesDatabase::class)->in(__DIR__);
