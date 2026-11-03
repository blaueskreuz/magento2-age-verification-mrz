<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Model;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzResult;
use BlauesKreuz\AgeVerificationMrz\Model\MrzForm;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;

class MrzFormTest extends TestCase
{
    private MrzForm $form;

    protected function setUp(): void
    {
        $this->form = new MrzForm();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function request(array $params): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn ($key, $default = null) => $params[$key] ?? $default);

        return $request;
    }

    public function testTypedPassportIsComposedToTwoLines(): void
    {
        $entry = $this->form->read($this->request([
            'document_type' => 'td3',
            'segments' => ['td3' => [
                0 => 'L898902C36UTO9912315F3012316', 1 => 'ZE184226B<<<<<', 2 => '12',
            ]],
        ]));
        $this->assertSame('td3', $entry['type']);
        $this->assertSame(
            str_repeat('<', 44) . "\nL898902C36UTO9912315F3012316ZE184226B<<<<<12",
            $entry['mrz']
        );
    }

    public function testNothingTypedGivesNoMrz(): void
    {
        $entry = $this->form->read($this->request([
            'document_type' => 'td3',
            'segments' => ['td3' => [0 => '', 1 => '<<<<<<<<<<<<<<', 2 => '']],
        ]));
        $this->assertNull($entry['mrz']);
    }

    public function testFillerAloneDoesNotCountAsAnEntry(): void
    {
        $entry = $this->form->read($this->request(['document_type' => 'td3', 'segments' => ['td3' => [1 => 'ABC']]]));
        $this->assertNull($entry['mrz']);
    }

    public function testUnknownTypeFallsBackToTheIdCard(): void
    {
        $this->assertSame('td1', $this->form->read($this->request(['document_type' => 'nope']))['type']);
        $this->assertSame('td1', $this->form->read($this->request([]))['type']);
    }

    public function testOnlyTheChosenCardIsRead(): void
    {
        $entry = $this->form->read($this->request([
            'document_type' => 'td3',
            'segments' => ['td1' => [0 => 'I<UTOD231458907'], 'td3' => []],
        ]));
        $this->assertNull($entry['mrz']);
    }

    public function testBrokenSegmentsAreIgnored(): void
    {
        foreach (['not an array', ['td3' => 'text'], ['td3' => ['x' => ['nested']]]] as $segments) {
            $entry = $this->form->read($this->request(['document_type' => 'td3', 'segments' => $segments]));
            $this->assertNull($entry['mrz']);
        }
    }

    public function testErrorMessages(): void
    {
        $this->assertStringContainsString(
            'incomplete or not recognised',
            (string) $this->form->errorMessage(MrzResult::invalid([MrzError::UnknownFormat]))
        );
        $this->assertStringContainsString(
            'characters that are not allowed',
            (string) $this->form->errorMessage(MrzResult::invalid([MrzError::InvalidCharacters]))
        );
        $this->assertStringContainsString(
            'has expired',
            (string) $this->form->errorMessage(MrzResult::invalid([MrzError::DocumentExpired]))
        );
        $this->assertStringContainsString(
            'could not be verified',
            (string) $this->form->errorMessage(MrzResult::invalid([MrzError::BirthDateCheck]))
        );
    }
}
