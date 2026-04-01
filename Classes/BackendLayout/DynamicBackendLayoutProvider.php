<?php

namespace LPS\DynBeLayouts\BackendLayout;

use LFM\Lfmcore\Utility\DebuggerUtility;
use LPS\DynBeLayouts\Service\BackendLayoutTemplateService;
use LPS\DynBeLayouts\Utility\BackendLayoutUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayoutCollection;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderContext;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderInterface;
use TYPO3\CMS\Backend\View\BackendLayout\PageTsBackendLayoutDataProvider;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DynamicBackendLayoutProvider implements DataProviderInterface
{
    protected PageTsBackendLayoutDataProvider $pageTsBackendLayoutDataProvider;

    public function __construct(
        protected FlashMessageService $flashMessageService,
        protected BackendLayoutTemplateService $templateService,
    ) {
        $this->pageTsBackendLayoutDataProvider = GeneralUtility::makeInstance(PageTsBackendLayoutDataProvider::class);
    }

    public function addBackendLayouts(
        DataProviderContext $dataProviderContext,
        BackendLayoutCollection $backendLayoutCollection,
    ): void {
        $this->pageTsBackendLayoutDataProvider->addBackendLayouts($dataProviderContext, $backendLayoutCollection);
    }

    public function getBackendLayout($identifier, $pageId): ?BackendLayout
    {
        $pageTsConfig = $this->getPageTsConfig(null, $pageId);
        if (!array_key_exists('tx_dynbelayouts.', $pageTsConfig)) {
            return $this->pageTsBackendLayoutDataProvider->getBackendLayout($identifier, $pageId);
        }
        $row = BackendUtility::getRecordWSOL('pages', $pageId);
        return $this->createBackendLayout($identifier, $row);
    }

    protected function createBackendLayout($identifier, $row): ?BackendLayout
    {
        $layoutRows = $this->templateService->getDefinedLayoutRecords((int)$row['uid']);
        $templates = $this->templateService->getTemplates((int)$row['uid']);
        $rows = $this->templateService->combineTemplatesAndLayouts($templates, $layoutRows);

        $config = BackendLayoutUtility::normalizeConfig(['rows.' => $rows]);
        $backendLayoutStr = BackendLayoutUtility::convertArrayToTypoScript(['backend_layout.' => $config]);
        return new BackendLayout($identifier, 'Dynamic Backend Layout', $backendLayoutStr);
    }

    protected function flash(string $message, string $title = '', ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::OK): void
    {
        $message = new FlashMessage($message, $title, $severity, true);
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($message);
    }

    /**
     * Gets page TSconfig from DataProviderContext if available from context,
     * else fetch from BackendUtility by pageId.
     */
    private function getPageTsConfig(?DataProviderContext $dataProviderContext, ?int $pageId): array
    {
        if ($dataProviderContext === null && $pageId === null) {
            throw new \RuntimeException('Either $dataProviderContext or $pageId must be provided', 1676380686);
        }
        if ($dataProviderContext) {
            return $dataProviderContext->pageTsConfig;
        }
        return BackendUtility::getPagesTSconfig($pageId);
    }
}
