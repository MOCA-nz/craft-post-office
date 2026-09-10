<?php

/**
 * Test-run config.
 *
 * craft-pest sets CRAFT_BASE_PATH to the current working directory, so a plugin repo needs
 * this minimal skeleton to boot Craft for its own suite. It is export-ignored, so it never
 * reaches a dist archive.
 */

return [
    'devMode' => true,
    'securityKey' => 'post-office-test-security-key',
    // Pinned to UTC so datetimes in tests match what Craft stores. Craft's own install
    // migration seeds America/Los_Angeles, which shifts every comparison by the offset.
    'timezone' => 'UTC',
    'allowAdminChanges' => true,
];
