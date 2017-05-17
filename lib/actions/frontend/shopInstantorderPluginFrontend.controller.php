<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendController extends waViewController {

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
        
        $sku_model = new shopProductSkusModel();
        $skus = $sku_model->getByField('product_id', waRequest::post('product_id'), true);
        
        if (!waRequest::post('sku_id') && !waRequest::post('features') && !empty($route_settings['enabled_select_sku']) && count($skus) > 1) {
            $this->executeAction(new shopInstantorderPluginFrontendSelectSkuAction());
        } else {
            $this->executeAction(new shopInstantorderPluginFrontendCartAction());
        }
    }

}
