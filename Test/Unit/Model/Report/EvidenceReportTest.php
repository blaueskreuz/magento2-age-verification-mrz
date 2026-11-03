<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Model\Report;

use BlauesKreuz\AgeVerificationMrz\Model\Report\EvidenceReport;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EvidenceReportTest extends TestCase
{
    private EvidenceReport $report;

    protected function setUp(): void
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturnCallback(
            fn ($value) => $value instanceof \DateTime ? \DateTimeImmutable::createFromMutable($value)
                : new \DateTimeImmutable('@' . $value)
        );
        $this->report = new EvidenceReport($this->createMock(CollectionFactory::class), $timezone);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function datesProvider(): array
    {
        return [
            'a date' => ['2026-11-03', true],
            'leap day' => ['2028-02-29', true],
            'not a leap year' => ['2026-02-29', false],
            'month 13' => ['2026-13-01', false],
            'day 45' => ['2026-01-45', false],
            'empty' => ['', false],
            'text' => ['yesterday', false],
            'other format' => ['03.11.2026', false],
            'with time' => ['2026-11-03 10:00', false],
            'injection' => ["2026-11-03' OR 1=1", false],
        ];
    }

    #[DataProvider('datesProvider')]
    public function testOnlyRealDatesAreAccepted(string $value, bool $expected): void
    {
        $this->assertSame($expected, $this->report->isDate($value));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function order(array $data): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getData')->willReturnCallback(fn ($key) => $data[$key] ?? null);
        $order->method('getIncrementId')->willReturn($data['increment_id']);
        $order->method('getCreatedAt')->willReturn($data['created_at']);
        $order->method('getCustomerName')->willReturn($data['name']);
        $order->method('getCustomerEmail')->willReturn($data['email']);

        return $order;
    }

    public function testRowOfAnOrderWithEvidence(): void
    {
        $row = $this->report->getRow($this->order([
            'increment_id' => '000000005', 'created_at' => '2026-11-03 08:37:00', 'name' => 'Age Tester',
            'email' => 'agetest@example.com', 'mrz_required_age' => '18', 'mrz_verified_age' => '26',
            'mrz_verified_date' => '1793690151', 'mrz_verified_method' => 'account_checkout',
        ]));
        $this->assertCount(count($this->report->getHeadings()), $row);
        $this->assertSame('000000005', $row[0]);
        $this->assertSame('Age Tester', $row[2]);
        $this->assertSame('18', $row[4]);
        $this->assertSame('26', $row[5]);
        $this->assertSame('Earlier checkout, saved on the account', $row[7]);
        $this->assertSame('Verified', $row[8]);
    }

    public function testRowOfAnOrderWithoutEvidence(): void
    {
        $row = $this->report->getRow($this->order([
            'increment_id' => '000000009', 'created_at' => '2026-11-03 08:37:00', 'name' => ' ', 'email' => null,
            'mrz_required_age' => '21',
        ]));
        $this->assertSame(['', '', '', 'No evidence'], [$row[2], $row[5], $row[6], $row[8]]);
        $this->assertSame('', $row[7]);
    }

    public function testRowOfAnExemptOrder(): void
    {
        $row = $this->report->getRow($this->order([
            'increment_id' => '000000010', 'created_at' => '2026-11-03 08:37:00', 'name' => 'Wholesale Buyer',
            'email' => 'w@example.com', 'mrz_required_age' => '18', 'mrz_verified_method' => 'bypass',
        ]));
        $this->assertSame('Exempt', $row[8]);
        $this->assertSame('Exempt customer group, not asked', $row[7]);
    }
}
