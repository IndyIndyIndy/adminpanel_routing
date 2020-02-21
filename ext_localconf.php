<?php

defined('TYPO3_MODE') or die('Access denied.');
call_user_func(
    function () {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['debug'])) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['debug']['submodules'] = array_replace_recursive(
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['debug']['submodules'],
                [
                    'routing' => [
                        'module' => \ChristianEssl\AdminpanelRouting\Modules\Routing::class,
                        'after' => [
                            'log',
                        ],
                    ],
                ]
            );
        }
    }
);

// TODO: refrain from using xclasses by placing the event listener into the core class
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Routing\PageRouter::class] = array(
    'className' => \ChristianEssl\AdminpanelRouting\XClass\PageRouterXClass::class
);

// TODO: replace with PSR-14 Event Listener for TYPO3 10
/** @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher */
$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
$signalSlotDispatcher->connect(
    \TYPO3\CMS\Core\Routing\PageRouter::class,
    'afterGenerateUri',
    \ChristianEssl\AdminpanelRouting\Service\RoutingInfoService::class,
    'receiveGeneratedUri'
);
