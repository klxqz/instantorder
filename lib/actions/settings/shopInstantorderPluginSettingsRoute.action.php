<?php

class shopInstantorderPluginSettingsRouteAction extends waViewAction {

    public function execute() {
        $route_hash = waRequest::get('route_hash');
        $view = wa()->getView();

        $btn_classes = array(
            'btn-green' => 'Зеленый',
            'btn-purple' => 'Фиолетовый',
            'btn-orange' => 'Оранжевый',
            'btn-pink' => 'Розовый',
            'btn-turquoise' => 'Бирюзовый',
            'btn-light-green' => 'Салотовый',
            'btn-light-blue' => 'Голубой',
            'btn-blue' => 'Синий',
            'btn-red' => 'Красный',
            'btn-light-red' => 'Светло-красный',
            'btn-yellow' => 'Желтый',
            'btn-black' => 'Черный',
            'btn-white' => 'Белый',
        );

        $view->assign(array(
            'route_hash' => $route_hash,
            'route_settings' => shopInstantorderRouteHelper::getRouteSettings($route_hash),
            'templates' => shopInstantorderRouteHelper::getRouteTemplates($route_hash),
            'fields' => shopInstantorderHelper::getFields(),
            'address_fields' => shopInstantorderHelper::getAddressFields(),
            'btn_classes' => $btn_classes,
        ));
    }

}
