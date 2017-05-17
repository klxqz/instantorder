<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendCartAction extends waViewAction {

    public function execute() {
        if (!wa()->getPlugin('instantorder')->getSettings('status')) {
            throw new waException(_ws("Page not found"), 404);
        }
        $route_hash = null;
        if (shopInstantorderRouteHelper::getRouteSettings(null, 'status')) {
            $route_hash = null;
            $route_settings = shopInstantorderRouteHelper::getRouteSettings();
        } elseif (shopInstantorderRouteHelper::getRouteSettings(0, 'status')) {
            $route_hash = 0;
            $route_settings = shopInstantorderRouteHelper::getRouteSettings(0);
        } else {
            throw new waException(_ws("Page not found"), 404);
        }

        try {
            $cart = shopInstantorderHelper::getOrderCart();
        } catch (Exception $ex) {
            $errors = $ex->getMessage();
        }
        $fields = ifset($route_settings['fields'], array());

        $this->view->assign(array(
            'cart_mode' => waRequest::post('cart', 0),
            'form' => shopInstantorderHelper::getContactInfoForm($fields),
            'comment_field' => ifset($route_settings['comment_field']),
            'cart' => ifset($cart),
            'plugin_url' => wa()->getPlugin('instantorder')->getPluginStaticUrl(),
            'errors' => ifset($errors),
        ));

        $FrontendCart_tmp = shopInstantorderRouteHelper::getRouteTemplates($route_hash, 'FrontendCart');
        $this->setTemplate($FrontendCart_tmp['template_path']);
    }

}
