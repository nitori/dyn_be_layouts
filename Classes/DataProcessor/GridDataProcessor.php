<?php

namespace LPS\DynBeLayouts\DataProcessor;

use LPS\DynBeLayouts\Service\BackendLayoutTemplateService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Example Usage:
 *
 * <f:section name="Main">
 *     <f:for each="{grids}" as="grid">
 *         <div class="container background-{grid.settings.background}">
 *             <f:render section="{grid.template}" arguments="{grid: grid}"/>
 *         </div>
 *     </f:for>
 * </f:section>
 *
 * <f:section name="Container">
 *     <div>
 *         <f:render partial="Content" arguments="{records: grid.columns.0.records}"/>
 *     </div>
 * </f:section>
 *
 * <f:section name="ContainerRowSpan">
 *     <div class="row">
 *         <div class="col col-lg-{grid.settings.mainWidth}">
 *             <f:render partial="Content" arguments="{records: grid.columns.1.records}"/>
 *         </div>
 *         <f:variable name="otherWidth" value="12"/>
 *         <f:variable name="otherWidth" value="{otherWidth - grid.settings.mainWidth}"/>
 *         <div class="col col-lg-{otherWidth}">
 *             <div class="row">
 *                 <div class="col col-lg-6">
 *                     <f:render partial="Content" arguments="{records: grid.columns.0.records}"/>
 *                     <f:render partial="Content" arguments="{records: grid.columns.3.records}"/>
 *                 </div>
 *                 <div class="col col-lg-6">
 *                     <f:render partial="Content" arguments="{records: grid.columns.2.records}"/>
 *                 </div>
 *             </div>
 *         </div>
 *     </div>
 * </f:section>
 */
#[Autoconfigure(
    tags: [['name' => 'data.processor', 'identifier' => 'belayout-grid-data']],
    public: true
)]
class GridDataProcessor implements DataProcessorInterface
{
    public function __construct(
        protected BackendLayoutTemplateService $templateService,
        protected readonly ContentDataProcessor $contentDataProcessor,
    ) {
    }

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if ($cObj->getCurrentTable() !== 'pages') {
            return $processedData;
        }

        $contentRecordsData = $this->contentDataProcessor->process($cObj, [
            'dataProcessing.' => [
                '10' => 'page-content',
            ],
        ], $processedData);
        $contentRecords = $contentRecordsData['content'];

        $layoutRows = $this->templateService->getDefinedLayoutRecords((int)$cObj->data['uid']);
        $templates = $this->templateService->getTemplates((int)$cObj->data['uid']);
        $backendLayoutRows = $this->templateService->combineTemplatesAndLayouts($templates, $layoutRows);

        $grids = [];
        foreach ($backendLayoutRows as $row) {
            $id = $row['templateId'];

            $grids[$id] ??= [
                'template' => $row['template'],
                'settings' => $row['settings'] ?? [],
                'columns' => [],
            ];

            foreach ($row['columns.'] as $col) {
                if (!isset($col['colPos'])) {
                    continue;
                }
                $localColPos = $col['colPos'] % BackendLayoutTemplateService::COLPOS_OFFSET;
                $content = $contentRecords[$row['template'] . '_' . $localColPos] ?? [];
                $col['records'] = $content['records'] ?? [];
                $grids[$id]['columns'][$localColPos] = $col;
            }
        }

        $as = $cObj->stdWrapValue('as', $processorConfiguration, 'grids');
        $processedData[$as] = $grids;
        return $processedData;
    }
}
