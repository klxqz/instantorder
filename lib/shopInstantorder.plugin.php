<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPlugin extends shopPlugin {

    protected static $plugin;

    public function __construct($info) {
        parent::__construct($info);
        if (!self::$plugin) {
            self::$plugin = &$this;
        }
    }

    protected static function getThisPlugin() {
        if (self::$plugin) {
            return self::$plugin;
        } else {
            return wa()->getPlugin('instantorder');
        }
    }

    public function frontendProduct() {
        if ($this->getSettings('frontend_product')) {
            return array('cart' => self::display());
        }
    }

    public static function display() {
        if (wa()->getUser()->isAuth()) {
            $contact = wa()->getUser();
            //$res = $contact->get("address.region", "default");
            //print_r($res);
        }
        $plugin = self::getThisPlugin();
        $instantorder_model = new shopInstantorderPluginModel();
        $selected_fields = $instantorder_model->getAll();
        $view = wa()->getView();
        $view->assign('contact', $contact);
        $view->assign('settings', $plugin->getSettings());
        $view->assign('selected_fields', $selected_fields);
        $template_path = wa()->getAppPath('plugins/instantorder/templates/Instantorder.html', 'shop');
        $html = $view->fetch($template_path);
        return $html;
    }

}
