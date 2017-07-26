<?php

/**
 * @author wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendOrderController extends waJsonController {

    public function execute() {
        try {
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

            if (!empty($route_settings['service_agreement']) && $route_settings['service_agreement'] == 'checkbox' && !waRequest::post('service_agreement', 0)) {
                throw new waException('Необходимо подтвердить согласие на обработку персональных данных');
            }

            if (!empty($route_settings['captcha'])) {
                $code = waRequest::post('captcha');
                if ($route_settings['captcha'] == 'waReCaptcha') {
                    $options = array(
                        'sitekey' => $route_settings['captcha_sitekey'],
                        'secret' => $route_settings['captcha_secret'],
                    );
                    $captcha = new waReCaptcha($options);
                } elseif ($route_settings['captcha'] == 'waCaptcha') {
                    $captcha = new waCaptcha();
                }
                if (!$captcha->isValid($code)) {
                    throw new waException('Капча введена неверно');
                }
            }

            $cart_mode = waRequest::post('cart_mode', 0);
            $_items = waRequest::post('items', array());
            $customer = waRequest::post('customer', array());
            $items = shopInstantorderItemsHelper::getItemsList($_items, true, false);
            if (!shopInstantorderHelper::checkQuantity($items, $errors)) {
                throw new waException($errors[0]);
            }
            $total = shopInstantorderItemsHelper::getItemsTotal($items);

            if (!empty($route_settings['use_wholesale_plugin']) && class_exists('shopWholesale') && wa()->getPlugin('wholesale')->getSettings('status')) {
                $result = shopWholesale::checkOrder($items);
                if (!$result['result']) {
                    throw new waException($result['message']);
                }
            }

            if (!empty($route_settings['use_minsum_plugin']) && class_exists('shopMinsum') && wa()->getPlugin('minsum')->getSettings('status')) {
                $result = shopMinsum::checkOrder($items);
                if (!$result['result']) {
                    throw new waException($result['message']);
                }
            }

            if (wa()->getUser()->isAuth()) {
                $contact = wa()->getUser();
            } elseif (shopInstantorderHelper::getSessionData('contact')) {
                $contact = shopInstantorderHelper::getSessionData('contact');
            } else {
                $contact = new waContact();
            }
            if ($contact) {
                if (!$contact->get('address.shipping') && $addresses = $contact->get('address')) {
                    $contact->set('address.shipping', $addresses[0]);
                }
            }
            if ($customer) {
                foreach ($customer as $field => $value) {
                    $contact->set($field, $value);
                }
            }

            $fields = ifset($route_settings['fields'], array());
            $form = shopInstantorderHelper::getContactInfoForm($fields);

            if (!$form->isValid($contact)) {
                throw new waException('Неверно заполнены контактные данные');
            }


            // remove id from item
            foreach ($items as &$item) {
                unset($item['id']);
                unset($item['parent_id']);
            }
            unset($item);

            $order = array(
                'contact' => $contact,
                'items' => $items,
                'total' => $total,
                'params' => array(),
            );

            $order['discount_description'] = null;
            $order['discount'] = shopDiscounts::apply($order, $order['discount_description']);

            $order['shipping'] = 0;

            $routing_url = wa()->getRouting()->getRootUrl();
            $order['params']['storefront'] = wa()->getConfig()->getDomain() . ($routing_url ? '/' . $routing_url : '');
            if (wa()->getStorage()->get('shop_order_buybutton')) {
                $order['params']['sales_channel'] = 'buy_button:';
            }

            if (( $ref = waRequest::cookie('referer'))) {
                $order['params']['referer'] = $ref;
                $ref_parts = @parse_url($ref);
                $order['params']['referer_host'] = $ref_parts['host'];
                // try get search keywords
                if (!empty($ref_parts['query'])) {
                    $search_engines = array(
                        'text' => 'yandex\.|rambler\.',
                        'q' => 'bing\.com|mail\.|google\.',
                        's' => 'nigma\.ru',
                        'p' => 'yahoo\.com'
                    );
                    $q_var = false;
                    foreach ($search_engines as $q => $pattern) {
                        if (preg_match('/(' . $pattern . ')/si', $ref_parts['host'])) {
                            $q_var = $q;
                            break;
                        }
                    }
                    // default query var name
                    if (!$q_var) {
                        $q_var = 'q';
                    }
                    parse_str($ref_parts['query'], $query);
                    if (!empty($query[$q_var])) {
                        $order['params']['keyword'] = $query[$q_var];
                    }
                }
            }

            if (( $utm = waRequest::cookie('utm'))) {
                $utm = json_decode($utm, true);
                if ($utm && is_array($utm)) {
                    foreach ($utm as $k => $v) {
                        $order['params']['utm_' . $k] = $v;
                    }
                }
            }

            if (( $landing = waRequest::cookie('landing')) && ( $landing = @parse_url($landing))) {
                if (!empty($landing['query'])) {
                    @parse_str($landing['query'], $arr);
                    if (!empty($arr['gclid']) && !empty($order['params']['referer_host']) && strpos($order['params']['referer_host'], 'google') !== false) {
                        $order['params']['referer_host'] .= ' (cpc)';
                        $order['params']['cpc'] = 1;
                    } else if (!empty($arr['_openstat']) && !empty($order['params']['referer_host']) && strpos($order['params']['referer_host'], 'yandex') !== false) {
                        $order['params']['referer_host'] .= ' (cpc)';
                        $order['params']['openstat'] = $arr['_openstat'];
                        $order['params']['cpc'] = 1;
                    }
                }

                $order['params']['landing'] = $landing['path'];
            }

            // A/B tests
            $abtest_variants_model = new shopAbtestVariantsModel();
            foreach (waRequest::cookie() as $k => $v) {
                if (substr($k, 0, 5) == 'waabt') {
                    $variant_id = $v;
                    $abtest_id = substr($k, 5);
                    if (wa_is_int($abtest_id) && wa_is_int($variant_id)) {
                        $row = $abtest_variants_model->getById($variant_id);
                        if ($row && $row['abtest_id'] == $abtest_id) {
                            $order['params']['abt' . $abtest_id] = $variant_id;
                        }
                    }
                }
            }

            $order['params']['ip'] = waRequest::getIp();
            $order['params']['user_agent'] = waRequest::getUserAgent();

            foreach (array('shipping', 'billing') as $ext) {
                $address = $contact->getFirst('address.' . $ext);
                if ($address) {
                    foreach ($address['data'] as $k => $v) {
                        $order['params'][$ext . '_address.' . $k] = $v;
                    }
                }
            }

            if (!empty($route_settings['comment_field']['enabled']) && waRequest::post('comment')) {
                $order['comment'] = waRequest::post('comment');
            }

            list($stock_id, $virtualstock_id) = self::determineStockIds($order);
            if ($virtualstock_id) {
                $order['params']['virtualstock_id'] = $virtualstock_id;
            }
            if ($stock_id) {
                $order['params']['stock_id'] = $stock_id;
            }

            $workflow = new shopWorkflow();
            if ($order_id = $workflow->getActionById('create')->run($order)) {

                $step_number = shopCheckout::getStepNumber();
                $checkout_flow = new shopCheckoutFlowModel();
                $checkout_flow->add(array(
                    'step' => $step_number
                ));

                if ($cart_mode) {
                    $cart = new shopCart();
                    $cart->clear();
                    wa()->getStorage()->remove('shop/checkout');
                }

                $order_model = new shopOrderModel();
                $log_model = new shopOrderLogModel();
                $order = $order_model->getOrder($order_id);
                $log_data = array(
                    'action_id' => 'comment',
                    'order_id' => $order_id,
                    'before_state_id' => $order['state_id'],
                    'after_state_id' => $order['state_id'],
                    'text' => 'Заказ создан через плагин «<a target="_blank" href="?action=plugins#/instantorder/">Быстрый заказ</a>» ',
                );
                $log_model->add($log_data);

                $success_action = new shopInstantorderPluginFrontendSuccessAction(array('order_id' => $order_id, 'cart_mode' => $cart_mode));
                $this->response['html'] = $success_action->display();
            } else {
                throw new waException('Ошибка создания заказа');
            }
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
        }
    }

    protected static function determineStockIds($order) {
        if (!class_exists('shopStockRulesModel')) {
            return array(null, null);
        }
        $stock_rules_model = new shopStockRulesModel();
        $rules = $stock_rules_model->getRules();
        $stocks = shopHelper::getStocks();

        /**
         * @event frontend_checkout_stock_rules
         *
         * Hook allows to implement custom rules to automatically select stock for new orders.
         *
         * $params['rules'] is a list of rules from `shop_stock_rules` table.
         * Plugins are expected to modify items in $params['rules'] by creating 'fulfilled' key (boolean)
         * for rule types plugin is responsible for.
         *
         * See also `backend_settings_stocks` event for how to set up settings form for such rules.
         *
         * @param array $params
         * @param array[array] $params['order'] order data
         * @param array[array] $params['rules'] list of rules to modify.
         * @param array[array] $params['stocks'] same as shopHelper::getStocks()
         * @return none
         */
        $event_params = array(
            'order' => $order,
            'stocks' => $stocks,
            'rules' => &$rules,
        );
        self::processBuiltInRules($event_params);
        wa('shop')->event('frontend_checkout_stock_rules', $event_params);

        $groups = $stock_rules_model->prepareRuleGroups($rules);
        foreach ($groups as $g) {
            if (($g['stock_id'] && empty($stocks[$g['stock_id']])) || ($g['virtualstock_id'] && empty($stocks['v' . $g['virtualstock_id']]))) {
                continue;
            }

            $all_fulfilled = true;
            foreach ($g['conditions'] as $rule) {
                if (!ifset($rule['fulfilled'], false)) {
                    $all_fulfilled = false;
                    break;
                }
            }
            if ($all_fulfilled) {
                return array($g['stock_id'], $g['virtualstock_id']);
            }
        }

        // No rule matched the order. Use stock specified in routing params.
        $virtualstock_id = null;
        $stock_id = waRequest::param('stock_id', null, 'string');
        if (empty($stocks[$stock_id])) {
            $stock_id = null;
        } else if (isset($stocks[$stock_id]['substocks'])) {
            $virtualstock_id = $stocks[$stock_id]['id'];
            $stock_id = null;
        }
        return array($stock_id, $virtualstock_id);
    }

    protected static function processBuiltInRules(&$params) {
        $shipping_type_id = null;
        if (!empty($params['order']['params']['shipping_id'])) {
            $shipping_type_id = $params['order']['params']['shipping_id'];
        }
        $shipping_country = $shipping_region = null;
        if (!empty($params['order']['params']['shipping_address.country'])) {
            $shipping_country = (string) $params['order']['params']['shipping_address.country'];
            if (!empty($params['order']['params']['shipping_address.region'])) {
                $shipping_region = $shipping_country . ':' . $params['order']['params']['shipping_address.region'];
            }
        }

        foreach ($params['rules'] as &$rule) {
            if ($rule['rule_type'] == 'by_shipping') {
                $rule['fulfilled'] = $shipping_type_id && $shipping_type_id == $rule['rule_data'];
            } else if ($rule['rule_type'] == 'by_region') {
                $rule['fulfilled'] = false;
                foreach (explode(',', $rule['rule_data']) as $candidate) {
                    if ($candidate === $shipping_country || $candidate === $shipping_region) {
                        $rule['fulfilled'] = true;
                        break;
                    }
                }
            }
        }
        unset($rule);
    }

}
