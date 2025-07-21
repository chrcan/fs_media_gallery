<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

#[UpgradeWizard('fsMediaGallery_cTypeMigration')]
final class CTypeMigration extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'Migrate "MiniFranske FsMediaGallery" plugins to content elements.';
    }

    public function getDescription(): string
    {
        return 'The "MiniFranske FsMediaGallery" plugins are now registered as content element. Update migrates existing records and backend user permissions.';
    }

    public function getPrerequisites(): array
    {
        return ['fsMediaGallery_migratePlugins'];
    }

    /**
     * Array containing the "list_type" to "CType" mapping
     *
     * @return array<string, string>
     */
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'fsmediagallery_nestedlist' => 'fsmediagallery_nestedlist',
            'fsmediagallery_flatlist' => 'fsmediagallery_flatlist',
            'fsmediagallery_showalbumbyconfig' => 'fsmediagallery_showalbumbyconfig',
            'fsmediagallery_showalbum' => 'fsmediagallery_showalbum',
            'fsmediagallery_randomasset' => 'fsmediagallery_randomasset',
        ];
    }
}
