<?php

if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$GLOBALS['TCA']['sys_redirect']['columns']['check_result'] = [
    'label' => 'LLL:EXT:redirects_healthcheck/Resources/Private/Language/locallang_db.xlf:sys_redirect.check_result',
    'config' => [
        'type' => 'text',
        'readOnly' => true,
        'default' => '',
    ]
];

$GLOBALS['TCA']['sys_redirect']['columns']['last_checked'] = [
    'label' => 'LLL:EXT:redirects_healthcheck/Resources/Private/Language/locallang_db.xlf:sys_redirect.last_checked',
    'config' => [
        'type' => 'input',
        'renderType' => 'inputDateTime',
        'size' => 10,
        'eval' => 'datetime',
        'readOnly' => true,
    ]
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_redirect',
    '--div--;LLL:EXT:redirects_healthcheck/Resources/Private/Language/locallang_db.xlf:div.health_check, check_result,last_checked'
);
