<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

use MiniFranske\FsMediaGallery\Controller\MediaAlbumController;
use MiniFranske\FsMediaGallery\Hooks\ProcessDatamapHook;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || die('not TYPO3 env');

call_user_func(function ($packageKey): void {
    ExtensionUtility::configurePlugin(
        $packageKey,
        'NestedList',
        [
            MediaAlbumController::class => 'nestedList,showAsset',
        ],
        [
            MediaAlbumController::class => 'showAsset',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'FlatList',
        [
            MediaAlbumController::class => 'flatList,showAsset',
        ],
        [
            MediaAlbumController::class => 'showAsset',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'ShowAlbumByConfig',
        [
            MediaAlbumController::class => 'showAlbumByConfig,showAsset',
        ],
        [
            MediaAlbumController::class => 'showAsset',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'ShowAlbum',
        [
            MediaAlbumController::class => 'showAlbum,showAsset',
        ],
        [
            MediaAlbumController::class => 'showAsset',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        $packageKey,
        'RandomAsset',
        [
            MediaAlbumController::class => 'randomAsset,showAsset',
        ],
        [
            MediaAlbumController::class => 'showAsset',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    // refresh file tree after changes in media album record (sys_file_collection)
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] =
        ProcessDatamapHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] =
        ProcessDatamapHook::class;
}, 'fs_media_gallery');
