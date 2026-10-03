<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Fixtures;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use RoundlyConsulting\Advertisements\Tests\TestCase;

/**
 * The base case for `tests/Publish` — the suite with `config/` pointed at a throwaway
 * directory per test, set before the providers boot (their publish destinations are fixed
 * then). Pest binds a test case per DIRECTORY, not per file, hence the directory.
 *
 * Publishing into the shared testbench skeleton raced the parallel suite: every other
 * process lists `laravel/config/*.php` and requires each file while it boots, so one that
 * was half-written or just deleted failed whichever unrelated test happened to be booting
 * ("Path must not be empty" in `LoadConfiguration`).
 *
 * @see TestCase
 */
abstract class PublishSandboxTestCase extends TestCase
{
    private string $sandbox = '';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->sandbox = sys_get_temp_dir().'/advertisements-publish-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->sandbox.'/config');

        $app->useConfigPath($this->sandbox.'/config');
    }

    protected function tearDown(): void
    {
        if ($this->sandbox !== '') {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }
}
