<?php
namespace ChristianEssl\AdminpanelRouting\Modules;

/***
 *
 * This file is part of the "AdminpanelRouting" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Christian Eßl <indy.essl@gmail.com>, https://christianessl.at
 *
 ***/

use ChristianEssl\AdminpanelRouting\Service\RoutingInfoService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\AbstractSubModule;
use TYPO3\CMS\Adminpanel\ModuleApi\DataProviderInterface;
use TYPO3\CMS\Adminpanel\ModuleApi\ModuleData;
use TYPO3\CMS\Adminpanel\ModuleApi\ResourceProviderInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Show routing debug info in the adminpanel
 */
class Routing extends AbstractSubModule implements DataProviderInterface, ResourceProviderInterface
{
    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return 'debug_routing';
    }

    /**
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:adminpanel_routing/Resources/Private/Language/locallang.xlf:label'
        );
    }

    /**
     * @param ModuleData $moduleData
     *
     * @return string
     */
    public function getContent(ModuleData $moduleData): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $templateNameAndPath = 'EXT:adminpanel_routing/Resources/Private/Templates/Debug/Routing.html';
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName($templateNameAndPath));
        $view->setPartialRootPaths([
            'EXT:adminpanel/Resources/Private/Partials',
            'EXT:adminpanel_routing/Resources/Private/Partials',
        ]);
        $view->assignMultiple($moduleData->getArrayCopy());
        return $view->render();
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return \TYPO3\CMS\Adminpanel\ModuleApi\ModuleData
     */
    public function getDataToStore(ServerRequestInterface $request): ModuleData
    {
        $routingInfoService = GeneralUtility::makeInstance(RoutingInfoService::class);
        $generatedUris = $routingInfoService->getGeneratedUris();
        return new ModuleData([
            'generatedUris' => $generatedUris
        ]);
    }

    /**
     * @return array
     */
    public function getJavaScriptFiles(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getCssFiles(): array
    {
        return [];
    }

}
