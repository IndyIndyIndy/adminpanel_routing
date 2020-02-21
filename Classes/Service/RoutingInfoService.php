<?php
namespace ChristianEssl\AdminpanelRouting\Service;

use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\Enhancer\AbstractEnhancer;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

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

/**
 * Parse and cache information related to routing uris
 */
class RoutingInfoService
{
    /**
     * @var array
     */
    protected static $generatedUris = [];

    /**
     * @var array
     */
    protected $availableEnhancers;

    /**
     * @var array
     */
    protected $availableAspects;

    /**
     * RoutingInfoService constructor.
     */
    public function __construct()
    {
        $this->availableEnhancers = $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers'] ?? [];
        $this->availableAspects = $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects'] ?? [];
    }

    /**
     * Receives all information for a generated uri from the PageRouter by a Event Listener and caches it
     *
     * @param Route $route
     * @param Uri $uri
     * @param array $originalParameters
     * @param array $resolvedParameters
     * @param SiteLanguage $language
     * @throws \ReflectionException
     */
    public function receiveGeneratedUri(Route $route, Uri $uri, array $originalParameters, array $resolvedParameters, SiteLanguage $language): void
    {
        // TODO: also output aspects from $route->getAspects()

        $page = $route->getOption('_page');

        $uriInfo = [
            'page' => '[' . $page['uid'] . '] ' . $page['title'],
            'language' => '[' . $language->getLanguageId() . '] ' . $language->getTitle(),
        ];

        $enhancer = $route->getEnhancer();
        if ($enhancer instanceof AbstractEnhancer) {
            foreach ($this->availableEnhancers as $enhancerName => $className) {
                if (get_class($enhancer) === $className) {
                    $uriInfo['enhancer'] = $enhancerName;
                    break;
                }
            }
        }

        if (count($route->getArguments()) > 0) {
            $uriInfo['arguments'] = $route->getArguments();
        }
        if (count($route->getDefaults()) > 0) {
            $uriInfo['defaults'] = $route->getDefaults();
        }
        if (count($originalParameters) > 0) {
            $uriInfo['originalParameters'] = $originalParameters;
        }
        if (is_array($route->getOption('deflatedParameters')) && count($route->getOption('deflatedParameters')) > 0) {
            $uriInfo['deflatedParameters'] = $route->getOption('deflatedParameters');
        }
        if (count($resolvedParameters) > 0) {
            $uriInfo['resolvedParameters'] = $resolvedParameters;
        }

        $uriInfo['intermediateUri'] = $route->getPath();
        $uriInfo['resolvedUri'] = (string)$uri;

        self::$generatedUris[] = $uriInfo;
    }

    /**
     * Return all uri information cached from generated uris in this page request
     *
     * @return array
     */
    public function getGeneratedUris(): array
    {
        return self::$generatedUris;
    }

}
