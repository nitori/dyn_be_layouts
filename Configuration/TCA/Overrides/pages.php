<?php

(function () {

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
        'pages',
        [
            'tx_dynbelayouts_setup' => [
                'exclude' => true,
                'label' => 'Backend-Layout Setup',
                'config' => [
                    'type' => 'inline',
                    'foreign_table' => 'tx_dynbelayouts_domain_model_layout',
                    'foreign_field' => 'page',
                    'foreign_sortby' => 'sorting',
                ],
            ],
        ]
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        'tx_dynbelayouts_setup',
        '',
        'after:backend_layout_next_level'
    );

})();
