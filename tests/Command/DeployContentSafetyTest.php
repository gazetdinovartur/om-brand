<?php

namespace App\Tests\Command;

use PHPUnit\Framework\TestCase;

/**
 * Контракт деплоя: кейсы и админские настройки не должны затираться
 * обычным deploy / content:sync / seed.
 */
final class DeployContentSafetyTest extends TestCase
{
    public function testOrdinaryDeployScriptDoesNotTouchCasesOrAdminSettings(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2).'/deploy-script.sh');

        self::assertStringNotContainsString('app:cases:seed', $script);
        self::assertStringContainsString('app:content:sync', $script);
        self::assertMatchesRegularExpression(
            '/elif \[\[ \$SYNC_CONTENT -eq 1 \]\][\s\S]*app:content:sync/',
            $script,
        );
        self::assertDoesNotMatchRegularExpression(
            '/^[^#]*\$CONSOLE app:content:sync/m',
            preg_replace('/elif \[\[ \$SYNC_CONTENT -eq 1 \]\][\s\S]*?fi/m', '', $script) ?? $script,
        );
        self::assertStringContainsString('Не перезаписывает кейсы', $script);
        self::assertStringContainsString('Настройки', $script);
    }

    public function testContentSyncCommandDoesNotTouchSiteSettingsOrCases(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Command/SyncContentCommand.php');

        self::assertStringNotContainsString('SiteSettings', $source);
        self::assertStringNotContainsString('CaseStudy', $source);
        self::assertStringNotContainsString('setName(', $source);
        self::assertStringNotContainsString('setTagline(', $source);
        self::assertStringNotContainsString('setCity(', $source);
        self::assertStringContainsString('ContentBlock', $source);
        self::assertStringContainsString('не трогает настройки, кейсы и хронику', $source);
    }

    public function testSeedCommandOnlyInsertsMissingLandingBlocksAndSettings(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Command/SeedCommand.php');

        self::assertStringContainsString("if (\$this->entityManager->getRepository(SiteSettings::class)->count([]) > 0)", $source);
        self::assertStringContainsString("if (null !== \$repository->findOneBy(['slug' => \$data['slug']]))", $source);
        self::assertStringContainsString('continue;', $source);
        self::assertStringNotContainsString('CaseStudy', $source);
    }

    public function testCasesSeedSkipsExistingUnlessForce(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Command/SeedModularCasesCommand.php');

        self::assertStringContainsString("if (null !== \$existing && !\$force)", $source);
        self::assertStringContainsString('continue;', $source);
        self::assertStringContainsString("'force'", $source);
    }
}
