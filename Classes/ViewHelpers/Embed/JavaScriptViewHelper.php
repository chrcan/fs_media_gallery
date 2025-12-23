<?php

declare(strict_types=1);

/*
 * (c) 2025 rc design visual concepts (rc-design.at)
 * _________________________________________________
 * The TYPO3 project - inspiring people to share!
 * _________________________________________________
 */

namespace MiniFranske\FsMediaGallery\ViewHelpers\Embed;

/*                                                                        *
 * This script is part of the TYPO3 project.                              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Embed JavaScript view helper.
 */
class JavaScriptViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments.
     */
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'name',
            'string',
            'If empty, a combination of plugin name and the uid of the cObj is used.'
        );
        $this->registerArgument(
            'moveToFooter',
            'boolean',
            'If TRUE, adds the script to the document footer by PageRenderer->addJsFooterInlineCode().'
        );
    }

    /**
     * Renders child nodes as inline JavaScript content or adds it to page footer.
     *
     * @return string The rendered script content; if moveToFooter is TRUE the script content is added by PageRenderer->addJsFooterInlineCode() and an empty string is returned
     */
    public function render()
    {
        $content = $this->renderChildren();

        if (!is_string($content)) {
            return $content;
        }

        if (empty($this->arguments['name'])) {
            $blockName = 'tx_fsmediagallery';
            if ($cObj = $this->getContentObjectRenderer()) {
                $blockName .= '.' . $cObj->data['uid'];
            }
        } else {
            $blockName = (string)$this->arguments['name'];
        }

        if (!empty($this->arguments['moveToFooter']) && $this->getApplicationType() === 'FE') {
            // KORREKTUR für TYPO3 v14: $GLOBALS['TSFE'] ist entfernt → verwende Request Attribute
            $compressJs = $this->getCompressJsSetting();

            // add JS inline code to footer
            GeneralUtility::makeInstance(PageRenderer::class)->addJsFooterInlineCode(
                $blockName,
                $content,
                $compressJs
            );
            return '';
        }
        $lb = "\n";
        return '<script type="text/javascript">' . $lb . '/*<![CDATA[*/' . $lb .
            '/*' . $blockName . '*/' . $lb . $content . $lb . '/*]]>*/' . $lb . '</script>';
    }

    /**
     * Get compressJs setting from TypoScript config.
     * Neue Methode für TYPO3 v14: Ersatz für $GLOBALS['TSFE']->config['config']['compressJs']
     */
    private function getCompressJsSetting(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if ($request instanceof ServerRequestInterface) {
            // Korrektur: frontend.typoscript Attribut verwenden
            $typoscript = $request->getAttribute('frontend.typoscript');
            if ($typoscript !== null && method_exists($typoscript, 'getConfigArray')) {
                $config = $typoscript->getConfigArray();
                return (bool)($config['compressJs'] ?? false);
            }
        }

        // Fallback: Standardwert
        return false;
    }

    /**
     * String 'FE' if in FrontendApplication, 'BE' otherwise (also in CLI without request object).
     *
     * @internal
     */
    public function getApplicationType(): string
    {
        if (
            ($GLOBALS['TYPO3_REQUEST'] ?? null) instanceof ServerRequestInterface &&
            ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])->isFrontend()
        ) {
            return 'FE';
        }

        return 'BE';
    }

    private function getContentObjectRenderer(): ?ContentObjectRenderer
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        if ($request instanceof ServerRequestInterface) {
            // cObject direkt aus Request Attributen holen
            return $request->getAttribute('currentContentObject');
        }

        return null;
    }

    /**
     * Alternative Methode für Extbase Request - korrigierte Version ohne getRequest()
     */
    private function getExtbaseRequest(): ?ServerRequestInterface
    {
        // Direkter Zugriff auf globalen Request
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
