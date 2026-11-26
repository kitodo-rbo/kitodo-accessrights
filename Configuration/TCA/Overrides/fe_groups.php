<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$ll = 'LLL:EXT:dlf_pdp/Resources/Private/Language/locallang_db.xlf:';

ExtensionManagementUtility::addTCAcolumns('fe_groups', [
    'tx_dlf_pdp_affiliation' => [
        'label' => $ll . 'fe_groups.tx_dlf_pdp_affiliation',
        'description' => $ll . 'fe_groups.tx_dlf_pdp_affiliation.description',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'items' => [
                ['label' => $ll . 'affiliation.student',      'value' => 'student'],
                ['label' => $ll . 'affiliation.professor',    'value' => 'professor'],
                ['label' => $ll . 'affiliation.staff',        'value' => 'staff'],
                ['label' => $ll . 'affiliation.libraryreader', 'value' => 'libraryreader'],
            ],
        ],
    ],
]);


ExtensionManagementUtility::addToAllTCAtypes(
    'fe_groups',
    '--div--;Group Affiliations from Users,tx_dlf_pdp_affiliation',
    '',
    'after:description'
);