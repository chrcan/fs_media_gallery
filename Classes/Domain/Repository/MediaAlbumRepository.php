<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Domain\Repository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Frans Saris <franssaris@gmail.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use MiniFranske\FsMediaGallery\Domain\Model\MediaAlbum;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * MediaAlbumRepository
 */
class MediaAlbumRepository extends Repository
{
    /**
     * @var array default ordering
     */
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
        'crdate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
     * @var array
     */
    protected $allowedAssetMimeTypes = [];

    /**
     * @var array
     */
    protected $albumUids = [];

    /**
     * @var bool
     */
    protected $useAlbumUidsAsExclude = false;

    /**
     * @var string
     */
    protected $assetsOrderBy = '';

    /**
     * @var string
     */
    protected $assetsOrderDirection = 'asc';

    /**
     * Set allowedAssetMimeTypes
     *
     * @param array $allowedAssetMimeTypes
     */
    public function setAllowedAssetMimeTypes($allowedAssetMimeTypes): void
    {
        $this->allowedAssetMimeTypes = $allowedAssetMimeTypes;
    }

    /**
     * Get allowedAssetMimeTypes
     *
     * @return array $allowedAssetMimeTypes
     */
    public function getAllowedAssetMimeTypes()
    {
        return $this->allowedAssetMimeTypes;
    }

    /**
     * Get allowedAlbumUids
     *
     * @return array
     */
    public function getAlbumUids()
    {
        return $this->albumUids;
    }

    /**
     * Set allowedAlbumUids
     *
     * @param array $albumUids
     */
    public function setAlbumUids($albumUids): void
    {
        $this->albumUids = $albumUids;
    }

    /**
     * Get useAlbumUidsAsExclude
     *
     * @return bool
     */
    public function getUseAlbumUidsAsExclude()
    {
        return $this->useAlbumUidsAsExclude;
    }

    /**
     * Set useAlbumUidsAsExclude
     *
     * @param bool $useAlbumUidsAsExclude
     */
    public function setUseAlbumUidsAsExclude($useAlbumUidsAsExclude): void
    {
        $this->useAlbumUidsAsExclude = $useAlbumUidsAsExclude;
    }

    /**
     * Get assetsOrderBy
     *
     * @return string
     */
    public function getAssetsOrderBy()
    {
        return $this->assetsOrderBy;
    }

    /**
     * Set assetsOrderBy
     *
     * @param string $assetsOrderBy
     */
    public function setAssetsOrderBy($assetsOrderBy): void
    {
        $this->assetsOrderBy = $assetsOrderBy;
    }

    /**
     * Get assetsOrderDirection
     *
     * @return string
     */
    public function getAssetsOrderDirection()
    {
        return $this->assetsOrderDirection;
    }

    /**
     * Set assetsOrderDirection
     *
     * @param string $assetsOrderDirection
     */
    public function setAssetsOrderDirection($assetsOrderDirection): void
    {
        $this->assetsOrderDirection = $assetsOrderDirection;
    }

    /**
     * Get random sub album
     *
     * @param MediaAlbum|bool $parent parent MediaAlbum, FALSE for parent = 0 or NULL for no restriction by parent
     * @return MediaAlbum|null
     */
    public function findRandom($parent = null)
    {
        /** @var Query $query */
        $query = $this->createQuery();
        $constraints = [];

        // restrict by parent album
        if ($parent !== null) {
            $constraints[] = $query->equals('parentalbum', $parent ? $parent->getUid() : 0);
        }

        // restrict by given uids
        if ($this->albumUids !== []) {
            if ($this->useAlbumUidsAsExclude) {
                $constraints[] = $query->logicalNot($query->in('uid', $this->albumUids));
            } else {
                $constraints[] = $query->in('uid', $this->albumUids);
            }
        }
        if (count($constraints) === 1) {
            $query->matching(reset($constraints));
        } elseif (count($constraints) >= 2) {
            $query->matching($query->logicalAnd(...$constraints));
        }
        $query->matching($query->logicalAnd(...$constraints));
        $mediaAlbums = $query->execute()->toArray();

        /** @var MediaAlbum $mediaAlbum */
        $mediaAlbum = null;

        if ($mediaAlbums) {
            $mediaAlbum = $mediaAlbums[array_rand($mediaAlbums, 1)];

            // set allowed asset mime types
            $mediaAlbum->setAllowedMimeTypes($this->allowedAssetMimeTypes);
            // set assets order
            $mediaAlbum->setAssetsOrderBy($this->assetsOrderBy);
            $mediaAlbum->setAssetsOrderDirection($this->assetsOrderDirection);
        }

        return $mediaAlbum;
    }

    /**
     * Find albums by parent album
     *
     * @param MediaAlbum|null $parentAlbum
     * @param bool $excludeEmptyAlbums Exclude albums without assets and subalbums
     * @param string $orderBy Sort albums by: datetime|crdate|sorting
     * @param string $orderDirection Sort order: asc|desc
     * @return MediaAlbum[]
     * @throws InvalidQueryException
     */
    public function findByParentAlbum(
        ?MediaAlbum $parentAlbum = null,
        bool $excludeEmptyAlbums = true,
        string $orderBy = 'sorting',
        string $orderDirection = 'desc'
    ): array {
        $query = $this->createQuery();

        $constraints = [
            $query->equals('parentalbum', $parentAlbum?->getUid() ?? 0),
        ];

        if ($this->albumUids !== []) {
            $uidConstraint = $query->in('uid', $this->albumUids);
            $constraints[] = $this->useAlbumUidsAsExclude
                ? $query->logicalNot($uidConstraint)
                : $uidConstraint;
        }

        $query->matching($query->logicalAnd(...$constraints));
        $query->setOrderings($this->getOrderingsSettings($orderBy, $orderDirection));

        $albums = $query->execute()->toArray();

        $result = [];

        foreach ($albums as $album) {
            $album->setAllowedMimeTypes($this->allowedAssetMimeTypes);
            $album->setAssetsOrderBy($this->assetsOrderBy);
            $album->setAssetsOrderDirection($this->assetsOrderDirection);
            $album->setExcludeEmptyAlbums($excludeEmptyAlbums);

            if (
                !$excludeEmptyAlbums ||
                $album->getAssetsCount() > 0 ||
                count($album->getAlbums() ?? []) > 0
            ) {
                $result[] = $album;
            }
        }

        return $result;
    }

    /**
     * Find album by Uid
     *
     * @param int $uid The identifier of the MediaAlbum to find
     * @param bool $respectStorage possibility to disable storage restriction
     * @return MediaAlbum|null The matching media album if found, otherwise NULL
     */
    public function findByUid($uid, $respectStorage = true)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectSysLanguage(false);

        if (!$respectStorage) {
            $query->getQuerySettings()->setRespectStoragePage(false);
        }

        $constraints = [$query->equals('uid', (int)$uid)];

        if ($this->albumUids !== []) {
            if ($this->useAlbumUidsAsExclude) {
                $constraints[] = $query->logicalNot($query->in('uid', $this->albumUids));
            } else {
                $constraints[] = $query->in('uid', $this->albumUids);
            }
        }

        /** @var MediaAlbum $mediaAlbum */
        $mediaAlbum = $query->matching($query->logicalAnd(...$constraints))->execute()->getFirst();

        if ($mediaAlbum) {
            // set allowed asset mime types
            $mediaAlbum->setAllowedMimeTypes($this->allowedAssetMimeTypes);
            // set assets order
            $mediaAlbum->setAssetsOrderBy($this->assetsOrderBy);
            $mediaAlbum->setAssetsOrderDirection($this->assetsOrderDirection);
        }

        return $mediaAlbum;
    }

    /**
     * Find all albums
     *
     * @param bool $excludeEmptyAlbums
     * @param string $orderBy Sort albums by: datetime|crdate|sorting
     * @param string $orderDirection Sort order: asc|desc
     * @return MediaAlbum[]
     */
    public function findAll($excludeEmptyAlbums = true, $orderBy = 'datetime', $orderDirection = 'desc')
    {
        $excludeEmptyAlbums = filter_var($excludeEmptyAlbums, FILTER_VALIDATE_BOOLEAN);
        $query = $this->createQuery();
        $query->setOrderings($this->getOrderingsSettings($orderBy, $orderDirection));
        $query->getQuerySettings()->setRespectSysLanguage(false);

        if ($this->albumUids !== []) {
            if ($this->useAlbumUidsAsExclude) {
                $query->matching($query->logicalNot($query->in('uid', $this->albumUids)));
            } else {
                $query->matching($query->in('uid', $this->albumUids));
            }
        }

        $mediaAlbums = $query->execute()->toArray();

        foreach ($mediaAlbums as $key => $mediaAlbum) {
            /** @var $mediaAlbum MediaAlbum */
            // set allowed asset mime types
            $mediaAlbum->setAllowedMimeTypes($this->allowedAssetMimeTypes);
            // set assets order
            $mediaAlbum->setAssetsOrderBy($this->assetsOrderBy);
            $mediaAlbum->setAssetsOrderDirection($this->assetsOrderDirection);
            $mediaAlbum->setExcludeEmptyAlbums($excludeEmptyAlbums);

            // exclude if album is empty
            if (
                $excludeEmptyAlbums
                &&
                $mediaAlbum->getAssetsCount() === 0
                &&
                (is_countable($mediaAlbum->getAlbums()) ? count($mediaAlbum->getAlbums()) : 0) === 0
            ) {
                unset($mediaAlbums[$key]);
            }
        }

        // Reset array keys and return albums
        return array_values($mediaAlbums);
    }

    /**
     * Get orderings settings. Returns an array like:
     * array(
     *  'foo' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
     *  'bar' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_DESCENDING
     * )
     *
     * @param string $orderBy Sort albums by: datetime|crdate|sorting
     * @param string $orderDirection Sort order: asc|desc
     * @return array Orderings settings used by \TYPO3\CMS\Extbase\Persistence\QueryInterface->setOrderings()
     */
    protected function getOrderingsSettings($orderBy = 'sorting', $orderDirection = 'asc')
    {
        // check orderDirection
        if ($orderDirection === 'asc') {
            $orderDirection = QueryInterface::ORDER_ASCENDING;
        } else {
            $orderDirection = QueryInterface::ORDER_DESCENDING;
        }

        $orderingsSettings = match ($orderBy) {
            'datetime' => [
                'datetime' => $orderDirection,
                'crdate' => $orderDirection,
            ],
            'crdate' => ['crdate' => $orderDirection],
            default => [
                'sorting' => $orderDirection,
                'crdate' => $orderDirection,
            ],
        };

        return $orderingsSettings;
    }
}
