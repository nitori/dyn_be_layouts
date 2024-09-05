<?php

namespace LPS\DynBeLayouts\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class BackendLayoutTemplateService implements SingletonInterface
{
    const COLPOS_OFFSET = 10000;

    public function combineTemplatesAndLayouts(array $templates, array $layouts): array
    {
        $rows = [];
        $rowKey = 1;
        foreach ($layouts as $layout) {
            $colPosOffset = $layout['uid'] * self::COLPOS_OFFSET;

            $key = $layout['template'];
            if (!isset($templates[$key])) {
                continue;
            }

            foreach ($templates[$key]['config.'] as $row) {
                foreach ($row['columns.'] as $colKey => $column) {
                    if (isset($column['colPos'])) {
                        $colPos = (int)$column['colPos'];
                        $row['columns.'][$colKey]['colPos'] = $colPosOffset + $colPos;
                    }
                }

                $row['template'] = $key;
                $row['templateId'] = $layout['uid'];
                $rows[$rowKey . '.'] = $row;
                $rowKey++;
            }
        }
        return $rows;
    }

    public function getDefinedLayoutRecords(int $pageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dynbelayouts_domain_model_layout');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $rows = $queryBuilder->select('uid', 'template')
            ->from('tx_dynbelayouts_domain_model_layout')
            ->where(
                'page=' . $pageId
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();
        if (is_array($rows)) {
            return $rows;
        }
        return [];
    }

    public function getTemplates(int $pageId): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig($pageId);
        $filesPaths = $tsConfig['tx_dynbelayouts.']['yamlFiles.'];

        $sortedKeys = ArrayUtility::filterAndSortByNumericKeys($filesPaths);
        $templates = [];

        foreach ($sortedKeys as $key) {
            if (!isset($filesPaths[$key])) {
                continue;
            }
            $path = GeneralUtility::getFileAbsFileName($filesPaths[$key]);
            foreach ($this->parseYamlFile($path) as $identifier => $config) {
                // no merging, just replacing.
                $templates[$identifier] = $config;
            }
        }

        $result = [];
        foreach ($templates as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    protected function parseYamlFile(string $path): array
    {
        /** @var YamlFileLoader $fileLoader */
        $fileLoader = GeneralUtility::makeInstance(YamlFileLoader::class);

        $layouts = $fileLoader->load($path)['layouts'] ?? [];

        $templates = [];
        foreach ($layouts as $identifier => $config) {
            $columns = $config['columns'] ?? [];
            $columnTitles = array_combine(
                array_map(fn($col) => $col['column'], $columns),
                array_map(fn($col) => $col['title'], $columns),
            );
            $layout = array_map(fn($row) => GeneralUtility::trimExplode(' ', $row, true), $config['layout'] ?? []);

            $rowsOutput = $this->parseLayout($layout);
            if ($rowsOutput === null) {
                continue;
            }

            $result = [];
            ksort($rowsOutput);
            $rowsOutput = array_values($rowsOutput);
            foreach ($rowsOutput as $r => $row) {
                $result[($r + 1) . '.'] = ['columns.' => []];
                ksort($row);
                foreach (array_values($row) as $c => $col) {
                    $result[($r + 1) . '.']['columns.'][($c + 1) . '.'] = $col;
                    if (isset($col['colPos'])) {
                        $result[($r + 1) . '.']['columns.'][($c + 1) . '.']['name'] = $columnTitles[$col['colPos']] ?? 'Column ' . ($c + 1);
                    } else {
                        $result[($r + 1) . '.']['columns.'][($c + 1) . '.']['name'] = 'n/a';
                    }
                }
            }

            $templates[$identifier] = [
                'title' => $config['title'] ?? $identifier,
                'config.' => $result,
            ];
        }

        return $templates;
    }

    /**
     * @param array $layout
     * @return array|null
     */
    protected function parseLayout(array $layout): ?array
    {
        // sample input: [
        //  ["1", "1", "0", "2"],
        //  ["1", "1", "3", "2"],
        //]

        // 1 [
        //   1 => rowspan=2, colspan=2
        //   0 => rowspan=1, colspan=1
        //   2 => rowspan=2, colspan=1
        // ]
        // 2 [
        //   3 => rowspan=1, colspan=1
        // ]

        $numbers = [];
        foreach ($layout as $row) {
            foreach ($row as $col) {
                $numbers[] = $col;
            }
        }
        $numbers = array_unique($numbers);
        $rows = array_fill(0, count($layout), []);

        foreach ($numbers as $number) {
            $rowStart = null;
            $rowEnd = null;
            $colStart = null;
            $colEnd = null;

            foreach ($layout as $rowIndex => $row) {
                $colIndex = array_search($number, $row);
                if ($colIndex !== false) {
                    $rowStart = $rowIndex;
                    $colStart = $colIndex;
                    break;
                }
            }

            foreach (array_reverse($layout, true) as $rowIndex => $row) {
                $colIndex = array_search($number, array_reverse($row, true));
                if ($colIndex !== false) {
                    $rowEnd = $rowIndex;
                    $colEnd = $colIndex;
                    break;
                }
            }

            if ($rowStart === null || $rowEnd === null || $colStart === null || $colEnd === null) {
                // missing in layout.
                continue;
            }

            // check if all cells in the range are the same number
            for ($r = $rowStart; $r <= $rowEnd; $r++) {
                for ($c = $colStart; $c <= $colEnd; $c++) {
                    if ($layout[$r][$c] !== $number) {
                        // incorrect layout, immediate failure
                        return null;
                    }
                }
            }

            $rows[$rowStart][$colStart] ??= [
                'rowspan' => $rowEnd - $rowStart + 1,
                'colspan' => $colEnd - $colStart + 1,
            ];

            if (MathUtility::canBeInterpretedAsInteger($number)) {
                $rows[$rowStart][$colStart]['colPos'] = $number;
            }
        }

        return $rows;
    }

}
