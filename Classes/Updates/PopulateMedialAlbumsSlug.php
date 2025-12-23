<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\Updates;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use MiniFranske\FsMediaGallery\Service\SlugService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrate EXT:realurl unique alias into album slugs.
 *
 * If a lot of similar titles are used it might be a good an idea
 * to migrate the unique aliases from realurl to be sure that the same alias is used
 *
 * Requires existence of DB table tx_realurl_uniqalias, but EXT:realurl requires not to be installed
 * Will only appear if missing slugs found between realurl and fs_media_gallery, respecting language and expire date from realurl
 * Converts title into slug
 */
class PopulateMedialAlbumsSlug implements UpgradeWizardInterface
{
    /**
     * @var SlugService
     */
    protected $slugService;

    public function __construct()
    {
        $this->slugService = GeneralUtility::makeInstance(SlugService::class);
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Fill empty field "slug" of EXT:fs_media_gallery records';
    }

    /**
     * Get description.
     *
     * @return string Longer description of this updater
     */
    public function getDescription(): string
    {
        return 'Fill empty field "slug" of EXT:fs_media_gallery records';
    }

    /**
     * @return string Unique identifier of this updater
     */
    public function getIdentifier(): string
    {
        return 'populateMedialAlbumsSlug';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    /**
     * Checks if an update is needed.
     *
     * @return bool Whether an update is needed (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {
        if (!$this->slugService->typo3SupportsSlugs()) {
            return false;
        }
        $elementCount = $this->slugService->countOfSlugUpdates();
        return (bool)$elementCount;
    }

    /**
     * Performs the database update.
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $queries = $this->slugService->performUpdateSlugs();
        return !empty($queries);
    }
}
