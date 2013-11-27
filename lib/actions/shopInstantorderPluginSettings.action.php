<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginSettingsAction extends waViewAction {

    protected $plugin_id = array('shop', 'instantorder');

    public function execute() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get($this->plugin_id);
        $fields = waContactFields::getAll(); 
        unset($fields['address']);
        $address = waContactFields::get('address');
        $address = $address->getFields();
        $instantorder_model = new shopInstantorderPluginModel();
        $selected_fields = $instantorder_model->getAll();

        $this->view->assign('settings', $settings);
        $this->view->assign('fields', $fields);
        $this->view->assign('address_fields', $address);
        $this->view->assign('selected_fields', $selected_fields);
        
    }

}
