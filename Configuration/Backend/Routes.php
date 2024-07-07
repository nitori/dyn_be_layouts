<?php

use LPS\DynBeLayouts\Controller\BackendController;

return [
    'lps_dynbelayouts_update' => [
        'path' => '/lps/dynbelayouts/update',
        'target' => BackendController::class . '::updateAction',
    ],
];
