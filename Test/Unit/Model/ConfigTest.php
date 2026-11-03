<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Model;

use BlauesKreuz\AgeVerificationMrz\Block\Adminhtml\System\Config\Donation;
use BlauesKreuz\AgeVerificationMrz\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const PATH = 'blaueskreuz_age_verification/general/';

    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    /**
     * Sets the values of the youth-protection settings.
     */
    private function configure(bool $shown, ?string $text): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturnMap([
            [self::PATH . 'show_youth_message', ScopeInterface::SCOPE_STORE, null, $shown],
        ]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            [self::PATH . 'youth_message', ScopeInterface::SCOPE_STORE, null, $text],
        ]);
    }

    public function testDefaultTextIsUsedWhenTheFieldIsEmpty(): void
    {
        $this->configure(true, '');
        $this->assertSame('Together for youth protection!', $this->config->getYouthMessage());
    }

    public function testDefaultTextIsUsedWhenNothingIsSet(): void
    {
        $this->configure(true, null);
        $this->assertSame('Together for youth protection!', $this->config->getYouthMessage());
    }

    public function testDefaultTextIsUsedForWhitespaceOnly(): void
    {
        $this->configure(true, "  \n ");
        $this->assertSame('Together for youth protection!', $this->config->getYouthMessage());
    }

    public function testTextOfTheShopIsShownAsItIs(): void
    {
        $this->configure(true, '  Be 18. Be safe.  ');
        $this->assertSame('Be 18. Be safe.', $this->config->getYouthMessage());
    }

    #[DataProvider('switchedOffProvider')]
    public function testNothingIsShownWhenSwitchedOff(?string $text): void
    {
        $this->configure(false, $text);
        $this->assertFalse($this->config->isYouthMessageShown());
        $this->assertSame('', $this->config->getYouthMessage());
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function switchedOffProvider(): array
    {
        return ['no text' => [''], 'own text' => ['Be 18. Be safe.']];
    }

    public function testDonationLinkPointsToTheBlueCrossPage(): void
    {
        $this->assertSame('https://blaueskreuz.ch/spende', Donation::URL);
    }
}
