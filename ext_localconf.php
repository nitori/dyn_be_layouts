<?php
defined('TYPO3') or die();

(function () {

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendLayoutDataProvider']['lps_dynbelayouts']
        = \LPS\DynBeLayouts\BackendLayout\DynamicBackendLayoutProvider::class;

})();
