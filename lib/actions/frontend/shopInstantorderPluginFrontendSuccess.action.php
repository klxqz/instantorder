<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendSuccessAction extends shopFrontendAction {

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

        $order_id = $this->params['order_id'];
        $cart_mode = $this->params['cart_mode'];

        $order_model = new shopOrderModel();
        $order = $order_model->getOrder($order_id);
        $this->view->assign(array(
            'order' => $order,
            'cart_mode' => $cart_mode,
        ));
        $FrontendSuccess_tmp = shopInstantorderRouteHelper::getRouteTemplates($route_hash, 'FrontendSuccess');
        $this->setTemplate($FrontendSuccess_tmp['template_path']);
    }

}
