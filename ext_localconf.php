<?php

defined('TYPO3') || die('Access denied.');

use MiniFranske\FsMediaGallery\Controller\MediaAlbumController;
use MiniFranske\FsMediaGallery\Hooks\ProcessDatamapHook;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

$boot = function ($packageKey): void
{
    ExtensionUtility::configurePlugin(
        $packageKey,
        'NestedList',
        [
            MediaAlbumController::class => 'nestedList,showAsset',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'FlatList',
        [
            MediaAlbumController::class => 'flatList,showAsset',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'ShowAlbumByConfig',
        [
            MediaAlbumController::class => 'showAlbumByConfig,showAsset',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'ShowAlbum',
        [
            MediaAlbumController::class => 'showAlbum,showAsset',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'RandomAsset',
        [
            MediaAlbumController::class => 'randomAsset,showAsset',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );

    ExtensionManagementUtility::addPageTSConfig(
        '@import "EXT:fs_media_gallery/Configuration/TSConfig/Page.tsconfig"'
    );

    // refresh file tree after changen in media album recored (sys_file_collection)
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        ProcessDatamapHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
        ProcessDatamapHook::class;
};
$boot('fs_media_gallery');
unset($boot);
