<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\ViewModel;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzLayout;
use BlauesKreuz\AgeVerificationMrz\Model\MrzForm;
use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Prepares everything the "Verify your age" page shows, so that the template only renders.
 */
class Verify implements ArgumentInterface
{
    /**
     * @param VerificationManager $manager
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param FormKey $formKey
     * @param UrlInterface $url
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly VerificationManager $manager,
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly FormKey $formKey,
        private readonly UrlInterface $url,
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * The age the cart needs.
     *
     * @return int
     */
    public function getRequiredAge(): int
    {
        return $this->manager->getRequiredAge($this->checkoutSession->getQuote());
    }

    /**
     * The youth-protection message, empty if it is switched off.
     *
     * @return string
     */
    public function getYouthMessage(): string
    {
        return $this->config->getYouthMessage();
    }

    /**
     * The form key for the POST request.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Where the form is sent to.
     *
     * @param bool $account whether the form is on the account page (otherwise: before the checkout)
     * @return string
     */
    public function getPostUrl(bool $account = false): string
    {
        return $this->url->getUrl($account ? 'agecheck/account/post' : 'agecheck/verify/post');
    }

    /**
     * Where the customer goes back to.
     *
     * @param bool $account whether the form is on the account page (otherwise: before the checkout)
     * @return string
     */
    public function getBackUrl(bool $account = false): string
    {
        return $this->url->getUrl($account ? 'customer/account' : 'checkout/cart');
    }

    /**
     * What is known about the age of the logged-in customer, as a sentence.
     *
     * @return Phrase
     */
    public function getAccountStatus(): Phrase
    {
        $status = $this->manager->getCustomerStatus((int) $this->customerSession->getCustomerId());
        if ($status['age'] === 0) {
            return __('Your age has not been verified yet.');
        }
        if ($status['date'] === null) {
            return __('Your age has been verified: you can buy products for customers aged %1 or older.', $status['age']);
        }

        return __(
            'Your age has been verified: you can buy products for customers aged %1 or older. Last checked on %2.',
            $status['age'],
            $this->timezone->formatDateTime(
                (new \DateTime('@' . $status['date'])),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE
            )
        );
    }

    /**
     * The error of the last attempt, if any.
     *
     * @return string
     */
    public function getError(): string
    {
        return (string) $this->request->getParam('mrz_error', '');
    }

    /**
     * The form value of the chosen document type.
     *
     * @return string
     */
    public function getSelectedType(): string
    {
        $key = (string) $this->request->getParam('document_type', 'td1');

        return isset(MrzForm::formats()[$key]) ? $key : 'td1';
    }

    /**
     * The document types as options of the select.
     *
     * @return array<int, array{value: string, label: string, selected: bool}>
     */
    public function getTypeOptions(): array
    {
        $options = [];
        foreach (MrzForm::formats() as $key => $format) {
            $options[] = [
                'value' => $key,
                'label' => (string) $this->documentLabel($format),
                'selected' => $key === $this->getSelectedType(),
            ];
        }

        return $options;
    }

    /**
     * The cards with their lines and blanks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCards(): array
    {
        $cards = [];
        foreach (MrzForm::formats() as $key => $format) {
            $segments = MrzLayout::segments($format);
            $ignored = MrzLayout::ignoredLines($format);
            $lines = [];
            for ($line = 0; $line < $format->lineCount(); $line++) {
                $blanks = [];
                foreach ($segments as $index => $segment) {
                    if ($segment->line !== $line) {
                        continue;
                    }
                    $blanks[] = [
                        'name' => 'segments[' . $key . '][' . $index . ']',
                        'line' => $segment->line,
                        'start' => $segment->start,
                        'length' => $segment->length,
                        'css_class' => $segment->filler ? 'mrz-segment--filler' : 'mrz-segment--blank',
                        'default' => $segment->defaultValue(),
                    ];
                }
                $lines[] = [
                    'sample' => in_array($line, $ignored, true) ? $this->sampleNameLine($format) : '',
                    'blanks' => $blanks,
                ];
            }
            $cards[] = [
                'key' => $key,
                'selected' => $key === $this->getSelectedType(),
                'title' => (string) $this->documentLabel($format),
                'hint' => (string) $this->documentHint($format),
                'line_count' => $format->lineCount(),
                'line_length' => $format->lineLength(),
                'lines' => $lines,
            ];
        }

        return $cards;
    }

    /**
     * The name of a document type.
     *
     * @param MrzFormat $format
     * @return Phrase
     */
    private function documentLabel(MrzFormat $format): Phrase
    {
        return match ($format) {
            MrzFormat::TD1 => __('ID card (credit card size)'),
            MrzFormat::TD3 => __('Passport'),
            MrzFormat::TD2 => __('ID card (older, large format)'),
        };
    }

    /**
     * A short hint on where to find the code on the document.
     *
     * @param MrzFormat $format
     * @return Phrase
     */
    private function documentHint(MrzFormat $format): Phrase
    {
        return match ($format) {
            MrzFormat::TD1 => __('All nationalities, including foreign residence permits in credit card format. The code is on the back.'),
            MrzFormat::TD3 => __('The code is at the bottom of the page with your photo.'),
            MrzFormat::TD2 => __('Large ID cards. The code is at the bottom of the back.'),
        };
    }

    /**
     * An example of the name line, shown greyed out as orientation.
     *
     * @param MrzFormat $format
     * @return string
     */
    private function sampleNameLine(MrzFormat $format): string
    {
        $prefix = $format === MrzFormat::TD1 ? '' : ($format === MrzFormat::TD3 ? 'P<CHE' : 'I<CHE');

        return str_pad($prefix . 'MUSTER<<ANDREA', $format->lineLength(), '<');
    }
}
