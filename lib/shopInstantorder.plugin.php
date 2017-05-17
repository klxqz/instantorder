<?php

/**
 * @author  wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPlugin extends shopPlugin {

    public static $templates = array(
        'FrontendCart' => array(
            'name' => 'Основной',
            'tpl_path' => 'plugins/instantorder/templates/actions/frontend/',
            'tpl_name' => 'FrontendCart',
            'tpl_ext' => 'html',
            'public' => false
        ),
        'FrontendSelectSku' => array(
            'name' => 'Выбор артикула',
            'tpl_path' => 'plugins/instantorder/templates/actions/frontend/',
            'tpl_name' => 'FrontendSelectSku',
            'tpl_ext' => 'html',
            'public' => false
        ),
        'FrontendSuccess' => array(
            'name' => 'Заказ оформлен',
            'tpl_path' => 'plugins/instantorder/templates/actions/frontend/',
            'tpl_name' => 'FrontendSuccess',
            'tpl_ext' => 'html',
            'public' => false
        ),
        'instantorder_js' => array(
            'name' => 'Основной JavaScript',
            'tpl_path' => 'plugins/instantorder/js/',
            'tpl_name' => 'instantorder',
            'tpl_ext' => 'js',
            'public' => true
        ),
        'product_js' => array(
            'name' => 'product.js используется при выборе артикула',
            'tpl_path' => 'plugins/instantorder/js/',
            'tpl_name' => 'product',
            'tpl_ext' => 'js',
            'public' => true
        ),
        'instantorder_css' => array(
            'name' => 'Css',
            'tpl_path' => 'plugins/instantorder/css/',
            'tpl_name' => 'instantorder',
            'tpl_ext' => 'css',
            'public' => true
        ),
    );

    public function saveSettings($settings = array()) {
        $route_hash = waRequest::post('route_hash');
        $route_settings = waRequest::post('route_settings');

        if ($routes = $this->getSettings('routes')) {
            $settings['routes'] = $routes;
        } else {
            $settings['routes'] = array();
        }
        $settings['routes'][$route_hash] = $route_settings;
        $settings['route_hash'] = $route_hash;
        parent::saveSettings($settings);


        $templates = waRequest::post('templates', array());
        if (!empty($templates)) {
            foreach ($templates as $template_id => $template) {
                $s_template = self::$templates[$template_id];
                if (!empty($template['reset_tpl'])) {
                    $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                    $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                    @unlink($template_path);
                } else {
                    $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                    $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                    if (!file_exists($template_path)) {
                        $tpl_full_path = $s_template['tpl_path'] . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                        $template_path = wa()->getAppPath($tpl_full_path, 'shop');
                    }
                    $content = file_get_contents($template_path);
                    if (!empty($template['template']) && strcmp(str_replace("\r", "", $template['template']), str_replace("\r", "", $content)) != 0) {
                        $tpl_full_path = $s_template['tpl_path'] . $route_hash . '.' . $s_template['tpl_name'] . '.' . $s_template['tpl_ext'];
                        $template_path = wa()->getDataPath($tpl_full_path, $s_template['public'], 'shop', true);
                        $f = fopen($template_path, 'w');
                        if (!$f) {
                            throw new waException('Не удаётся сохранить шаблон. Проверьте права на запись ' . $template_path);
                        }
                        fwrite($f, $template['template']);
                        fclose($f);
                    }
                }
            }
        }
    }

    public function frontendHead() {
        if (!$this->getSettings('status')) {
            return false;
        }
        $route_hash = null;
        if (shopInstantorderRouteHelper::getRouteSettings(null, 'status')) {
            $route_hash = null;
            $route_settings = shopInstantorderRouteHelper::getRouteSettings();
        } elseif (shopInstantorderRouteHelper::getRouteSettings(0, 'status')) {
            $route_hash = 0;
            $route_settings = shopInstantorderRouteHelper::getRouteSettings(0);
        } else {
            return false;
        }

        $instantorder_js_url = shopInstantorderRouteHelper::getRouteTemplateUrl('instantorder_js', $route_hash) . '?' . wa()->getPlugin('instantorder')->getVersion();
        $instantorder_css_url = shopInstantorderRouteHelper::getRouteTemplateUrl('instantorder_css', $route_hash) . '?' . wa()->getPlugin('instantorder')->getVersion();

        $options = array(
            'url' => wa()->getRouteUrl(null, array('plugin' => 'instantorder', 'module' => 'frontend')),
            'recalculate_url' => wa()->getRouteUrl('shop/frontend/recalculate', array('plugin' => 'instantorder')),
            'order_url' => wa()->getRouteUrl('shop/frontend/order', array('plugin' => 'instantorder')),
            'instantorder_btn_selector' => ifset($route_settings['instantorder_btn_selector'], '.instantorder-btn'),
            'product_form_selector' => ifset($route_settings['product_form_selector'], 'form#cart-form'),
            'order_btn_text' => ifset($route_settings['order_btn_text'], 'Заказать'),
            'order_btn_class' => ifset($route_settings['order_btn_class'], 'btn-light-blue'),
            'close_btn_text' => ifset($route_settings['close_btn_text'], 'Продолжить покупки'),
            'select_btn_text' => ifset($route_settings['select_btn_text'], 'Выбрать'),
            'modal_title' => ifset($route_settings['modal_title'], 'Оформление заказа'),
            'phone_mask' => ifset($route_settings['phone_mask'], '+7 (999) 999-99-99'),
        );
        $json_options = json_encode($options);

        $this->addJS('js/maskedinput.js');
        $this->addJS('js/jquery.validate.min.js');
        $this->addCss('js/jquery.modal/css/jquery.modal.min.css');
        $this->addJS('js/jquery.modal/js/jquery.modal.min.js');
        waSystem::getInstance()->getResponse()->addJs(ltrim($instantorder_js_url, '/'));
        waSystem::getInstance()->getResponse()->addCss(ltrim($instantorder_css_url, '/'));
        $html = <<<HTML
<script type="text/javascript">
    $(function () {
        $.instantorder.init({$json_options});
    });
</script>
HTML;
        return $html;
    }

    public static function __callStatic($name, $arguments) {
        waLog::log("Метод shopInstantorderPlugin::$name() не существует.\nВозможно, данный метод устарел и больше не используется.");
    }

}
