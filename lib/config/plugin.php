<?php

/**
 * @author  wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
return array(
    'name' => 'Быстрый заказ',
    'description' => 'Быстрое оформление заказа во всплывающем окне за 1 клик',
    'vendor' => '985310',
    'version' => '2.0.0',
    'img' => 'img/instantorder.png',
    'frontend' => true,
    'shop_settings' => true,
    'handlers' => array(
        'frontend_head' => 'frontendHead',
    ),
);
//EOF
