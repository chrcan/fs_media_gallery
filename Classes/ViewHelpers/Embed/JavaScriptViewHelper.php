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
            // TYPO3 v14: avoid ContentObjectRenderer property access (marked @internal in #102621)
            // use Fluid VariableProvider instead — 'data' contains the current content element record
            $data = $this->renderingContext->getVariableProvider()->get('data');
            if (is_array($data) && !empty($data['uid'])) {
                $blockName .= '.' . (int)$data['uid'];
            }
        } else {
            $blockName = (string)$this->arguments['name'];
        }

        if (!empty($this->arguments['moveToFooter']) && $this->getApplicationType() === 'FE') {
            $compressJs = $this->getCompressJsSetting();
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

    private function getCompressJsSetting(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request instanceof ServerRequestInterface) {
            $typoscript = $request->getAttribute('frontend.typoscript');
            if ($typoscript !== null && method_exists($typoscript, 'getConfigArray')) {
                return (bool)($typoscript->getConfigArray()['compressJs'] ?? false);
            }
        }
        return false;
    }

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
}
