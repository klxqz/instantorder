<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginSettingsAction extends waViewAction {

    public function execute() {
        $this->view->assign(array(
            'templates' => shopInstantorderPlugin::$templates,
            'plugin' => wa()->getPlugin('instantorder'),
            'route_hashs' => shopInstantorderRouteHelper::getRouteHashs(),
            'fields' => shopInstantorderHelper::getFields(),
            'address_fields' => shopInstantorderHelper::getAddressFields(),
        ));
    }

}
