<?php
namespace ChristianEssl\AdminpanelRouting\XClass;

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

use Psr\Http\Message\UriInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\Aspect\MappableProcessor;
use TYPO3\CMS\Core\Routing\Enhancer\DecoratingEnhancerInterface;
use TYPO3\CMS\Core\Routing\Enhancer\InflatableEnhancerInterface;
use TYPO3\CMS\Core\Routing\Enhancer\RoutingEnhancerInterface;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\RouteCollection;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Temporary XClass for placing a Signal Dispatcher in the generateUri() method
 * TODO: refrain from using xclasses by placing the event listener into the core class
 */
class PageRouterXClass extends PageRouter
{
    /**
     * API for generating a page where the $route parameter is typically an array (page record) or the page ID
     *
     * @param array|string $route
     * @param array $parameters an array of query parameters which can be built into the URI path, also consider the special handling of "_language"
     * @param string $fragment additional #my-fragment part
     * @param string $type see the RouterInterface for possible types
     * @return UriInterface
     * @throws InvalidRouteArgumentsException
     */
    public function generateUri($route, array $parameters = [], string $fragment = '', string $type = ''): UriInterface
    {
        // Resolve language
        $language = null;
        $languageOption = $parameters['_language'] ?? null;
        unset($parameters['_language']);
        if ($languageOption instanceof SiteLanguage) {
            $language = $languageOption;
        } elseif ($languageOption !== null) {
            $language = $this->site->getLanguageById((int)$languageOption);
        }
        if ($language === null) {
            $language = $this->site->getDefaultLanguage();
        }

        $pageId = 0;
        if (is_array($route)) {
            $pageId = (int)$route['uid'];
        } elseif (is_scalar($route)) {
            $pageId = (int)$route;
        }

        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('language', LanguageAspectFactory::createFromSiteLanguage($language));
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class, $context);
        $page = $pageRepository->getPage($pageId, true);
        $pagePath = ltrim($page['slug'] ?? '', '/');
        $originalParameters = $parameters;
        $collection = new RouteCollection();
        $defaultRouteForPage = new Route(
            '/' . $pagePath,
            [],
            [],
            ['utf8' => true, '_page' => $page]
        );
        $collection->add('default', $defaultRouteForPage);

        // cHash is never considered because cHash is built by this very method.
        unset($originalParameters['cHash']);
        $enhancers = $this->getEnhancersForPage($pageId, $language);
        foreach ($enhancers as $enhancer) {
            if ($enhancer instanceof RoutingEnhancerInterface) {
                $enhancer->enhanceForGeneration($collection, $originalParameters);
            }
        }
        foreach ($enhancers as $enhancer) {
            if ($enhancer instanceof DecoratingEnhancerInterface) {
                $enhancer->decorateForGeneration($collection, $originalParameters);
            }
        }

        $scheme = $language->getBase()->getScheme();
        $mappableProcessor = new MappableProcessor();
        $context = new RequestContext(
        // page segment (slug & enhanced part) is supposed to start with '/'
            rtrim($language->getBase()->getPath(), '/'),
            'GET',
            $language->getBase()->getHost(),
            $scheme ?: 'http',
            $scheme === 'http' ? $language->getBase()->getPort() ?? 80 : 80,
            $scheme === 'https' ? $language->getBase()->getPort() ?? 443 : 443
        );
        $generator = new UrlGenerator($collection, $context);
        $allRoutes = $collection->all();
        $allRoutes = array_reverse($allRoutes, true);
        $matchedRoute = null;
        $pageRouteResult = null;
        $uri = null;
        // map our reference type to symfony's custom paths
        $referenceType = $type === static::ABSOLUTE_PATH ? UrlGenerator::ABSOLUTE_PATH : UrlGenerator::ABSOLUTE_URL;
        /**
         * @var string $routeName
         * @var Route $route
         */
        foreach ($allRoutes as $routeName => $route) {
            try {
                $parameters = $originalParameters;
                if ($route->hasOption('deflatedParameters')) {
                    $parameters = $route->getOption('deflatedParameters');
                }
                $mappableProcessor->generate($route, $parameters);
                // ABSOLUTE_URL is used as default fallback
                $urlAsString = $generator->generate($routeName, $parameters, $referenceType);
                $uri = new Uri($urlAsString);
                /** @var Route $matchedRoute */
                $matchedRoute = $collection->get($routeName);
                parse_str($uri->getQuery() ?? '', $remainingQueryParameters);
                $enhancer = $route->getEnhancer();
                if ($enhancer instanceof InflatableEnhancerInterface) {
                    $remainingQueryParameters = $enhancer->inflateParameters($remainingQueryParameters);
                }
                $pageRouteResult = $this->buildPageArguments($route, $parameters, $remainingQueryParameters);
                break;
            } catch (MissingMandatoryParametersException $e) {
                // no match
            }
        }

        if (!$uri instanceof UriInterface) {
            throw new InvalidRouteArgumentsException('Uri could not be built for page "' . $pageId . '"', 1538390230);
        }

        if ($pageRouteResult && $pageRouteResult->areDirty()) {
            // for generating URLs this should(!) never happen
            // if it does happen, generator logic has flaws
            throw new InvalidRouteArgumentsException('Route arguments are dirty', 1537613247);
        }

        if ($matchedRoute && $pageRouteResult && !empty($pageRouteResult->getDynamicArguments())) {
            $cacheHash = $this->generateCacheHash($pageId, $pageRouteResult);

            if (!empty($cacheHash)) {
                $queryArguments = $pageRouteResult->getQueryArguments();
                $queryArguments['cHash'] = $cacheHash;
                $uri = $uri->withQuery(http_build_query($queryArguments, '', '&', PHP_QUERY_RFC3986));
            }
        }
        if ($fragment) {
            $uri = $uri->withFragment($fragment);
        }

        // TODO: replace with PSR-14 Event Listener for TYPO3 10
        $signalSlotDispatcher = GeneralUtility::makeInstance(ObjectManager::class)->get(Dispatcher::class);
        $signalSlotDispatcher->dispatch(PageRouter::class, 'afterGenerateUri', [
            'route' => $route,
            'uri' => $uri,
            'originalParameters' => $originalParameters,
            'resolvedParameters' => $parameters,
            'language' => $language
        ]);

        return $uri;
    }
}
