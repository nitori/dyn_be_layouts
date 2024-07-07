<?php

namespace LPS\DynBeLayouts\BackendLayout;

use LPS\DynBeLayouts\Utility\BackendLayoutUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayoutCollection;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderContext;
use TYPO3\CMS\Backend\View\BackendLayout\DataProviderInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynbelayouts_domain_model_layout');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $layoutRows = $queryBuilder->select('uid', 'template')
            ->from('tx_dynbelayouts_domain_model_layout')
            ->where(
                'page=' . (int)$row['uid']
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $templates = BackendLayoutUtility::getTemplates($row['uid']);

        $rows = [];
        $rowKey = 1;
        foreach ($layoutRows as $layoutRow) {
            $colPosOffset = $layoutRow['uid'] * 10000;

            $key = $layoutRow['template'];
            if (!isset($templates[$key])) {
                $this->flash('Backend Layout Template not found: ' . $key,
                    'Error', ContextualFeedbackSeverity::ERROR);
                continue;
            }

            foreach ($templates[$key]['config.'] as $row) {
                foreach ($row['columns.'] as $colKey => $column) {
                    if (isset($column['colPos'])) {
                        $colPos = (int)$column['colPos'];
                        $row['columns.'][$colKey]['colPos'] = $colPosOffset + $colPos;
                    }
                }

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
