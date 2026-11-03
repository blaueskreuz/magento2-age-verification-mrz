<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Model;

use BlauesKreuz\AgeVerificationMrz\Model\EvidenceStatus;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EvidenceStatusTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: ?int, 2: ?string, 3: EvidenceStatus}>
     */
    public static function ordersProvider(): array
    {
        return [
            'verified for exactly the age' => [18, 18, 'checkout', EvidenceStatus::Verified],
            'verified for more' => [18, 26, 'account_checkout', EvidenceStatus::Verified],
            'verified for less' => [18, 16, 'checkout', EvidenceStatus::NoEvidence],
            'nothing recorded' => [18, null, null, EvidenceStatus::NoEvidence],
            'age without method still counts' => [18, 21, null, EvidenceStatus::Verified],
            'exempt group' => [18, null, 'bypass', EvidenceStatus::Exempt],
            'exempt wins over a low age' => [21, 16, 'bypass', EvidenceStatus::Exempt],
        ];
    }

    #[DataProvider('ordersProvider')]
    public function testStatusOfAnOrder(int $required, ?int $age, ?string $method, EvidenceStatus $expected): void
    {
        $this->assertSame($expected, EvidenceStatus::fromOrder($required, $age, $method));
    }

    public function testEveryStatusHasALabel(): void
    {
        foreach (EvidenceStatus::cases() as $status) {
            $this->assertNotSame('', (string) $status->label());
        }
        $this->assertSame('No evidence', (string) EvidenceStatus::NoEvidence->label());
    }

    public function testAgeFromTheAccountKeepsHowItWasVerified(): void
    {
        $this->assertSame(VerificationMethod::AccountCheckout, VerificationMethod::Checkout->forOrderFromAccount());
        $this->assertSame(VerificationMethod::AccountAccount, VerificationMethod::Account->forOrderFromAccount());
        $this->assertSame(VerificationMethod::AccountManual, VerificationMethod::Manual->forOrderFromAccount());
        $this->assertSame(VerificationMethod::Bypass, VerificationMethod::Bypass->forOrderFromAccount());
        $this->assertSame(
            VerificationMethod::AccountCheckout,
            VerificationMethod::AccountCheckout->forOrderFromAccount()
        );
    }
}
