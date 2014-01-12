<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginSettingsAction extends waViewAction {

    protected $plugin_id = array('shop', 'instantorder');
    protected $tmp_path = 'plugins/instantorder/templates/Instantorder.html';
    protected $themes = array(
        'base',
        'black-tie',
        'blitzer',
        'cupertino',
        'dark-hive',
        'dot-luv',
        'eggplant',
        'excite-bike',
        'flick',
        'hot-sneaks',
        'humanity',
        'le-frog',
        'mint-choc',
        'overcast',
        'pepper-grinder',
        'redmond',
        'smoothness',
        'south-street',
        'start',
        'sunny',
        'swanky-purse',
        'trontastic',
        'ui-darkness',
        'ui-lightness',
        'vader',
    );

    public function execute() {
        $app_settings_model = new waAppSettingsModel();
        $settings = $app_settings_model->get($this->plugin_id);
        $fields = waContactFields::getAll();
        unset($fields['address']);
        $address = waContactFields::get('address');
        $address = $address->getFields();
        $instantorder_model = new shopInstantorderPluginModel();
        $selected_fields = $instantorder_model->getAll();

        $change_tpl = false;

        $template_path = wa()->getDataPath($this->tmp_path, false, 'shop', true);
        if (file_exists($template_path)) {
            $change_tpl = true;
        } else {
            $template_path = wa()->getAppPath($this->tmp_path, 'shop');
        }

        $template = file_get_contents($template_path);

        $this->view->assign('themes', $this->themes);
        $this->view->assign('settings', $settings);
        $this->view->assign('fields', $fields);
        $this->view->assign('address_fields', $address);
        $this->view->assign('selected_fields', $selected_fields);
        $this->view->assign('template', $template);
        $this->view->assign('change_tpl', $change_tpl);
    }

}
