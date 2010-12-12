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
    $emailLabsSync->attach(new EmailLabs_Logger('log/test.log'));

    try {
        //$emailLabsSync->syncRecords(array('type' => 'active', 'start_datetime' => 1));
    } catch(Exception $e) {
        echo $e->getMessage();
    }


    $client = new EmailLabs_Client($origin['endpoint'], $origin['site_id'], $origin['password']);
    $client->setMlid(68);
    $client->addData('trashed', 'extra', 'type');
    $client->addData('January 1, 2010', 'extra', 'start_datetime');
    $result = $client->perform('record', 'query-listdata', array(), 'EmailLabs_Result_Record');

    var_dump($result->getData());
    