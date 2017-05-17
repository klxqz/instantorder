<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendSelectSkuAction extends shopFrontendProductAction {

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

        $product_id = waRequest::post('product_id');
        $product_model = new shopProductModel();
        $product = $product_model->getById($product_id);

        if (!$product) {
            throw new waException(_w('Product not found'), 404);
        }

        if ($types = waRequest::param('type_id')) {
            if (!in_array($product['type_id'], (array)$types)) {
                throw new waException(_w('Product not found'), 404);
            }
        }

        $product = new shopProduct($product, true);
        $this->prepareProduct($product);
        // get services
        list($services, $skus_services) = $this->getServiceVars($product);
        $this->view->assign('sku_services', $skus_services);
        $this->view->assign('services', $services);

        $compare = waRequest::cookie('shop_compare', array(), waRequest::TYPE_ARRAY_INT);
        $this->view->assign('compare', in_array($product['id'], $compare) ? $compare : array());
        $product->tags = array_map('htmlspecialchars', $product->tags);
        $this->view->assign('currency_info', $this->getCurrencyInfo());
        $this->view->assign('frontend_product', wa()->event('frontend_product', $product, array('menu', 'cart', 'block_aux', 'block')));
        $this->view->assign('stocks', shopHelper::getStocks(true));

        
        $product_js_url = shopInstantorderRouteHelper::getRouteTemplateUrl('product_js', $route_hash) . '?' . wa()->getPlugin('instantorder')->getVersion();
        $this->view->assign('product_js_url', $product_js_url);
        $FrontendSelectSku_tmp = shopInstantorderRouteHelper::getRouteTemplates($route_hash, 'FrontendSelectSku');
        $this->setTemplate($FrontendSelectSku_tmp['template_path']);
    }



}
