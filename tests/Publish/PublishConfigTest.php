<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Tests\Fixtures\PublishSandboxTestCase;

/**
 * Publishing runs against a throwaway config/ ({@see PublishSandboxTestCase}),
 * never the testbench skeleton the parallel suite loads its configuration from.
 */
it('publishes into the sandbox, never the shared skeleton', function (): void {
    expect(config_path('advertisements.php'))->toContain('advertisements-publish-');
});

it('publishes the config file', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'advertisements-config'])->assertSuccessful();

    expect(file_exists(config_path('advertisements.php')))->toBeTrue();
});
