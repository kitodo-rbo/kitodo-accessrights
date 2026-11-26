<?php
declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTCAcolumns('tx_dlf_documents', [
    'accessrights' => [
        'exclude' => true,
        'label' => 'Access rights (LibRML XML)',
        'config' => [
            'type' => 'text',
            'rows' => 20,
            'wrap' => 'off',
            'fixedFont' => true,
            'enableTabulator' => true,
        ],
    ],
]);

// Own tab, so the editor doesn't hunt for the field in Kitodo's form
ExtensionManagementUtility::addToAllTCAtypes('tx_dlf_documents', '--div--;Access rights,accessrights');