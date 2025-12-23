<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Frans Saris <franssaris@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract utility class for classes that want to add album add/edit buttons
 * somewhere like a ClickMenuOptions class.
 */
abstract class AbstractBeAlbumButtons
{
    /**
     * Generate album add/edit buttons for click menu or toolbar.
     */
    protected function generateButtons(string $combinedIdentifier): array
    {
        $buttons = [];

        // In some folder copy/move actions in file list an invalid id is passed
        try {
            /** @var Folder $file */
            $folder = GeneralUtility::makeInstance(ResourceFactory::class)
                ->retrieveFileOrFolderObject($combinedIdentifier);
        } catch (ResourceDoesNotExistException) {
            $folder = null;
        }

        if (
            $folder instanceof Folder &&
            in_array(
                $folder->getRole(),
                [FolderInterface::ROLE_DEFAULT, FolderInterface::ROLE_USERUPLOAD]
            )
        ) {
            /** @var Utility $utility */
            $utility = GeneralUtility::makeInstance(Utility::class);
            $mediaFolders = $utility->getStorageFolders();

            // \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($collections, 'Collections');

            if (count($mediaFolders)) {
                $collections = $utility->findFileCollectionRecordsForFolder(
                    $folder->getStorage()->getUid(),
                    $folder->getIdentifier(),
                    array_keys($mediaFolders)
                );

                foreach ($collections as $collection) {
                    $buttons[] = $this->createLink(
                        sprintf($this->sL('module.buttons.editAlbum'), $collection['title']),
                        sprintf(
                            $this->sL('module.buttons.editAlbum'),
                            mb_strimwidth((string)$collection['title'], 0, 12, '...')
                        ),
                        $this->getIcon('edit-album'),
                        (string)$this->buildEditUrl($collection['uid'])
                    );
                }

                // \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($mediaFolders, 'Media Folders');

                if (!count((array)$collections)) {
                    foreach ($mediaFolders as $uid => $title) {
                        // find parent album for auto setting parent album
                        $parentUid = 0;
                        $parents = $utility->findFileCollectionRecordsForFolder(
                            $folder->getStorage()->getUid(),
                            $folder->getParentFolder()->getIdentifier(),
                            [$uid]
                        );
                        // if parent(s) found we take the first one
                        if (count((array)$parents)) {
                            $parentUid = $parents[0]['uid'];
                        }
                        $buttons[] = $this->createLink(
                            sprintf($this->sL('module.buttons.createAlbumIn'), $title),
                            sprintf(
                                $this->sL('module.buttons.createAlbumIn'),
                                mb_strimwidth((string)$title, 0, 12, '...')
                            ),
                            $this->getIcon('add-album'),
                            (string)$this->buildAddUrl($uid, $parentUid, $folder)
                        );
                    }
                }

                // show hint button for admin users
                // todo: make this better so it can also be used for editors with enough rights to create a storageFolder
            } elseif ($GLOBALS['BE_USER']->isAdmin()) {
                $buttons[] = $this->createLink(
                    $this->sL('module.buttons.createAlbum'),
                    $this->sL('module.buttons.createAlbum'),
                    $this->getIcon('add-album'),
                    'alert:' . $this->sL('module.alerts.firstCreateStorageFolder'),
                    false
                );
            }
        }
        return $buttons;
    }

    protected function buildEditUrl(int $mediaAlbumUid): Uri
    {
        return GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', [
            'edit' => [
                'sys_file_collection' => [
                    $mediaAlbumUid => 'edit',
                ],
            ],
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
        ]);
    }

    /**
     * Build Add new media album url.
     */
    protected function buildAddUrl(int $pid, int $parentAlbumUid, Folder $folder): Uri
    {
        return GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', [
            'edit' => [
                'sys_file_collection' => [
                    $pid => 'new',
                ],
            ],
            'defVals' => [
                'sys_file_collection' => [
                    'parentalbum' => $parentAlbumUid,
                    'title' => ucfirst(trim(str_replace('_', ' ', (string)$folder->getName()))),
                    'folder_identifier' => $folder->getStorage()->getUid() . ':' . $folder->getIdentifier(),
                    'type' => 'folder',
                ],
            ],
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
        ]);
    }

    abstract protected function createLink(string $title, string $shortTitle, Icon $icon, string $url, bool $addReturnUrl = true): array;

    protected function getIcon(string $name): Icon
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        return $iconFactory->getIcon('action-' . $name, IconSize::SMALL);
    }

    protected function getLangService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Get language string.
     */
    protected function sL(string $key, string $languageFile = 'LLL:EXT:fs_media_gallery/Resources/Private/Language/locallang_be.xlf'): string
    {
        return $this->getLangService()->sL($languageFile . ':' . $key);
    }
}
