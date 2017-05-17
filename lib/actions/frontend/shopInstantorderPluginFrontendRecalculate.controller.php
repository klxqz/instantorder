<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendRecalculateController extends waJsonController {

    public function execute() {
        try {
            $_items = waRequest::post('items', array());
            $items = shopInstantorderItemsHelper::getItemsList($_items, true, false);
            shopInstantorderHelper::checkQuantity($items);
            $total = shopInstantorderItemsHelper::getItemsTotal($items);
            shopInstantorderHelper::getServices($items);
            shopInstantorderHelper::getFullPrice($items);
            $order = array(
                'currency' => wa()->getConfig()->getCurrency(false),
                'total' => $total,
                'items' => $items
            );
            $discount = shopDiscounts::calculate($order);
            $order['total'] -= $discount;

            $this->response = array(
                'order' => $order,
                'total' => shop_currency_html($order['total']),
                'discount' => shop_currency_html($discount),
            );
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

}
