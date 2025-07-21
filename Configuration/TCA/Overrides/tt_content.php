<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die('not TYPO3 env');

$extensionName = 'FsMediaGallery';
$plugins = [
    'NestedList',
    'FlatList',
    'ShowAlbumByConfig',
    'ShowAlbum',
    'RandomAsset',
];

foreach ($plugins as $pluginName) {
    ExtensionUtility::registerPlugin(
        $extensionName,
        $pluginName,
        'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf:mediagallery.' . lcfirst($pluginName) . '.title'
    );
    $piKey = strtolower($extensionName) . '_' . strtolower($pluginName);
    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        '--div--;Configuration,pi_flexform,',
        $piKey,
        'after:subheader'
    );
    ExtensionManagementUtility::addPiFlexFormValue(
        '*',
        'FILE:EXT:fs_media_gallery/Configuration/FlexForms/flexform_' . strtolower($pluginName) . '.xml',
        $piKey
    );
}
