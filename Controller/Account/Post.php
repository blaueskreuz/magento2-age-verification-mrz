<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Controller\Account;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\MrzForm;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Phrase;

/**
 * Checks the MRZ entered on the account page and saves the age on the account. The MRZ is checked and dropped
 * here: it is not stored, logged or sent back.
 */
class Post extends AbstractAccount implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param ForwardFactory $forwardFactory
     * @param Session $customerSession
     * @param VerificationManager $manager
     * @param Config $config
     * @param MrzForm $form
     */
    public function __construct(
        Context $context,
        private readonly ForwardFactory $forwardFactory,
        private readonly Session $customerSession,
        private readonly VerificationManager $manager,
        private readonly Config $config,
        private readonly MrzForm $form
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        if (!$this->config->isAccountVerificationAllowed()) {
            return $redirect->setPath('customer/account');
        }

        $entry = $this->form->read($this->getRequest());
        if ($entry['mrz'] === null) {
            return $this->back($entry['type'], __('Please fill in the code from your document.'));
        }
        $outcome = $this->manager->verifyForCustomer(
            (int) $this->customerSession->getCustomerId(),
            $entry['mrz'],
            VerificationMethod::Account
        );
        unset($entry['mrz']);
        if (!$outcome['result']->isValid()) {
            return $this->back($entry['type'], $this->form->errorMessage($outcome['result']));
        }
        if ($outcome['saved']) {
            $this->messageManager->addSuccessMessage(__('Your age has been verified.'));
        } else {
            $this->messageManager->addNoticeMessage(__(
                'Your age was checked. Your account already holds a higher verified age, so it stays as it is.'
            ));
        }

        return $redirect->setPath('agecheck/account');
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
            ->setController('account')
            ->setParams(['mrz_error' => (string) $message, 'document_type' => $type])
            ->forward('index');
    }
}
