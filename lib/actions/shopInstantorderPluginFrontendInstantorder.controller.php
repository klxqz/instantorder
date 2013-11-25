<?php

/**
 * @author Коробов Николай wa-plugins.ru <support@wa-plugins.ru>
 * @link http://wa-plugins.ru/
 */
class shopInstantorderPluginFrontendInstantorderController extends waJsonController {

    public function execute() {
        $plugin = wa()->getPlugin('instantorder');
        
        $this->response['message'] = 'Заказ успешно отправлен';
    }

}
