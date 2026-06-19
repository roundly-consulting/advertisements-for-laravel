<?php

declare(strict_types=1);

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('uses strict types everywhere')
    ->expect('RoundlyConsulting\Advertisements')
    ->toUseStrictTypes();

arch('keeps actions final')
    ->expect('RoundlyConsulting\Advertisements\Actions')
    ->classes->toBeFinal();

arch('keeps jobs final')
    ->expect('RoundlyConsulting\Advertisements\Jobs')
    ->classes->toBeFinal();

arch('keeps testing helpers final')
    ->expect('RoundlyConsulting\Advertisements\Testing')
    ->classes->toBeFinal();

arch('keeps support classes final')
    ->expect('RoundlyConsulting\Advertisements\Support')
    ->classes->toBeFinal();

arch('keeps concerns as traits')
    ->expect('RoundlyConsulting\Advertisements\Concerns')
    ->toBeTraits();
