<?php
defined('TYPO3') or die();

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'DlfPdp',
    'Gate',
    [\Kitodo\Dlf\Pdp\Controller\GateController::class => 'denied'],
    [\Kitodo\Dlf\Pdp\Controller\GateController::class => 'denied'],
);