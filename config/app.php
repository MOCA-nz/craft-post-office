<?php

/**
 * Test-run application config.
 */

return [
    'components' => [
        // A real queue would leave jobs behind between tests. Sync runs them inline, which
        // is also what craft-pest's queue assertions expect.
        'queue' => [
            'class' => \yii\queue\sync\Queue::class,
        ],
    ],
];
