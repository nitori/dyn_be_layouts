<?php

namespace LPS\DynBeLayouts\DataProcessor;

use LPS\DynBeLayouts\Service\BackendLayoutTemplateService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

class GridDataProcessor implements DataProcessorInterface
{
    public function __construct(
        protected BackendLayoutTemplateService $templateService,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        if ($cObj->getCurrentTable() !== 'pages') {
            return $processedData;
        }

        $pageLayout = $cObj->getData('pagelayout');
        if ($pageLayout !== 'lps_dynbelayouts__dummy') {
            return $processedData;
        }

        $layoutRows = $this->templateService->getDefinedLayoutRecords((int)$cObj->data['uid']);
        $templates = $this->templateService->getTemplates((int)$cObj->data['uid']);
        $backendLayoutRows = $this->templateService->combineTemplatesAndLayouts($templates, $layoutRows);

        $rows = [];
        foreach ($backendLayoutRows as $row) {
            $newRow = [
                'template' => $row['template'],
                'templateId' => $row['templateId'],
                'columns' => [],
            ];
            foreach ($row['columns.'] as $column) {
                $newRow['columns'][] = $column;
            }
            $rows[] = $newRow;
        }

        $current = null;
        $currentGroup = [];
        $groups = [];
        foreach ($rows as $row) {
            $id = $row['templateId'];
            if ($id !== $current) {
                $current = $id;
                if (count($currentGroup) > 0) {
                    $groups[] = [
                        'template' => $row['template'],
                        'rows' => $currentGroup,
                    ];
                }
                $currentGroup = [];
            }
            $currentGroup[] = $row['columns'];
        }

        if (count($currentGroup) > 0) {
            $groups[] = [
                'template' => $row['template'],
                'rows' => $currentGroup,
            ];
        }

        $processedData['grid'] = $groups;

        return $processedData;
    }
}
