<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\EventListener;

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

use MiniFranske\FsMediaGallery\Service\Utility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileCopiedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileCreatedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileDeletedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderDeletedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFolderMovedEvent;
use TYPO3\CMS\Core\Resource\Event\BeforeFolderDeletedEvent;
use TYPO3\CMS\Core\Resource\Event\BeforeFolderMovedEvent;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;

/**
 * EventListener to clear media gallery cache after various file/folder modifications in filelist
 */
final class FolderChangedEventListener
{
    protected $folderMapping = [];

    public function __construct(
        private readonly Utility $utilityService,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    /**
     * Get sub folder structure of folder before is gets moved
     * Is needed to update sys_file_collection records when move was successful
     */
    public function preFolderMove(BeforeFolderMovedEvent $event): void
    {
        $this->folderMapping[$event->getFolder()->getCombinedIdentifier()] = $this->getSubFolderIdentifiers($event->getFolder());
    }

    /**
     * Update sys_file_collection records when folder is moved
     */
    public function postFolderMove(AfterFolderMovedEvent $event): void
    {
        if (!$event->getTargetFolder()) {
            return;
        }
        $newFolder = $event->getTargetFolder();
        $oldStorageUid = $event->getFolder()->getStorage()->getUid();
        $newStorageUid = $newFolder->getStorage()->getUid();

        $newParentId = null;
        // If this is a subFolder find new parent album
        if ($newFolder->getParentFolder() !== $newFolder) {
            $newParentId = $this->utilityService->findFileCollectionRecordsForFolder(
                $newStorageUid,
                $event->getFolder()->getParentFolder()->getIdentifier()
            )[0]['parentalbum'] ?? null;
        }

        $this->utilityService->updateFolderRecord(
            $oldStorageUid,
            $event->getFolder()->getIdentifier(),
            $newStorageUid,
            $newFolder->getIdentifier(),
            $newParentId
        );

        if (!empty($this->folderMapping[$event->getFolder()->getCombinedIdentifier()])) {
            $newMapping = $this->getSubFolderIdentifiers($newFolder);
            foreach ($this->folderMapping[$event->getFolder()->getCombinedIdentifier()] as $key => $folderInfo) {
                $this->utilityService->updateFolderRecord(
                    $oldStorageUid,
                    $folderInfo[1],
                    $newStorageUid,
                    $newMapping[$key][1]
                );
            }
        }
    }

    /**
     * Get sub folder structure of folder before is gets deleted
     * Is needed to update sys_file_collection records when delete was successful
     */
    public function preFolderDelete(BeforeFolderDeletedEvent $event): void
    {
        $this->folderMapping[$event->getFolder()->getCombinedIdentifier()] = $this->getSubFolderIdentifiers($event->getFolder());
    }

    /**
     * Update sys_file_collection records when folder is deleted
     */
    public function postFolderDelete(AfterFolderDeletedEvent $event): void
    {
        $folder = $event->getFolder();
        $storageUid = $folder->getStorage()->getUid();
        $this->utilityService->deleteFolderRecord($storageUid, $folder->getIdentifier());
        foreach ($this->folderMapping[$folder->getCombinedIdentifier()] as $folderInfo) {
            $this->utilityService->deleteFolderRecord($storageUid, $folderInfo[1]);
        }
        $this->clearMediaGalleryPageCache($folder);
    }

    /**
     * Auto creates a file collection to the first parentCollection found of the current folder,
     * when no collection is found nothing is created
     */
    public function postFolderAdd(AfterFolderAddedEvent $event): void
    {
        if (!$this->getConfigFlag('enableAutoCreateFileCollection')) {
            return;
        }

        $folder = $event->getFolder();
        $mediaFolders = $this->utilityService->getStorageFolders();
        if (count($mediaFolders) === 0) {
            return;
        }

        foreach ($mediaFolders as $uid => $title) {
            $parents = $this->utilityService->getFirstParentCollections($folder, $uid);
            if (count($parents) === 0) {
                continue;
            }

            // Take the first parent found
            $parentUid = $parents[0]['uid'];
            $this->utilityService->createFolderRecord(
                ucfirst(trim(str_replace('_', ' ', (string)$folder->getName()))),
                $uid,
                $folder->getStorage()->getUid(),
                $folder->getIdentifier(),
                $parentUid
            );
            $this->clearMediaGalleryPageCache($folder);
        }
    }

    public function postFileAdd(AfterFileAddedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getFolder());
    }

    public function postFileCreate(AfterFileCreatedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getFolder());
    }

    public function postFileCopy(AfterFileCopiedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getFolder());
    }

    public function postFileMove(AfterFileMovedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getOriginalFolder());
        $this->clearMediaGalleryPageCache($event->getFolder());
    }

    public function postFileDelete(AfterFileDeletedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getFile()->getParentFolder());
    }

    public function postFileRename(AfterFileRenamedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getFile()->getParentFolder());
    }

    public function postFileReplace(AfterFileReplacedEvent $event): void
    {
        $this->clearMediaGalleryPageCache($event->getFile()->getParentFolder());
    }

    private function clearMediaGalleryPageCache(FolderInterface $folder): void
    {
        if ($this->getBackendUser() && $this->getConfigFlag('clearCacheAfterFileChange')) {
            $this->utilityService->clearMediaGalleryPageCache($folder);
        }
    }

    private function getSubFolderIdentifiers(Folder $folder): array
    {
        $folderIdentifiers = [];
        foreach ($folder->getSubfolders() as $subFolder) {
            $folderIdentifiers[] = [$subFolder->getHashedIdentifier(), $subFolder->getIdentifier()];
            $folderIdentifiers = array_merge($folderIdentifiers, $this->getSubFolderIdentifiers($subFolder));
        }

        return $folderIdentifiers;
    }

    private function getConfigFlag(string $flag): bool
    {
        return (bool)($this->extensionConfiguration->get('fs_media_gallery')[$flag] ?? false);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
