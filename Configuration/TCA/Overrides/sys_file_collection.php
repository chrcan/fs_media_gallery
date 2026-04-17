<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

use TYPO3\CMS\Core\Utility\ArrayUtility;

defined('TYPO3') || die('not TYPO3 env');

$additionalColumns = [
    'datetime' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:date_formlabel',
        'config' => [
            'type' => 'datetime',
            'format' => 'datetime',
            'size' => 20,
            'default' => 0,
        ],
    ],
    'sorting' => [
        'label' => 'sorting',
        'config' => [
            'type' => 'passthrough',
        ],
    ],
    'webdescription' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_db.xlf:tx_fsmediagallery_domain_model_mediaalbum.webdescription',
        'config' => [
            'type' => 'text',
            'cols' => 40,
            'rows' => 5,
            'eval' => 'trim',
            'enableRichtext' => true,
            'fieldControl' => [
                'fullScreenRichtext' => [
                    'disabled' => false,
                ],
            ],
        ],
        'defaultExtras' => 'richtext[]',
    ],
    'parentalbum' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_db.xlf:tx_fsmediagallery_domain_model_mediaalbum.parentalbum',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectTree',
            'foreign_table' => 'sys_file_collection',
            'foreign_table_where' => ' AND (sys_file_collection.sys_language_uid = 0 OR sys_file_collection.l10n_parent = 0) AND sys_file_collection.pid = ###CURRENT_PID### AND sys_file_collection.uid != ###THIS_UID### ORDER BY sys_file_collection.sorting ASC, sys_file_collection.crdate DESC',
            'subType' => 'db',
            'treeConfig' => [
                'parentField' => 'parentalbum',
                'appearance' => [
                    'showHeader' => true,
                    'maxLevels' => 99,
                    'width' => 650,
                ],
            ],
            'size' => 10,
            'autoSizeMax' => 20,
            'minitems' => 0,
            'maxitems' => 1,
            'default' => 0,
        ],
    ],
    'main_asset' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_db.xlf:tx_fsmediagallery_domain_model_mediaalbum.main_asset',
        'config' => [
            'type' => 'file',
            'maxitems' => 1,
            'allowed' => 'common-image-types',
            'appearance' => [
                'createNewRelationLinkTitle' => 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_db.xlf:tx_fsmediagallery_domain_model_mediaalbum.main_asset.add',
            ],
        ],
    ],
    'slug' => [
        'exclude' => true,
        'label' => 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_db.xlf:tx_fsmediagallery_domain_model_mediaalbum.slug',
        'config' => [
            'type' => 'slug',
            'generatorOptions' => [
                'fields' => ['title'],
                'fieldSeparator' => '/',
                'prefixParentPageSlug' => false,
            ],
            'fallbackCharacter' => '-',
            'eval' => 'uniqueInPid',
        ],
    ],
];

foreach ($GLOBALS['TCA']['sys_file_collection']['types'] as $type => $tmp) {
    $GLOBALS['TCA']['sys_file_collection']['types'][$type]['showitem'] .= ',--div--;LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_db.xlf:tx_fsmediagallery_domain_model_mediaalbum';
    // try to add field datetime before type (after title)
    if ($replacedTca = preg_replace(
        '/(\s*)type(\s*)([;,])/',
        'datetime,type$3',
        $GLOBALS['TCA']['sys_file_collection']['types'][$type]['showitem']
    )) {
        $GLOBALS['TCA']['sys_file_collection']['types'][$type]['showitem'] = $replacedTca;
    } else {
        $GLOBALS['TCA']['sys_file_collection']['types'][$type]['showitem'] .= ',datetime';
    }
    $GLOBALS['TCA']['sys_file_collection']['types'][$type]['showitem'] .= ',slug,parentalbum,main_asset,webdescription';
}

// enable manual sorting
$GLOBALS['TCA']['sys_file_collection']['ctrl']['sortby'] = 'sorting';
$GLOBALS['TCA']['sys_file_collection']['ctrl']['default_sortby'] = 'ORDER BY sorting ASC, crdate DESC';

// enable main asset preview in list module
$GLOBALS['TCA']['sys_file_collection']['ctrl']['thumbnail'] = 'main_asset';

ArrayUtility::mergeRecursiveWithOverrule(
    $GLOBALS['TCA']['sys_file_collection']['columns'],
    $additionalColumns
);
