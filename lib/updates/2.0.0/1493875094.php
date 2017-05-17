<?php

try {
    $files = array(
        // css
        'plugins/instantorder/css/themes/',
        'plugins/instantorder/css/edit_button.css',
        'plugins/instantorder/css/gradient.css',
        'plugins/instantorder/css/style.css',
        // js
        'plugins/instantorder/js/fcts.min.js',
        'plugins/instantorder/js/script.js',
        // lib/actions
        'plugins/instantorder/lib/actions/shopInstantorderPluginBackendSave.controller.php',
        'plugins/instantorder/lib/actions/shopInstantorderPluginFrontendInstantorder.controller.php',
        'plugins/instantorder/lib/actions/shopInstantorderPluginFrontendRegions.controller.php',
        'plugins/instantorder/lib/actions/shopInstantorderPluginSettings.action.php',
        // lib/config
        'plugins/instantorder/lib/db.php',
        'plugins/instantorder/lib/uninstall.php',
        // lib/models
        'plugins/instantorder/lib/models/',
        // templates
        'plugins/instantorder/templates/Instantorder.html',
        'plugins/instantorder/templates/actions/settings/EditButton.html',
    );

    foreach ($files as $file) {
        waFiles::delete(wa()->getAppPath($file, 'shop'), true);
    }
} catch (Exception $e) {
    
}

$model = new waModel();
try {
    $model->exec("DROP TABLE `shop_instantorder`");
} catch (waDbException $e) {
    
}