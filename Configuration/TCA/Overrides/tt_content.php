<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die('not TYPO3 env');

call_user_func(function () {
    $extensionKey = 'fs_media_gallery';
    $extensionName = 'FsMediaGallery';

    $plugins = [
        'NestedList' => [
            'title' => 'mediagallery.nestedList.title',
            'description' => 'mediagallery.nestedList.description',
            'flexform' => 'flexform_nestedlist.xml'
        ],
        'FlatList' => [
            'title' => 'mediagallery.flatList.title',
            'description' => 'mediagallery.flatList.description',
            'flexform' => 'flexform_flatlist.xml'
        ],
        'ShowAlbumByConfig' => [
            'title' => 'mediagallery.showAlbumByConfig.title',
            'description' => 'mediagallery.showAlbumByConfig.description',
            'flexform' => 'flexform_showalbumbyconfig.xml'
        ],
        'ShowAlbum' => [
            'title' => 'mediagallery.showAlbum.title',
            'description' => 'mediagallery.showAlbum.description',
            'flexform' => 'flexform_showalbum.xml'
        ],
        'RandomAsset' => [
            'title' => 'mediagallery.randomAsset.title',
            'description' => 'mediagallery.randomAsset.description',
            'flexform' => 'flexform_randomasset.xml'
        ],
    ];

    foreach ($plugins as $pluginName => $config) {
        $pluginSignature = strtolower($extensionName) . '_' . strtolower($pluginName);

        // Plugin mit FlexForm als 7. Parameter registrieren (TYPO3 v14)
        ExtensionUtility::registerPlugin(
            $extensionName,
            $pluginName,
            'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang_be.xlf:' . $config['title'],
            'content-mediagallery',
            'FS Media Gallery',
            'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang_be.xlf:' . $config['description'],
            'FILE:EXT:' . $extensionKey . '/Configuration/FlexForms/' . $config['flexform']
        );

        // Exclude fields - NUR wenn nötig für bestimmte Felder
        // Standardfelder ausschließen
        $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature] = 'layout,select_key,pages,recursive';
    }

    // Optional: Plugin Icon setzen
    $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes']['list-fsmediagallery_mediagallery'] = 'content-mediagallery';
});
