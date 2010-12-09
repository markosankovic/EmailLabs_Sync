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

    $emailLabsSync = new EmailLabs_Sync($oldSiteConfig->toArray(), $newSiteConfig->toArray());

    try {
        $emailLabsSync->attach(new EmailLabs_Logger('sync.log'));
        $emailLabsSync->syncActive();
    } catch(Exception $e) {
        echo $e->getMessage();
    }

/*
    $emailLabsClient = new EmailLabs_Client('https://secure.elabs10.com/API/mailing_list.html', '2010000089', 'Wl31eRSrgI32P#');

    //$result = $emailLabsClient->setMlid(68)->userExists('dan2@dan.cogentads.com');

    //$result = $emailLabsClient->setMlid(68)->addData('sdflajfd', 'demographic', 2)->recordUpdate('dan2@dan.cogentads.com');

    $emailLabsClient->clearData();



    $result = $emailLabsClient->setMlid(68)
                              //->addData('dan2@dan.cogentads.com', 'email')
                              //->addData('March 17, 2010', 'extra', 'start_datetime')
                              ->recordQueryData('dan2@dan.cogentads1.com');

              var_dump($result->getData());

    var_dump($result->isSuccess());
    var_dump($result->isError());
 * 
 */