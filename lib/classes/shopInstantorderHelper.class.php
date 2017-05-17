<?php

class shopInstantorderHelper {

    public static function getFields() {
        $result = array();
        $fields = waContactFields::getAll();
        unset($fields['address']);
        if (!empty($fields)) {
            foreach ($fields as $field) {
                $result[] = array(
                    'id' => $field->getId(),
                    'name' => ($field->getId() == 'name' ? 'Полное имя' : $field->getName()),
                );
            }
        }
        return $result;
    }

    public static function getAddressFields() {
        $result = array();
        $address = waContactFields::get('address');
        $address_fields = $address->getFields();
        if (!empty($address_fields)) {
            foreach ($address_fields as $field) {
                $result[$field->getId()] = array(
                    'id' => $field->getId(),
                    'name' => $field->getName(),
                );
            }
        }
        return $result;
    }

    public static function getContactInfoForm($fields) {
        $fields_config = array();
        foreach ($fields as $field) {
            $fields_config[$field['name']] = array(
                'localized_names' => $field['title'],
                'required' => $field['required'],
            );
        }
        $address_fields = self::getAddressFields();

        foreach ($fields_config as $field_id => $field) {
            if (isset($address_fields[$field_id])) {
                $fields_config['address.shipping']['fields'][$field_id] = $field;
                unset($fields_config[$field_id]);
            }
        }
        $form = waContactForm::loadConfig($fields_config, array('namespace' => 'customer'));
        if (wa()->getUser()->isAuth()) {
            $contact = wa()->getUser();
        } else {
            $contact = self::getSessionData('contact');
        }
        $contact && $form->setValue($contact);
        return $form;
    }

    public static function getSessionData($key, $default = null) {
        $data = wa()->getStorage()->get('shop/checkout');
        return isset($data[$key]) ? $data[$key] : $default;
    }

    public static function getOrderCart() {
        $data = waRequest::post();
        $_items = array();

        if (!empty($data['cart'])) {
            $cart = new shopCart();
            $items = $cart->items(false);
            self::checkQuantity($items);
            $total = $cart->total(false);
            $order = array(
                'currency' => wa()->getConfig()->getCurrency(false),
                'total' => $total,
                'items' => $items
            );

            $order['discount'] = $discount = shopDiscounts::calculate($order);
            $total = $total - $order['discount'];
            self::getServices($items);
            self::getFullPrice($items);
            return array(
                'items' => $items,
                'total' => $total,
                'count' => $cart->count(),
                'discount' => $discount,
            );
        } else {
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
                            throw new waException(_w('This product is not available for purchase'));
                        }
                    }
                }
            }
            $quantity = ifset($data['quantity'], 0);
            if ($quantity <= 0) {
                $quantity = 1;
            }

            if ($product && $sku) {
                $services = ifset($data['services'], array());
                if ($services) {
                    $variants = ifset($data['service_variant']);
                    $temp = array();
                    $service_ids = array();
                    foreach ($services as $service_id) {
                        if (isset($variants[$service_id])) {
                            $temp[$service_id] = $variants[$service_id];
                        } else {
                            $service_ids[] = $service_id;
                        }
                    }
                    if ($service_ids) {
                        $service_model = new shopServiceModel();
                        $temp_services = $service_model->getById($service_ids);
                        foreach ($temp_services as $row) {
                            $temp[$row['id']] = $row['variant_id'];
                        }
                    }
                    $services = $temp;
                }

                $item_id = 1;
                $_items[$item_id] = array(
                    'id' => $item_id,
                    'product_id' => $product['id'],
                    'sku_id' => $sku['id'],
                    'quantity' => $quantity,
                    'type' => 'product'
                );
                if ($services) {
                    $iteration = 1;
                    foreach ($services as $service_id => $variant_id) {
                        $_items[$item_id + $iteration] = array(
                            'id' => $item_id + $iteration,
                            'product_id' => $product['id'],
                            'sku_id' => $sku['id'],
                            'quantity' => $quantity,
                            'type' => 'service',
                            'service_id' => $service_id,
                            'service_variant_id' => $variant_id,
                            'parent_id' => $item_id,
                        );
                        $iteration++;
                    }
                }
            } else {
                throw new waException('product not found');
            }

            $items = shopInstantorderItemsHelper::getItemsList($_items, true, false);
            self::checkQuantity($items);
            $total = shopInstantorderItemsHelper::getItemsTotal($items);
            $order = array(
                'currency' => wa()->getConfig()->getCurrency(false),
                'total' => $total,
                'items' => $items
            );
            $order['discount'] = $discount = shopDiscounts::calculate($order);
            $total = $total - $order['discount'];
            self::getServices($items);
            self::getFullPrice($items);
            return array(
                'items' => $items,
                'total' => $total,
                'discount' => $discount,
            );
        }
    }

    public static function checkQuantity(&$items, &$errors = array()) {
        $errors = array();
        // check quantity
        if (!wa()->getSetting('ignore_stock_count')) {
            $product_model = new shopProductModel();
            $sku_model = new shopProductSkusModel();
            foreach ($items as &$item) {
                $product = $product_model->getById($item['product_id']);
                $sku = $sku_model->getById($item['sku_id']);
                // limit by main stock
                if (wa()->getSetting('limit_main_stock') && waRequest::param('stock_id')) {
                    $stock_id = waRequest::param('stock_id');
                    $product_stocks_model = new shopProductStocksModel();
                    $sku_stock = shopHelper::fillVirtulStock($product_stocks_model->getCounts($sku['id']));
                    if (isset($sku_stock[$stock_id])) {
                        $sku['count'] = $sku_stock[$stock_id];
                    }
                }

                if ($sku['count'] !== null && $item['quantity'] > $sku['count']) {
                    $quantity = $sku['count'] - $item['quantity'];
                    $name = $product['name'] . ($sku['name'] ? ' (' . $sku['name'] . ')' : '');
                    if (!$quantity) {
                        if ($sku['count'] > 0) {
                            $item['quantity'] = $sku['count'];
                            $errors[] = $item['error'] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $sku['count'], $name);
                        } else {
                            $item['quantity'] = 0;
                            $errors[] = $item['error'] = sprintf(_w('Oops! %s just went out of stock and is not available for purchase at the moment. We apologize for the inconvenience.'), $name);
                        }
                    } else {
                        $item['quantity'] = $sku['count'];
                        $errors[] = $item['error'] = sprintf(_w('Only %d pcs of %s are available, and you already have all of them in your shopping cart.'), $sku['count'], $name);
                    }
                }
            }
            unset($item);
        }
        return empty($errors) ? true : false;
    }

    public static function getServices(&$items) {

        $product_ids = $sku_ids = $service_ids = $type_ids = array();
        foreach ($items as $item) {
            $product_ids[] = $item['product_id'];
            $sku_ids[] = $item['sku_id'];
        }

        $product_ids = array_unique($product_ids);
        $sku_ids = array_unique($sku_ids);

        foreach ($items as $item_id => &$item) {
            if ($item['type'] == 'product') {
                $type_ids[] = $item['product']['type_id'];
            }
        }
        unset($item);

        $type_ids = array_unique($type_ids);

        // get available services for all types of products
        $type_services_model = new shopTypeServicesModel();
        $rows = $type_services_model->getByField('type_id', $type_ids, true);
        $type_services = array();
        foreach ($rows as $row) {
            $service_ids[$row['service_id']] = $row['service_id'];
            $type_services[$row['type_id']][$row['service_id']] = true;
        }

        // get services for products and skus, part 1
        $product_services_model = new shopProductServicesModel();
        $rows = $product_services_model->getByProducts($product_ids);
        foreach ($rows as $i => $row) {
            if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                unset($rows[$i]);
                continue;
            }
            $service_ids[$row['service_id']] = $row['service_id'];
        }

        $service_ids = array_unique(array_values($service_ids));

        // Get services
        $service_model = new shopServiceModel();
        $services = $service_model->getByField('id', $service_ids, 'id');
        shopRounding::roundServices($services);

        // get services for products and skus, part 2
        $product_services = $sku_services = array();
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            if (!$row['sku_id']) {
                $product_services[$row['product_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
            if ($row['sku_id']) {
                $sku_services[$row['sku_id']][$row['service_id']]['variants'][$row['service_variant_id']] = $row;
            }
        }

        // Get service variants
        $variant_model = new shopServiceVariantsModel();
        $rows = $variant_model->getByField('service_id', $service_ids, true);
        shopRounding::roundServiceVariants($rows, $services);
        foreach ($rows as $row) {
            $services[$row['service_id']]['variants'][$row['id']] = $row;
            unset($services[$row['service_id']]['variants'][$row['id']]['id']);
        }

        // When assigning services into cart items, we don't want service ids there
        foreach ($services as &$s) {
            unset($s['id']);
        }
        unset($s);


        // Assign service and product data into cart items
        foreach ($items as $item_id => $item) {
            if ($item['type'] == 'product') {
                $p = $item['product'];
                $item_services = array();
                // services from type settings
                if (isset($type_services[$p['type_id']])) {
                    foreach ($type_services[$p['type_id']] as $service_id => &$s) {
                        $item_services[$service_id] = $services[$service_id];
                    }
                }
                // services from product settings
                if (isset($product_services[$item['product_id']])) {
                    foreach ($product_services[$item['product_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            if (!isset($item_services[$service_id])) {
                                $item_services[$service_id] = $services[$service_id];
                            }
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                                // default variant is different for this product
                                if ($v['status'] == shopProductServicesModel::STATUS_DEFAULT) {
                                    $item_services[$service_id]['variant_id'] = $variant_id;
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                // services from sku settings
                if (isset($sku_services[$item['sku_id']])) {
                    foreach ($sku_services[$item['sku_id']] as $service_id => $s) {
                        if (!isset($s['status']) || $s['status']) {
                            // update variants
                            foreach ($s['variants'] as $variant_id => $v) {
                                if ($v['status']) {
                                    if ($v['price'] !== null) {
                                        $item_services[$service_id]['variants'][$variant_id]['price'] = $v['price'];
                                    }
                                } else {
                                    unset($item_services[$service_id]['variants'][$variant_id]);
                                }
                            }
                        } elseif (isset($item_services[$service_id])) {
                            // remove disabled service
                            unset($item_services[$service_id]);
                        }
                    }
                }
                foreach ($item_services as $s_id => &$s) {
                    if (!$s['variants']) {
                        unset($item_services[$s_id]);
                        continue;
                    }

                    if ($s['currency'] == '%') {
                        foreach ($s['variants'] as $v_id => $v) {
                            $s['variants'][$v_id]['price'] = $v['price'] * $item['price'] / 100;
                        }
                        $s['currency'] = $item['currency'];
                    }

                    if (count($s['variants']) == 1) {
                        $v_id = key($s['variants']);
                        $v = $s['variants'][$v_id];
                        $s['variant_id'] = $v_id;
                        $s['price'] = $v['price'];
                        unset($s['variants']);
                    }
                }
                unset($s);
                uasort($item_services, array('shopServiceModel', 'sortServices'));

                $items[$item_id]['services'] = $item_services;
            } else {
                $items[$item['parent_id']]['services'][$item['service_id']]['id'] = $item['id'];
                if (isset($item['service_variant_id'])) {
                    $items[$item['parent_id']]['services'][$item['service_id']]['variant_id'] = $item['service_variant_id'];
                }
                unset($items[$item_id]);
            }
        }
    }

    public static function getFullPrice(&$items) {
        foreach ($items as &$item) {
            $price = shop_currency($item['price'] * $item['quantity'], $item['currency'], null, false);
            if (isset($item['services'])) {
                foreach ($item['services'] as $s) {
                    if (!empty($s['id'])) {
                        if (isset($s['variants'])) {
                            $price += shop_currency($s['variants'][$s['variant_id']]['price'] * $item['quantity'], $s['currency'], null, false);
                        } else {
                            $price += shop_currency($s['price'] * $item['quantity'], $s['currency'], null, false);
                        }
                    }
                }
            }
            $item['full_price'] = $price;
            $item['full_price_html'] = shop_currency_html($price);
        }
        unset($item);
    }

}
