<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Controller\Adminhtml\Report;

use BlauesKreuz\AgeVerificationMrz\Model\Report\CsvBuilder;
use BlauesKreuz\AgeVerificationMrz\Model\Report\EvidenceReport;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Response\Http\FileFactory;

/**
 * Downloads the report as a CSV file, with the filters of the page.
 */
class Export extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = Index::ADMIN_RESOURCE;

    private const CHUNK = 500;

    /**
     * @param Action\Context $context
     * @param FileFactory $fileFactory
     * @param EvidenceReport $report
     * @param CsvBuilder $csv
     */
    public function __construct(
        Action\Context $context,
        private readonly FileFactory $fileFactory,
        private readonly EvidenceReport $report,
        private readonly CsvBuilder $csv
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResponseInterface
    {
        $request = $this->getRequest();
        $collection = $this->report->getCollection(
            (string) $request->getParam('from', ''),
            (string) $request->getParam('to', ''),
            (bool) $request->getParam('only_missing', false)
        );
        $collection->setPageSize(self::CHUNK);

        $content = "\xEF\xBB\xBF" . $this->csv->line($this->report->getHeadings());
        $pages = $collection->getLastPageNumber();
        for ($page = 1; $page <= $pages; $page++) {
            $collection->clear()->setCurPage($page);
            foreach ($collection as $order) {
                $content .= $this->csv->line($this->report->getRow($order));
            }
        }

        return $this->fileFactory->create(
            'age-verification-report.csv',
            $content,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
