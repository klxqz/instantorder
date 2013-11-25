<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginBackendSaveController extends waJsonController {

    public function execute() {

        $app_settings_model = new waAppSettingsModel();
        $shop_pointsissue = waRequest::post('shop_instantorder');
        foreach ($shop_pointsissue as $name => $val) {
            $app_settings_model->set(array('shop', 'instantorder'), $name, $val);
        }

        $field_names = waRequest::post('field_names');
        $field_vals = waRequest::post('field_vals');
        $field_required = waRequest::post('field_required');

        $instantorder_model = new shopInstantorderPluginModel();
        $instantorder_model->truncate();
        if ($field_names) {
            foreach ($field_names as $id => $field_name) {
                $data = array('name' => $field_name, 'type' => $field_vals[$id], 'required' => $field_required[$id]);
                $instantorder_model->insert($data);
            }
        }
    }

}
