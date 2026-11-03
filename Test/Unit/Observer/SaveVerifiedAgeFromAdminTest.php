<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Observer;

use BlauesKreuz\AgeVerificationMrz\Observer\SaveVerifiedAgeFromAdmin;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveVerifiedAgeFromAdminTest extends TestCase
{
    private const NOW = 1793700000;

    /** @var array<string, mixed> what ends up on the customer */
    private array $saved = [];

    /**
     * Runs the observer.
     *
     * @param bool $allowed whether the user may set the age
     * @param mixed $posted what the form sent
     * @param array<string, mixed> $stored what the customer has stored
     */
    private function saveAs(bool $allowed, mixed $posted, array $stored): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->with(SaveVerifiedAgeFromAdmin::RESOURCE)->willReturn($allowed);

        $model = $this->createMock(Customer::class);
        $model->method('getData')->willReturnCallback(fn ($key) => $stored[$key] ?? null);
        $registry = $this->createMock(CustomerRegistry::class);
        $registry->method('retrieve')->willReturn($model);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(self::NOW);

        $attribute = $this->createMock(AttributeInterface::class);
        $attribute->method('getValue')->willReturn($posted);
        /** @var CustomerInterface&MockObject $customer */
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(3);
        $customer->method('getCustomAttribute')->willReturn($attribute);
        $customer->method('setCustomAttribute')->willReturnCallback(
            function ($code, $value) use ($customer) {
                $this->saved[$code] = $value;

                return $customer;
            }
        );

        $event = new Event(['customer' => $customer]);
        $observer = new Observer(['event' => $event]);
        (new SaveVerifiedAgeFromAdmin($authorization, $registry, $dateTime))->execute($observer);
    }

    public function testStaffSetsAnAge(): void
    {
        $this->saveAs(true, '21', []);
        $this->assertSame(
            ['mrz_verified_age' => 21, 'mrz_verified_date' => self::NOW, 'mrz_verified_method' => 'manual'],
            $this->saved
        );
    }

    public function testSameAgeKeepsTheDateAndMethod(): void
    {
        $stored = ['mrz_verified_age' => '21', 'mrz_verified_date' => '1700000000', 'mrz_verified_method' => 'checkout'];
        $this->saveAs(true, '21', $stored);
        $this->assertSame(
            ['mrz_verified_age' => '21', 'mrz_verified_date' => '1700000000', 'mrz_verified_method' => 'checkout'],
            $this->saved
        );
    }

    public function testChangedAgeIsDatedNowAndMarkedAsSetByStaff(): void
    {
        $stored = ['mrz_verified_age' => '26', 'mrz_verified_date' => '1700000000', 'mrz_verified_method' => 'checkout'];
        $this->saveAs(true, '19', $stored);
        $this->assertSame(
            ['mrz_verified_age' => 19, 'mrz_verified_date' => self::NOW, 'mrz_verified_method' => 'manual'],
            $this->saved
        );
    }

    public function testSameAgeWithoutADateGetsOne(): void
    {
        $this->saveAs(true, '21', ['mrz_verified_age' => '21']);
        $this->assertSame(self::NOW, $this->saved['mrz_verified_date']);
        $this->assertSame('manual', $this->saved['mrz_verified_method']);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function emptyProvider(): array
    {
        return ['empty text' => [''], 'spaces' => ['  '], 'null' => [null]];
    }

    #[DataProvider('emptyProvider')]
    public function testEmptyFieldClearsEverything(mixed $posted): void
    {
        $stored = ['mrz_verified_age' => '26', 'mrz_verified_date' => '1700000000', 'mrz_verified_method' => 'checkout'];
        $this->saveAs(true, $posted, $stored);
        $this->assertSame(
            ['mrz_verified_age' => null, 'mrz_verified_date' => null, 'mrz_verified_method' => null],
            $this->saved
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'letters' => ['abc'], 'zero' => ['0'], 'too old' => ['100'], 'negative' => ['-5'],
            'decimal' => ['18.5'], 'text with number' => ['18 years'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidAgeIsRefused(string $posted): void
    {
        $this->expectException(LocalizedException::class);
        $this->saveAs(true, $posted, []);
    }

    public function testBoundariesAreAccepted(): void
    {
        $this->saveAs(true, '1', []);
        $this->assertSame(1, $this->saved['mrz_verified_age']);
        $this->saveAs(true, '99', []);
        $this->assertSame(99, $this->saved['mrz_verified_age']);
    }

    public function testUserWithoutPermissionCannotChangeAnything(): void
    {
        $stored = ['mrz_verified_age' => '26', 'mrz_verified_date' => '1700000000', 'mrz_verified_method' => 'checkout'];
        foreach (['99', '', 'abc'] as $posted) {
            $this->saved = [];
            $this->saveAs(false, $posted, $stored);
            $this->assertSame(
                ['mrz_verified_age' => '26', 'mrz_verified_date' => '1700000000', 'mrz_verified_method' => 'checkout'],
                $this->saved,
                'Posted: ' . $posted
            );
        }
    }

    public function testUserWithoutPermissionCannotSetAnAgeOnAnUnverifiedCustomer(): void
    {
        $this->saveAs(false, '21', []);
        $this->assertSame(
            ['mrz_verified_age' => null, 'mrz_verified_date' => null, 'mrz_verified_method' => null],
            $this->saved
        );
    }
}
