<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
return array(
    'shop_instantorder' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'name' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'type' => array('varchar', 255, 'null' => 0, 'default' => ''),
        ':keys' => array(
            'PRIMARY' => array('id'),
        ),
    ),
);
