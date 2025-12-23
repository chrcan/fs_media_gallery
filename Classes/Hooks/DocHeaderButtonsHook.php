<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\Hooks;

use MiniFranske\FsMediaGallery\Service\AbstractBeAlbumButtons;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;

/**
 * Hook to add extra button to DocHeaderButtons in file list.
 */
class DocHeaderButtonsHook extends AbstractBeAlbumButtons
{
    public function __construct(
        private readonly ComponentFactory $componentFactory
    ) {
    }

    protected function createLink(string $title, string $shortTitle, Icon $icon, string $url, bool $addReturnUrl = true): array
    {
        return [
            'title' => $title,
            'icon' => $icon,
            'url' => $url . ($addReturnUrl ? '&returnUrl=' . rawurlencode((string)$_SERVER['REQUEST_URI']) : ''),
        ];
    }

    /**
     * Add media album buttons to file list.
     */
    public function moduleTemplateDocHeaderGetButtons(array $params, ButtonBar $buttonBar): array
    {
        $buttons = $params['buttons'];

        // KORREKTUR: $buttons ist hier ein ARRAY, kein Request-Objekt!
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        // Prüfe auf Filelist-Modul
        $isFileListModule = false;
        if ($request instanceof ServerRequestInterface) {
            $moduleName = $request->getParsedBody()['M'] ?? $request->getQueryParams()['M'] ?? null;
            $route = $request->getAttribute('route');
            $routePath = $route ? $route->getPath() : null;

            $isFileListModule = $moduleName === 'file_FilelistList'
                || $routePath === '/file/FilelistList/'
                || $routePath === '/module/file/FilelistList';
        }

        if ($isFileListModule) {
            $id = (string)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? '');

            foreach ($this->generateButtons($id) as $buttonInfo) {
                // KORREKTUR: ComponentFactory aus dem Konstruktor verwenden
                $button = $this->componentFactory->createLinkButton();

                $button->setShowLabelText(true);
                $button->setIcon($buttonInfo['icon']);
                $button->setTitle($buttonInfo['title']);

                if (str_starts_with((string)$buttonInfo['url'], 'alert')) {
                    // KORREKTUR: `data-content` statt `data-bs-content` für TYPO3 v14
                    $button->setClasses('t3js-modal-trigger')
                        ->setDataAttributes([
                            'severity' => 'warning',
                            'title' => $buttonInfo['title'],
                            'content' => htmlspecialchars(substr((string)$buttonInfo['url'], 6)), // 'content' statt 'bs-content'
                        ]);
                } else {
                    $button->setHref($buttonInfo['url']);
                }
                $buttons['left'][2][] = $button;
            }
        }

        return $buttons;
    }
}
