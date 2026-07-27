<?php

namespace App\Tests;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Keeps the test database schema aligned with Doctrine migrations.
 */
final class DatabaseSchema
{
    private static bool $ensured = false;

    public static function ensureUpToDate(): void
    {
        if (self::$ensured) {
            return;
        }

        $kernel = new Kernel('test', true);
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(
            new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]),
            new NullOutput(),
        );

        $kernel->shutdown();
        self::$ensured = true;
    }
}
