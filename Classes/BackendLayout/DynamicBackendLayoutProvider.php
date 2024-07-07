<?php

namespace LPS\DynBeLayouts\BackendLayout;

use LPS\DynBeLayouts\Utility\BackendLayoutUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayoutCollection;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderContext;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderInterface;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class DynamicBackendLayoutProvider implements DataProviderInterface
{

    public function __construct(
        protected FlashMessageService $flashMessageService,
    ) {
    }

    public function addBackendLayouts(
        DataProviderContext $dataProviderContext,
        BackendLayoutCollection $backendLayoutCollection,
    ) {
        $backendLayoutCollection->add($this->createBackendLayout('dummy', $dataProviderContext->getData()));
    }

    public function getBackendLayout($identifier, $pageId): ?BackendLayout
    {
        if ($identifier !== 'dummy') {
            return null;
        }
        $row = BackendUtility::getRecord('pages', $pageId);
        return $this->createBackendLayout($identifier, $row);
    }

    protected function createBackendLayout($identifier, $row): ?BackendLayout
    {
        if ($identifier !== 'dummy') {
            return null;
        }

        $templateKeys = GeneralUtility::trimExplode(',', $row['tx_dynbelayouts_setup'] ?? '', true);
        $templates = BackendLayoutUtility::getTemplates($row['uid']);

        $rows = [];
        $rowKey = 1;
        foreach ($templateKeys as $key) {
            if (!isset($templates[$key])) {
                $this->flash('Backend Layout Template not found: ' . $key,
                    'Error', ContextualFeedbackSeverity::ERROR);
                continue;
            }
            foreach ($templates[$key]['config.'] as $row) {
                $rows[$rowKey . '.'] = $row;
                $rowKey++;
            }
        }

        $config = BackendLayoutUtility::normalizeConfig(['rows.' => $rows]);

        $backendLayoutStr = BackendLayoutUtility::convertArrayToTypoScript(['backend_layout.' => $config]);
        return new BackendLayout('dummy', 'Dynamic Backend Layout', $backendLayoutStr);
    }

    protected function flash(string $message, string $title = '', ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::OK): void
    {
        $message = new FlashMessage($message, $title, $severity, true);
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage($message);
    }
}
