<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('fe_users', [
    'tx_dlf_pdp_location' => [
        'exclude' => true,
        'label' => 'LLL:EXT:dlf_pdp/Resources/Private/Language/locallang_db.xlf:fe_users.tx_dlf_pdp_location',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 255,
            'readOnly' => true,
        ],
    ],
    'tx_dlf_pdp_affiliation' => [
        'exclude' => true,
        'label' => 'LLL:EXT:dlf_pdp/Resources/Private/Language/locallang_db.xlf:fe_users.tx_dlf_pdp_affiliation',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 255,
            'readOnly' => true,
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'tx_dlf_pdp_location',
    '',
    'after:address'
);
ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    'tx_dlf_pdp_affiliation',
    '',
    'after:tx_dlfpdp_location'
);