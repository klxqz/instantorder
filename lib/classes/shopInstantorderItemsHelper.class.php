<?php

class shopInstantorderItemsHelper {

    public static function getItemsList($_items, $full_info = true, $hierarchy = true) {
        $items = array();
        foreach ($_items as $index => $_item) {
            $items[ifset($_item['id'])] = array(
                'id' => ifset($_item['id']),
                'code' => '',
                'contact_id' => wa()->getUser()->getId(),
                'product_id' => $_item['product_id'],
                'sku_id' => $_item['sku_id'],
                'create_datetime' => ifset($_item['create_datetime'], date('Y-m-d H:i:s')),
                'quantity' => ifset($_item['quantity'], 1),
                'type' => ifset($_item['type'], 'product'),
                'service_id' => ifset($_item['service_id'], null),
                'service_variant_id' => ifset($_item['service_variant_id'], null),
                'parent_id' => ifset($_item['parent_id'], null),
            );
        }

        if ($full_info) {
            $rounding_enabled = shopRounding::isEnabled();

            $product_ids = $sku_ids = $service_ids = $variant_ids = array();
            foreach ($items as $item) {
                $product_ids[] = $item['product_id'];
                $sku_ids[] = $item['sku_id'];
                if ($item['type'] == 'service') {
                    $service_ids[] = $item['service_id'];
                    if ($item['service_variant_id']) {
                        $variant_ids[] = $item['service_variant_id'];
                    }
                }
            }

            $product_model = new shopProductModel();
            if (waRequest::param('url_type') == 2) {
                $products = $product_model->getWithCategoryUrl($product_ids);
            } else {
                $products = $product_model->getById($product_ids);
            }

            foreach ($products as $p_id => $p) {
                $products[$p_id]['original_price'] = $p['price'];
                $products[$p_id]['original_compare_price'] = $p['compare_price'];
            }

            $sku_model = new shopProductSkusModel();
            $skus = $sku_model->getByField('id', $sku_ids, 'id');

            foreach ($skus as $s_id => $s) {
                $skus[$s_id]['original_price'] = $s['price'];
                $skus[$s_id]['original_compare_price'] = $s['compare_price'];
            }

            $event_params = array(
                'products' => &$products,
                'skus' => &$skus
            );
            wa('shop')->event('frontend_products', $event_params);

            $rounding_enabled && shopRounding::roundProducts($products);
            $rounding_enabled && shopRounding::roundSkus($skus, $products);

            $service_model = new shopServiceModel();
            $services = $service_model->getByField('id', $service_ids, 'id');
            $rounding_enabled && shopRounding::roundServices($services);

            $service_variants_model = new shopServiceVariantsModel();
            $variants = $service_variants_model->getByField('id', $variant_ids, 'id');
            $rounding_enabled && shopRounding::roundServiceVariants($variants, $services);

            $product_services_model = new shopProductServicesModel();
            $rows = $product_services_model->getByProducts($product_ids);
            $rounding_enabled && shopRounding::roundServiceVariants($rows, $services);

            $product_services = $sku_services = array();
            foreach ($rows as $row) {
                if ($row['sku_id'] && !in_array($row['sku_id'], $sku_ids)) {
                    continue;
                }
                $service_ids[] = $row['service_id'];
                if (!$row['sku_id']) {
                    $product_services[$row['product_id']][$row['service_variant_id']] = $row;
                }
                if ($row['sku_id']) {
                    $sku_services[$row['sku_id']][$row['service_variant_id']] = $row;
                }
            }

            $image_model = null;
            foreach ($items as $item_key => &$item) {
                if ($item['type'] == 'product' && isset($products[$item['product_id']])) {
                    $item['product'] = $products[$item['product_id']];
                    if (!isset($skus[$item['sku_id']])) {
                        unset($items[$item_key]);
                        continue;
                    }
                    $sku = $skus[$item['sku_id']];

                    // Use SKU image instead of product image if specified
                    if ($sku['image_id'] && $sku['image_id'] != $item['product']['image_id']) {
                        $image_model || ($image_model = new shopProductImagesModel());
                        $img = $image_model->getById($sku['image_id']);
                        if ($img) {
                            $item['product']['image_id'] = $sku['image_id'];
                            $item['product']['image_filename'] = $img['filename'];
                            $item['product']['ext'] = $img['ext'];
                        }
                    }

                    $item['sku_code'] = $sku['sku'];
                    $item['purchase_price'] = $sku['purchase_price'];
                    $item['compare_price'] = $sku['compare_price'];
                    $item['sku_name'] = $sku['name'];
                    $item['currency'] = $item['product']['currency'];
                    $item['price'] = $sku['price'];
                    $item['name'] = $item['product']['name'];
                    $item['sku_file_name'] = $sku['file_name'];
                    if ($item['sku_name']) {
                        $item['name'] .= ' (' . $item['sku_name'] . ')';
                    }
                    // Fix for purchase price when rounding is enabled
                    if (!empty($item['product']['unconverted_currency']) && $item['product']['currency'] != $item['product']['unconverted_currency']) {
                        $item['purchase_price'] = shop_currency($item['purchase_price'], $item['product']['unconverted_currency'], $item['product']['currency'], false);
                    }
                } elseif ($item['type'] == 'service' && isset($services[$item['service_id']])) {
                    $item['name'] = $item['service_name'] = $services[$item['service_id']]['name'];
                    $item['currency'] = $services[$item['service_id']]['currency'];
                    $item['service'] = $services[$item['service_id']];
                    $item['variant_name'] = $variants[$item['service_variant_id']]['name'];
                    if ($item['variant_name']) {
                        $item['name'] .= ' (' . $item['variant_name'] . ')';
                    }
                    $item['price'] = $variants[$item['service_variant_id']]['price'];
                    if (isset($product_services[$item['product_id']][$item['service_variant_id']])) {
                        if ($product_services[$item['product_id']][$item['service_variant_id']]['price'] !== null) {
                            $item['price'] = $product_services[$item['product_id']][$item['service_variant_id']]['price'];
                        }
                    }
                    if (isset($sku_services[$item['sku_id']][$item['service_variant_id']])) {
                        if ($sku_services[$item['sku_id']][$item['service_variant_id']]['price'] !== null) {
                            $item['price'] = $sku_services[$item['sku_id']][$item['service_variant_id']]['price'];
                        }
                    }
                    if ($item['currency'] == '%') {
                        $p = $items[$item['parent_id']];
                        $item['price'] = $item['price'] * $p['price'] / 100;
                        $item['currency'] = $p['currency'];
                    }
                }
            }
            unset($item);
        }

        // sort
        foreach ($items as $item_id => $item) {
            if ($item['parent_id']) {
                $items[$item['parent_id']]['services'][] = $item;
                unset($items[$item_id]);
            }
        }

        if (!$hierarchy) {
            $result = array();
            foreach ($items as $item_id => $item) {
                if (isset($item['services'])) {
                    $i = $item;
                    unset($i['services']);
                    $result[$item_id] = $i;
                    foreach ($item['services'] as $s) {
                        $result[$s['id']] = $s;
                    }
                } else {
                    $result[$item_id] = $item;
                }
            }
            $items = $result;
        }
        return $items;
    }

    public static function getItemsTotal($items) {

        $sku_ids = array();
        foreach ($items as $item) {
            if ($item['type'] == 'product') {
                $sku_ids[$item['sku_id']] = $item['quantity'];
            }
        }

        $skus_model = new shopProductSkusModel();

        $skus = $skus_model->getById(array_keys($sku_ids));

        if (!$skus) {
            return 0.0;
        }

        foreach ($skus as &$sku) {
            $sku['quantity'] = $sku_ids[$sku['id']];
        }
        unset($sku);


        $product_ids = array();
        foreach ($skus as $k => $sku) {
            $product_ids[] = $sku['product_id'];
            $skus[$k]['original_price'] = $sku['price'];
            $skus[$k]['original_compare_price'] = $sku['compare_price'];
        }
        $product_ids = array_unique($product_ids);
        $product_model = new shopProductModel();
        $products = $product_model->getById($product_ids);

        foreach ($products as $p_id => $p) {
            $products[$p_id]['original_price'] = $p['price'];
            $products[$p_id]['original_compare_price'] = $p['compare_price'];
        }
        $event_params = array(
            'products' => &$products,
            'skus' => &$skus
        );
        wa('shop')->event('frontend_products', $event_params);
        shopRounding::roundSkus($skus);
        $products_total = 0.0;
        foreach ($skus as $s) {
            $products_total += $s['frontend_price'] * $s['quantity'];
        }
        // services
        $services_total = self::getServicesTotal($items, $event_params);
        return (float) ($products_total + $services_total);
    }

    public static function getServicesTotal($items, $products_skus) {
        $service_ids = array();
        foreach ($items as $item) {
            if ($item['type'] == 'service') {
                $service_ids[] = $item['service_id'];
            }
        }

        $service_model = new shopServiceModel();
        $services = $service_model->getById($service_ids);

        if (!$services) {
            return 0.0;
        }

        foreach ($items as $index => &$item) {
            if ($item['type'] == 'service') {
                $item['currency'] = $services[$item['service_id']]['currency'];
            } else {
                unset($items[$index]);
            }
        }
        unset($item);
        $services = $items;



        $sku_ids = array();
        $variant_ids = array();
        $product_ids = array();
        $service_stubs = array();
        foreach ($services as $s) {
            if ($s['service_variant_id']) {
                $variant_ids[] = $s['service_variant_id'];
            }
            $product_ids[] = $s['product_id'];
            if ($s['currency'] == '%') {
                $sku_ids[] = $s['sku_id'];
            }

            $service_stubs[$s['service_id']] = array(
                'id' => $s['service_id'],
                'currency' => $s['currency'],
            );
        }
        $variant_ids = array_unique($variant_ids);
        $product_ids = array_unique($product_ids);
        $sku_ids = array_unique($sku_ids);

        // get variant settings
        $rounding_enabled = shopRounding::isEnabled();
        $variants_model = new shopServiceVariantsModel();
        $variants = $variants_model->getWithPrice($variant_ids);
        $rounding_enabled && shopRounding::roundServiceVariants($variants, $service_stubs);

        // get products/skus settings
        $product_services_model = new shopProductServicesModel();
        $products_services = $product_services_model->getByProducts($product_ids, true);

        $primary = wa('shop')->getConfig()->getCurrency();
        $frontend_currency = wa('shop')->getConfig()->getCurrency(false);

        // Calculate total amount for all services
        $services_total = 0;
        foreach ($services as $s) {
            $p_id = $s['product_id'];
            $sku_id = $s['sku_id'];
            $s_id = $s['service_id'];
            $v_id = $s['service_variant_id'];
            $p_services = isset($products_services[$p_id]) ? $products_services[$p_id] : array();

            $s['price'] = $variants[$v_id]['price'];

            // price variant for sku
            if (!empty($p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['price'])) {
                shopRounding::roundServiceVariants($p_services['skus'][$sku_id][$s_id]['variants'], array(array('id' => $s['service_id'], 'currency' => $s['currency'])));
                $s['price'] = $p_services['skus'][$sku_id][$s_id]['variants'][$v_id]['price'];
            }

            if ($s['currency'] == '%') {
                if (isset($products_skus['skus'][$s['parent_id']])) {
                    $sku_price = $products_skus['skus'][$s['parent_id']]['frontend_price'];
                } else {
                    // most likely never happen case, but just in case
                    $product = $products_skus['products'][$s['product_id']];
                    $product_price = $product['price'];
                    $product_currency = $product['currency'] !== null ? $product['currency'] : $primary;
                    $sku_price = shop_currency($product_price, $product_currency, $frontend_currency, false);
                }
                $s['price'] = $s['price'] * $sku_price / 100;
            } else {
                $s['price'] = shop_currency($s['price'], $variants[$v_id]['currency'], $frontend_currency, false);
            }

            $services_total += $s['price'] * $s['quantity'];
        }
        return $services_total;
    }

}
