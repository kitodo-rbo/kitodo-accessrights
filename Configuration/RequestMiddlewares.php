<?php
return [
    'frontend' => [
        'kitodo/dlf-pdp/enforcement' => [
            'target' => \Kitodo\Dlf\Pdp\Middleware\AccessEnforcementMiddleware::class,
            'after' => ['typo3/cms-frontend/page-resolver'],
            'before' => ['typo3/cms-frontend/prepare-tsfe-rendering'],
        ],
    ],
];