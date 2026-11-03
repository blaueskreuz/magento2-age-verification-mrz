<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Controller\Account;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * The "Age verification" page of the customer account. Customers who are not logged in are sent to the login
 * page, as for every page of the account.
 */
class Index extends AbstractAccount implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param Config $config
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        if (!$this->config->isAccountVerificationAllowed()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Age verification'));

        return $page;
    }
}
