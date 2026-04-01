<?php

namespace LPS\DynBeLayouts\DataProcessor;

use LPS\DynBeLayouts\Service\BackendLayoutTemplateService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Example Usage:
 *
 *  <f:section name="Main">
 *      <f:for each="{grids}" as="gridElement">
 *          <f:render section="{gridElement.template}" arguments="{_all}"/>
 *      </f:for>
 *  </f:section>
 *
 *  <f:section name="Container">
 *      <div>
 *          <f:cObject typoscriptObjectPath="lib.dynamicContent" data="{colPos: gridElement.columns.0.colPos}"/>
 *      </div>
 *  </f:section>
 *
 *  <f:section name="ContainerRowSpan">
 *      <div class="row">
 *          <div class="col col-lg-5">
 *              <f:cObject typoscriptObjectPath="lib.dynamicContent" data="{colPos: gridElement.columns.1.colPos}"/>
 *          </div>
 *          <div class="col col-lg-7">
 *              <div class="row">
 *                  <div class="col col-lg-6">
 *                      <f:cObject typoscriptObjectPath="lib.dynamicContent" data="{colPos: gridElement.columns.0.colPos}"/>
 *                      <f:cObject typoscriptObjectPath="lib.dynamicContent" data="{colPos: gridElement.columns.3.colPos}"/>
 *                  </div>
 *                  <div class="col col-lg-6">
 *                      <f:cObject typoscriptObjectPath="lib.dynamicContent" data="{colPos: gridElement.columns.2.colPos}"/>
 *                  </div>
 *              </div>
 *          </div>
 *      </div>
 *  </f:section>
 */
#[Autoconfigure(
    tags: [['name' => 'data.processor', 'identifier' => 'belayout-grid-data']],
    public: true
)]
class GridDataProcessor implements DataProcessorInterface
{
    public function __construct(
        protected BackendLayoutTemplateService $templateService,
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

        $pageLayout = $cObj->getData('pagelayout');

        $layoutRows = $this->templateService->getDefinedLayoutRecords((int)$cObj->data['uid']);
        $templates = $this->templateService->getTemplates((int)$cObj->data['uid']);
        $backendLayoutRows = $this->templateService->combineTemplatesAndLayouts($templates, $layoutRows);

        $grids = [];
        foreach ($backendLayoutRows as $row) {
            $id = $row['templateId'];

            $grids[$id] ??= [
                'template' => $row['template'],
                'columns' => [],
            ];

            foreach ($row['columns.'] as $col) {
                $colPos = $col['colPos'] % BackendLayoutTemplateService::COLPOS_OFFSET;
                $grids[$id]['columns'][$colPos] = $col;
            }
        }

        $as = $cObj->stdWrapValue('as', $processorConfiguration, 'grids');
        $processedData[$as] = $grids;
        return $processedData;
    }
}
