<?php

namespace LPS\DynBeLayouts\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class BackendLayoutUtility
{
    public static function getBackendLayoutsForTca(array &$data): void
    {
        if ($data['table'] === 'pages') {
            $pid = (int)$data['row']['uid'];
        } else {
            $pid = (int)$data['row']['pid'];
        }

        $templates = self::getTemplates($pid);

        foreach ($templates as $key => $value) {
            if (is_array($value)) {
                $data['items'][] = [
                    'value' => $key,
                    'label' => $value['title'] ?? $key,
                ];
            }
        }
    }

    public static function getTemplates(int $pageId): array
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
            foreach (self::parseYamlFile($path) as $identifier => $config) {
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

    protected static function parseYamlFile(string $path): array
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

            $rowsOutput = self::parseLayout($layout);
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
    protected static function parseLayout(array $layout): ?array
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

    /**
     * @param array $config normalizeConfig($tsConfig['backend_layout.'])
     * @return array
     */
    public static function normalizeConfig(array $config): array
    {
        $columnCounts = self::getColumnCounts($config);
        $totalColumns = array_reduce(array_values($columnCounts), fn($a, $b) => (int)gmp_lcm($a, $b), 1);
        foreach ($config['rows.'] as $rowKey => $row) {
            $perColSpan = $totalColumns / $columnCounts[$rowKey];
            foreach ($row['columns.'] as $colKey => $column) {
                $colSpan = (int)($column['colspan'] ?? 1);
                $config['rows.'][$rowKey]['columns.'][$colKey]['colspan'] = $colSpan * $perColSpan;
            }
        }

        $config['colCount'] = (string)$totalColumns;
        $config['rowCount'] = (string)count($config['rows.']);
        return $config;
    }

    /**
     * @param array $config getColumnCounts($tsConfig['backend_layout.'])
     * @return array
     */
    public static function getColumnCounts(array $config): array
    {
        $columnCounts = [];
        $extraColumns = [];
        foreach ($config['rows.'] ?? [] as $rowKey => $row) {
            $count = count($extraColumns) > 0 ? array_pop($extraColumns) : 0;
            foreach ($row['columns.'] ?? [] as $column) {
                $colSpan = (int)($column['colspan'] ?? 1);
                $rowSpan = (int)($column['rowspan'] ?? 1);
                $count += $colSpan;
                for ($i = 1; $i < $rowSpan; $i++) {
                    $extraColumns[$i - 1] = ($extraColumns[$i - 1] ?? 0) + $colSpan;
                }
            }
            $columnCounts[$rowKey] = $count;
        }
        return $columnCounts;
    }

    /**
     * Converts given array to TypoScript
     *
     * @param array $typoScriptArray The array to convert to string
     * @param string $addKey Prefix given values with given key (eg. lib.whatever = {...})
     * @param integer $tab Internal
     * @param bool $init Internal
     * @return string TypoScript
     */
    public static function convertArrayToTypoScript(array $typoScriptArray, string $addKey = '', int $tab = 0, bool $init = true): string
    {
        $typoScript = '';
        if ($addKey !== '') {
            $typoScript .= str_repeat("    ", ($tab === 0) ? $tab : $tab - 1)
                . $addKey . " {\n";
            if ($init === true) {
                $tab++;
            }
        }
        $tab++;
        foreach ($typoScriptArray as $key => $value) {
            $key = trim($key, '.');
            if (!is_array($value)) {
                if (!str_contains($value, "\n")) {
                    $typoScript .= str_repeat("    ", ($tab === 0) ? $tab : $tab - 1)
                        . "$key = $value\n";
                } else {
                    $typoScript .= str_repeat("    ", ($tab === 0) ? $tab : $tab - 1)
                        . "$key (\n$value\n"
                        . str_repeat("    ", ($tab === 0) ? $tab : $tab - 1)
                        . ")\n";
                }

            } else {
                $typoScript .= self::convertArrayToTypoScript($value, $key, $tab, false);
            }
        }
        if ($addKey !== '') {
            $tab--;
            $typoScript .= str_repeat("    ", ($tab === 0) ? $tab : $tab - 1) . '}';
            if ($init !== true) {
                $typoScript .= "\n";
            }
        }
        return $typoScript;
    }
}
