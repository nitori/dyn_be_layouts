<?php

(function () {

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
        'pages',
        [
            'tx_dynbelayouts_setup' => [
                'exclude' => true,
                'label' => 'Backend-Layout Setup',
                'config' => [
                    'type' => 'text',
                    'cols' => 40,
                    'rows' => 15,
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
