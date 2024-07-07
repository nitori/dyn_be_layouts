<?php

namespace LPS\DynBeLayouts\Utility;


use LPS\DynBeLayouts\Service\BackendLayoutTemplateService;

class BackendLayoutUtility
{
    public function __construct(
        protected BackendLayoutTemplateService $templateService,
    ) {
    }

    public function getBackendLayoutsForTca(array &$data): void
    {
        if ($data['table'] === 'pages') {
            $pid = (int)$data['row']['uid'];
        } else {
            $pid = (int)$data['row']['pid'];
        }

        $templates = $this->templateService->getTemplates($pid);

        foreach ($templates as $key => $value) {
            if (is_array($value)) {
                $data['items'][] = [
                    'value' => $key,
                    'label' => $value['title'] ?? $key,
                ];
            }
        }
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
