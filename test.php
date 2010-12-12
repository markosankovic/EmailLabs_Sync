<?php
    // Ensure library/ is on include_path
    set_include_path(implode(PATH_SEPARATOR, array(realpath('./library'), get_include_path())));

    // Register Autoloader
    require_once 'Zend/Loader/Autoloader.php';
    $autoloader = Zend_Loader_Autoloader::getInstance();
    $autoloader->registerNamespace('EmailLabs_');

    // Get configurations
    $oldSiteConfig = new Zend_Config_Ini('config.ini', 'old_site', null);
    $newSiteConfig = new Zend_Config_Ini('config.ini', 'new_site', null);

    $origin = $oldSiteConfig->toArray();
    $target = $newSiteConfig->toArray();

    $emailLabsSync = new EmailLabs_Sync($origin, $target);
    
    try {
        $emailLabsSync->attach(new EmailLabs_Logger('log/sync.log'));
        $emailLabsSync->syncRecords(array('type' => 'active', 'start_datetime' => 300));
    } catch(Exception $e) {
        echo $e->getMessage();
    }
    
    