<?php

declare(strict_types=1);

namespace MiniFranske\FsMediaGallery\EventListener;

use MiniFranske\FsMediaGallery\Service\AbstractBeAlbumButtons;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;

class DocHeaderButtonsEventListener extends AbstractBeAlbumButtons
{
    public function __construct(
        private readonly ComponentFactory $componentFactory
    ) {
    }

    public function __invoke(ModifyButtonBarEvent $event): void
    {
        $buttons = $event->getButtons();

        if (
            ($request = $this->getTypo3Request())
            && ($route = $request->getAttribute('route'))
            && $route instanceof Route
            && (
                $route->getPath() === '/file/FilelistList/'
                || $route->getPath() === '/module/file/FilelistList'
                || $route->getPath() === '/module/file/list'
            )
        ) {
            foreach ($this->generateButtons((string)($request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? '')) as $buttonInfo) {
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

        $event->setButtons($buttons);
    }

    protected function createLink(string $title, string $shortTitle, Icon $icon, string $url, bool $addReturnUrl = true): array
    {
        return [
            'title' => $title,
            'icon' => $icon,
            'url' => $url . ($addReturnUrl ? '&returnUrl=' . rawurlencode($_SERVER['REQUEST_URI']) : ''),
        ];
    }

    private function getTypo3Request(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
