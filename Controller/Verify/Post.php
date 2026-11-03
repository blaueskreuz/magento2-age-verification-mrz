<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Controller\Verify;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\MrzForm;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
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
     * @param MrzForm $form
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly VerificationManager $manager,
        private readonly Config $config,
        private readonly MrzForm $form
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

        $entry = $this->form->read($this->request);
        if ($entry['mrz'] === null) {
            return $this->back($entry['type'], __('Please fill in the code from your document.'));
        }
        $result = $this->manager->verify($quote, $entry['mrz'], VerificationMethod::Checkout);
        unset($entry['mrz']);
        if (!$result->isValid()) {
            return $this->back($entry['type'], $this->form->errorMessage($result));
        }
        if (!$this->manager->isSatisfied($quote)) {
            return $this->back($entry['type'], __('You are not old enough for the products in your cart.'));
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
}
