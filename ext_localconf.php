<?php
defined('TYPO3') or die();

(function () {

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['BackendLayoutDataProvider']['pagets']
        = \LPS\DynBeLayouts\BackendLayout\DynamicBackendLayoutProvider::class;

})();
