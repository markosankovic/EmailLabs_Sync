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

    // Set origin and target
    $origin = $newSiteConfig->toArray();
    $target = $oldSiteConfig->toArray();

    // Instantiate Sync and accomodating Logger
    $emailLabsSync = new EmailLabs_Sync($origin, $target);
    $logger = new EmailLabs_Logger('log/process_2.log');
    
    // Attach logger
    $emailLabsSync->attach($logger);

    // Try to sync
    try {
        $emailLabsSync->syncRecords(array('type' => 'trashed', 'start_datetime' => 1));
    } catch(Exception $e) {
        echo $e->getMessage();
    }
