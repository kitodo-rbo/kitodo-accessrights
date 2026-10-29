<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('fe_groups', [
    'tx_dlf_pdp_affiliation' => [
        'label' => 'Affiliations that automatically receive this group',
        'description' => 'Comma-separated, e.g. "staff, employee". Compared case-insensitively.',
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 255,
            'eval' => 'trim',
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_groups',
    '--div--;Group Affiliations from Users,tx_dlf_pdp_affiliation',
    '',
    'after:description'
);