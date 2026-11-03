<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Observer;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/**
 * Sends the customer to the age verification page when they open the checkout with a cart that needs it.
 */
class RedirectToVerification implements ObserverInterface
{
    /**
     * @param Config $config
     * @param VerificationManager $manager
     * @param CheckoutSession $checkoutSession
     * @param UrlInterface $url
     * @param ActionFlag $actionFlag
     * @param RedirectInterface $redirect
     */
    public function __construct(
        private readonly Config $config,
        private readonly VerificationManager $manager,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $url,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId() || !$quote->hasItems() || $this->manager->isSatisfied($quote)) {
            return;
        }
        /** @var Action $controller */
        $controller = $observer->getEvent()->getData('controller_action');
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
        $this->redirect->redirect($controller->getResponse(), $this->url->getUrl('agecheck/verify'));
    }
}
