<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Controller\Verify;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzLayout;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzResult;
use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
use BlauesKreuz\AgeVerificationMrz\ViewModel\Verify;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Phrase;

/**
 * Checks the entered MRZ. The MRZ is checked and dropped here: it is not stored, logged or sent back.
 */
class Post implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param ForwardFactory $forwardFactory
     * @param CheckoutSession $checkoutSession
     * @param VerificationManager $manager
     * @param Config $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly VerificationManager $manager,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$this->config->isEnabled() || !$quote->hasItems()) {
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $key = (string) $this->request->getParam('document_type', 'td1');
        $formats = Verify::formats();
        $format = $formats[$key] ?? $formats['td1'];
        $key = isset($formats[$key]) ? $key : 'td1';
        $entries = (array) ($this->request->getParam('segments')[$key] ?? []);

        $typed = false;
        foreach (MrzLayout::segments($format) as $index => $segment) {
            if (!$segment->filler && trim((string) ($entries[$index] ?? '')) !== '') {
                $typed = true;
            }
        }
        if (!$typed) {
            return $this->back($key, __('Please fill in the code from your document.'));
        }
        $result = $this->manager->verify(
            $quote,
            MrzLayout::compose($format, array_map('strval', $entries)),
            VerificationMethod::Checkout
        );
        unset($entries);
        if (!$result->isValid()) {
            return $this->back($key, $this->errorMessage($result));
        }
        if (!$this->manager->isSatisfied($quote)) {
            return $this->back($key, __('You are not old enough for the products in your cart.'));
        }

        return $this->redirectFactory->create()->setPath('checkout');
    }

    /**
     * Shows the page again with an error. Only the error and the chosen type are passed on, never the entry.
     *
     * @param string $type
     * @param Phrase $message
     * @return ResultInterface
     */
    private function back(string $type, Phrase $message): ResultInterface
    {
        return $this->forwardFactory->create()
            ->setController('verify')
            ->setParams(['mrz_error' => (string) $message, 'document_type' => $type])
            ->forward('index');
    }

    /**
     * A message for the first error of a rejected MRZ.
     *
     * @param MrzResult $result
     * @return Phrase
     */
    private function errorMessage(MrzResult $result): Phrase
    {
        $error = $result->getErrors()[0] ?? MrzError::UnknownFormat;

        return match ($error) {
            MrzError::UnknownFormat => __('The code is incomplete or not recognised. Fill in every blank exactly as printed on your document.'),
            MrzError::InvalidCharacters => __('The code contains characters that are not allowed. Use only letters, digits and "<".'),
            MrzError::DocumentExpired => __('This document has expired. Please use a valid ID card or passport.'),
            default => __('The code could not be verified. Please check it for typing mistakes.'),
        };
    }
}
