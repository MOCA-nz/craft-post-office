<?php

use moca\capture\services\ValueTemplate;

it('recognises what it would act on', function() {
    $t = new ValueTemplate();

    expect($t->isTemplated('{{ agent }}'))->toBeTrue()
        ->and($t->isTemplated('{{agent}}'))->toBeTrue()
        ->and($t->isTemplated('sales@example.test'))->toBeFalse()
        ->and($t->isTemplated(null))->toBeFalse()
        ->and($t->isTemplated('{{}}'))->toBeFalse();
});
