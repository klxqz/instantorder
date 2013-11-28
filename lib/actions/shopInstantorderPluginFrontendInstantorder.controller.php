<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendInstantorderController extends waJsonController {

    public function execute() {
        $plugin = wa()->getPlugin('instantorder');
        $fields = waRequest::post('fields', array());
        $product_id = waRequest::post('product_id');
        $quantity = waRequest::post('quantity');
        $sku_id = waRequest::post('sku_id');
        
        if ($plugin->getSettings('is_captcha') && !wa()->getCaptcha()->isValid()) {
            $this->errors = _w('Invalid captcha code');
            return false;
        }

        if (wa()->getUser()->isAuth()) {
            $contact = wa()->getUser();
        } else {
            $contact = new waContact();
        }

        $instantorder_model = new shopInstantorderPluginModel();
        $selected_fields = $instantorder_model->getAll();
        $address = array();
        foreach ($selected_fields as &$selected_field) {
            $val = isset($fields[$selected_field['type']]) ? $fields[$selected_field['type']] : null;
            if ($val) {
                if (preg_match("/address\.(.+)/", $selected_field['type'], $match)) {
                    $address[$match[1]] = $val;
                } else {
                    $contact->set($selected_field['type'], $val);
                }
            }
        }

        if ($address) {
            $contact->set('address.shipping', $address);
            $contact->set('address.billing', $address);
        }

        $data = array(
            'sku_id' => $sku_id,
            'product_id' => $product_id,
            'quantity' => $quantity,
        );
        $this->addToCart($data);
        $order_id = $this->createOrder($contact);
        $plugin = wa()->getPlugin('instantorder');
        $successful_order = $plugin->getSettings('successful_order');
        $successful_order = str_replace('{order_id}', shopHelper::encodeOrderId($order_id), $successful_order);
        $this->response['message'] = $successful_order;
    }

    protected function createOrder($contact) {
        $cart = new shopCart();
        $items = $cart->items(false);
        // remove id from item
        foreach ($items as &$item) {
            unset($item['id']);
            unset($item['parent_id']);
        }
        unset($item);

        $order = array(
            'contact' => $contact,
            'items' => $items,
            'total' => $cart->total(false),
            'params' => array(),
        );

        $order['discount'] = shopDiscounts::apply($order);

        $routing_url = wa()->getRouting()->getRootUrl();
        $order['params']['storefront'] = wa()->getConfig()->getDomain() . ($routing_url ? '/' . $routing_url : '');

        $order['params']['ip'] = waRequest::getIp();
        $order['params']['user_agent'] = waRequest::getUserAgent();

        foreach (array('shipping', 'billing') as $ext) {
            $address = $contact->getFirst('address.' . $ext);
            if (!$address) {
                $address = $contact->getFirst('address');
            }
            if ($address) {
                foreach ($address['data'] as $k => $v) {
                    $order['params'][$ext . '_address.' . $k] = $v;
                }
            }
        }

        $order['shipping'] = 0;

        $order['comment'] = 'Заказ создан через форму "Быстрый заказ"';

        $workflow = new shopWorkflow();
        if ($order_id = $workflow->getActionById('create')->run($order)) {
            $cart->clear();
            return $order_id;
        }
    }

    protected function addToCart($data) {
        $cart_model = new shopCartItemsModel();
        $code = waRequest::cookie('shop_cart');
        if (!$code) {
            $code = md5(uniqid(time(), true));
            wa()->getResponse()->setCookie('shop_cart', $code, time() + 30 * 86400, null, '', false, true);
        }


        $sku_model = new shopProductSkusModel();
        $product_model = new shopProductModel();
        if (!isset($data['product_id'])) {
            $sku = $sku_model->getById($data['sku_id']);
            $product = $product_model->getById($sku['product_id']);
        } else {
            $product = $product_model->getById($data['product_id']);
            if (isset($data['sku_id'])) {
                $sku = $sku_model->getById($data['sku_id']);
            } else {
                if (isset($data['features'])) {
                    $product_features_model = new shopProductFeaturesModel();
                    $sku_id = $product_features_model->getSkuByFeatures($product['id'], $data['features']);
                    if ($sku_id) {
                        $sku = $sku_model->getById($sku_id);
                    } else {
                        $sku = null;
                    }
                } else {
                    $sku = $sku_model->getById($product['sku_id']);
                    if (!$sku['available']) {
                        $sku = $sku_model->getByField(array('product_id' => $product['id'], 'available' => 1));
                    }

                    if (!$sku) {
                        return false;
                    }
                }
            }
        }

        $quantity = $data['quantity'];

        if ($product && $sku) {
            // check quantity
            if (!wa()->getSetting('ignore_stock_count')) {
                $c = $cart_model->countSku($code, $sku['id']);
                if ($sku['count'] !== null && $c + $quantity > $sku['count']) {
                    $quantity = $sku['count'] - $c;
                    if (!$quantity) {
                        return false;
                    } else {
                        return false;
                    }
                }
            }
            $services = array();
            $item_id = null;
            $item = $cart_model->getItemByProductAndServices($code, $product['id'], $sku['id'], $services);
            if ($item) {
                $item_id = $item['id'];
                $cart_model->updateById($item_id, array('quantity' => $item['quantity'] + $quantity));
                if ($services) {
                    $cart_model->updateByField('parent_id', $item_id, array('quantity' => $item['quantity'] + $quantity));
                }
            }
            if (!$item_id) {
                $data = array(
                    'code' => $code,
                    'contact_id' => wa()->getUser()->getId(),
                    'product_id' => $product['id'],
                    'sku_id' => $sku['id'],
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'quantity' => $quantity
                );
                $item_id = $cart_model->insert($data + array('type' => 'product'));
                if ($services) {
                    foreach ($services as $service_id => $variant_id) {
                        $data_service = array(
                            'service_id' => $service_id,
                            'service_variant_id' => $variant_id,
                            'type' => 'service',
                            'parent_id' => $item_id
                        );
                        $cart_model->insert($data + $data_service);
                    }
                }
            }
            // update shop cart session data

            wa()->getStorage()->remove('shop/cart');
            return true;
        } else {
            return false;
        }
    }

}
