<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\EventListener;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Frans Saris <franssaris@gmail.com>
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

use MiniFranske\FsMediaGallery\Service\Utility;
use TYPO3\CMS\Core\Imaging\Event\ModifyIconForResourcePropertiesEvent;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;

/**
 * Changes the folder icon in folder tree to sys_file_collection image, if contained
 * images are defined as media gallery.
 */
class IconFactoryEventListener
{
    private static ?array $mediaFolders = null;

    public function __construct(
        private readonly Utility $utilityService
    ) {}

    public function buildIconForResource(
        ModifyIconForResourcePropertiesEvent $event
    ): void {
        $folderObject = $event->getResource();

        if (!($folderObject instanceof Folder)
            || !in_array($folderObject->getRole(), [FolderInterface::ROLE_DEFAULT, FolderInterface::ROLE_USERUPLOAD])
        ) {
            return;
        }

        $mediaFolders = $this->getMediaFolders();
        if ($mediaFolders === []) {
            return;
        }

        $collections = $this->utilityService->findFileCollectionRecordsForFolder(
            $folderObject->getStorage()->getUid(),
            $folderObject->getIdentifier(),
            array_keys($mediaFolders)
        );

        if (!$collections) {
            return;
        }

        $event->setIconIdentifier('tcarecords-sys_file_collection-folder');
        $hidden = true;
        foreach ($collections as $collection) {
            if ((int)$collection['hidden'] === 0) {
                $hidden = false;
                break;
            }
        }
        if ($hidden) {
            $event->setOverlayIdentifier('overlay-hidden');
        }
    }

    private function getMediaFolders(): array
    {
        if (self::$mediaFolders === null) {
            self::$mediaFolders = $this->utilityService->getStorageFolders();
        }
        return self::$mediaFolders;
    }
}
