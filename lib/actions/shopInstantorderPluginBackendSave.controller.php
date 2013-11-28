<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginBackendSaveController extends waJsonController {

    protected $tmp_path = 'plugins/instantorder/templates/Instantorder.html';

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

        if (waRequest::post('reset_tpl')) {
            $template_path = wa()->getDataPath($this->tmp_path, false, 'shop', true);
            @unlink($template_path);
        } else {
            $post_template = waRequest::post('template');
            if (!$post_template) {
                throw new waException('Не определён шаблон');
            }

            $template_path = wa()->getDataPath($this->tmp_path, false, 'shop', true);
            if (!file_exists($template_path)) {
                $template_path = wa()->getAppPath($this->tmp_path, 'shop');
            }

            $template = file_get_contents($template_path);
            if ($template != $post_template) {
                $template_path = wa()->getDataPath($this->tmp_path, false, 'shop', true);

                $f = fopen($template_path, 'w');
                if (!$f) {
                    throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
                }
                fwrite($f, $post_template);
                fclose($f);
            }
        }
    }

}
