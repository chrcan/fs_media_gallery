<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\Hooks;

/*                                                                        *
 * This script is part of the TYPO3 project.                              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ItemsProcFuncHook.
 */
class ItemsProcFuncHook
{
    /**
     * Sets the available actions for settings.switchableControllerActions.
     */
    public function getItemsForSwitchableControllerActions(array &$config): void
    {
        $availableActions = [
            'nestedList' => 'MediaAlbum->nestedList;MediaAlbum->showAsset',
            'flatList' => 'MediaAlbum->flatList;MediaAlbum->showAlbum;MediaAlbum->showAsset',
            'showAlbumByParam' => 'MediaAlbum->showAlbum;MediaAlbum->showAsset',
            'showAlbumByConfig' => 'MediaAlbum->showAlbumByConfig;MediaAlbum->showAsset',
            'randomAsset' => 'MediaAlbum->randomAsset',
        ];
        $extConf = $this->getExtensionConfiguration();
        $allowedActions = [
            // index action is always allowed
            // this is needed to make sure the correct tabs/fields are shown in
            // flexform when a new plugin is added
            'index' => 'MediaAlbum->index',
        ];
        $allowedActionsFromExtConf = [];
        if (!empty($extConf['allowedActionsInFlexforms'])) {
            $allowedActionsFromExtConf = GeneralUtility::trimExplode(',', $extConf['allowedActionsInFlexforms']);
        }
        foreach ($allowedActionsFromExtConf as $allowedActionFromExtConf) {
            if (!empty($availableActions[$allowedActionFromExtConf])) {
                $allowedActions[$allowedActionFromExtConf] = $availableActions[$allowedActionFromExtConf];
            }
        }
        // check items; allow all actions if something went wrong (no action except of "indexAction" is allowed)
        if (count($allowedActions) > 1) {
            foreach ($config['items'] as $key => $item) {
                if (!in_array($item[1], $allowedActions)) {
                    unset($config['items'][$key]);
                }
            }
        }
    }

    /**
     * Sets the available options for settings.list.orderBy.
     */
    public function getItemsForListOrderBy(array &$config): void
    {
        $availableOptions = ['datetime', 'crdate', 'sorting'];
        $extConf = $this->getExtensionConfiguration();
        $allowedOptions = [];
        $allowedOptionsFromExtConf = [];
        if (!empty($extConf['list']['orderOptions'])) {
            $allowedOptionsFromExtConf = GeneralUtility::trimExplode(',', $extConf['list']['orderOptions']);
        }
        foreach ($allowedOptionsFromExtConf as $allowedOptionFromExtConf) {
            if (in_array($allowedOptionFromExtConf, $availableOptions)) {
                $allowedOptions[] = $allowedOptionFromExtConf;
            }
        }
        foreach ($config['items'] as $key => $item) {
            // check items; empty value (inherit from TS) is always allowed
            if (!empty($item[1]) && !in_array($item[1], $allowedOptions)) {
                unset($config['items'][$key]);
            }
        }
    }

    /**
     * Sets the available options for settings.album.assets.orderBy.
     */
    public function getItemsForAssetsOrderBy(array &$config): void
    {
        // default set
        $allowedOptions = ['name', 'crdate', 'title', 'content_creation_date', 'content_modification_date'];
        $availableOptions = [];
        $extConf = $this->getExtensionConfiguration();

        if (!empty($extConf['asset']['orderOptions'])) {
            $allowedOptions = GeneralUtility::trimExplode(',', $extConf['asset']['orderOptions']);
        }
        // check if field exists in TCA of sys_file or sys_file_metadata
        foreach ($allowedOptions as $option) {
            if (
                $option === 'crdate'
                ||
                !empty($GLOBALS['TCA']['sys_file']['columns'][$option])
                ||
                !empty($GLOBALS['TCA']['sys_file_metadata']['columns'][$option])
            ) {
                $availableOptions[] = $option;
            }
        }
        // @todo: add option to add custom options to the item list
        //        use label from TCA
        foreach ($config['items'] as $key => $item) {
            // check items; empty value (inherit from TS) is always allowed
            if (!empty($item[1]) && !in_array($item[1], $availableOptions)) {
                unset($config['items'][$key]);
            }
        }
    }

    /**
     * Get extension configuration.
     */
    protected function getExtensionConfiguration()
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('fs_media_gallery');
    }
}
