<?php

try {
    $files = array(
        'plugins/instantorder/locale/',
    );

    foreach ($files as $file) {
        waFiles::delete(wa()->getAppPath($file, 'shop'), true);
    }
} catch (Exception $e) {
    
}