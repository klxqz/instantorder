<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
$plugin_id = array('shop', 'instantorder');
$app_settings_model = new waAppSettingsModel();
$app_settings_model->set($plugin_id, 'status', '1');
$app_settings_model->set($plugin_id, 'frontend_product', '1');
$app_settings_model->set($plugin_id, 'title', 'Быстрый заказ');
$app_settings_model->set($plugin_id, 'link_name', 'Быстрый заказ');
$app_settings_model->set($plugin_id, 'width_modal', '640');
$app_settings_model->set($plugin_id, 'height_modal', '500');
