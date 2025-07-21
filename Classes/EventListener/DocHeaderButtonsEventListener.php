<?php

namespace MiniFranske\FsMediaGallery\EventListener;

use MiniFranske\FsMediaGallery\Service\AbstractBeAlbumButtons;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\Components\ModifyButtonBarEvent;
use TYPO3\CMS\Core\Imaging\Icon;

class DocHeaderButtonsEventListener extends AbstractBeAlbumButtons
{
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
                $button = $event->getButtonBar()->makeLinkButton();
                $button->setShowLabelText(true);
                $button->setIcon($buttonInfo['icon']);
                $button->setTitle($buttonInfo['title']);
                if (str_starts_with((string)$buttonInfo['url'], 'alert')) {
                    // using CSS class to trigger confirmation in modal box
                    $button->setClasses('t3js-modal-trigger')
                        ->setDataAttributes([
                            'severity' => 'warning',
                            'title' => $buttonInfo['title'],
                            'bs-content' => htmlspecialchars(substr((string)$buttonInfo['url'], 6)),
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
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
