<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Controller\Verify;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Shows the "Verify your age" page.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param PageFactory $pageFactory
     * @param RedirectFactory $redirectFactory
     * @param CheckoutSession $checkoutSession
     * @param VerificationManager $manager
     * @param Config $config
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
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
        if ($this->manager->isSatisfied($quote)) {
            return $this->redirectFactory->create()->setPath('checkout');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Verify your age'));

        return $page;
    }
}
