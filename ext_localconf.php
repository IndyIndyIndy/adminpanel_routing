<?php
defined('TYPO3_MODE') or die('Access denied.');
call_user_func(
    function () {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['info'])) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['info']['submodules'] = array_replace_recursive(
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['adminpanel']['modules']['info']['submodules'],
                [
                    'routinginfo' => [
                        'module' => \ChristianEssl\AdminpanelRouting\Modules\RoutingInfo::class,
                        'after' => [
                            'general',
                        ],
                    ],
                ]
            );
        }
    }
);