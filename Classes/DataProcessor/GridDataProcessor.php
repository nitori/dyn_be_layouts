<?php

namespace LPS\DynBeLayouts\DataProcessor;

use LPS\DynBeLayouts\Service\BackendLayoutTemplateService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Example Usage:
 *
 *  <f:section name="Main">
 *      <f:for each="{gridByColumn}" as="gridElement">
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

        $groups = [];
        $byColumn = [];
        foreach ($backendLayoutRows as $row) {
            $id = $row['templateId'];
            $groups[$id] ??= [
                'template' => $row['template'],
                'rows' => [],
            ];

            $byColumn[$id] ??= [
                'template' => $row['template'],
                'columns' => [],
            ];

            $groups[$id]['rows'][] = array_values($row['columns.']);
            foreach ($row['columns.'] as $col) {
                $colPos = $col['colPos'] % BackendLayoutTemplateService::COLPOS_OFFSET;
                $byColumn[$id]['columns'][$colPos] = $col;
            }
        }
        $groups = array_values($groups);

        $processedData['grid'] = $groups;
        $processedData['gridByColumn'] = $byColumn;
        return $processedData;
    }
}
