<?php
/*
    EmailLabs_Sync is used to synchronize EmailLabs mailing lists.
    Copyright (C) 2010 Marko Sanković

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @see EmailLabs_Client
 */
require_once 'EmailLabs/Client.php';

/**
 * EmailLabs Sync class.
 *
 * @uses      EmailLabs_Client
 * @category  EmailLabs
 */
class EmailLabs_Sync implements SplSubject, EmailLabs_Loggable
{
    /**
     * Origin site config.
     *
     * @var array
     */
    protected $_origin;

    /**
     * Origin site client.
     *
     * @var EmailLabs_Client
     */
    protected $_originClient;

    /**
     * Target site config.
     *
     * @var array
     */
    protected $_target;

    /**
     * Target site client.
     *
     * @var EmailLabs_Client
     */
    protected $_targetClient;

    /**
     * Attachable observers.
     *
     * @var array
     */
    private $_observers = array();

    /**
     * Use message exception as means of message exchange with observers.
     *
     * @var Exception
     */
    private $_messageException;

    /**
     * Instantiate new EmailLabs Sync object.
     * Two array configs should be supplied: origin and target.
     *
     * @param array $origin
     * @param array $target
     */
    public function __construct(array $origin, array $target)
    {
        $this->_origin = $origin;
        $this->_target = $target;

        $this->_originClient = new EmailLabs_Client($origin['endpoint'], $origin['site_id'], $origin['password']);
        $this->_targetClient = new EmailLabs_Client($target['endpoint'], $target['site_id'], $target['password']);
    }

    /**
     * Check mailing lists requirements. If they are not met throw appropriate exception.
     */
    protected function _checkMailingListsRequirements()
    {
        $origin = $this->getOrigin();
        $target = $this->getTarget();
        // Mailing lists mlid mapping is required
        if(empty($origin['mailing_lists']['mlid']) or empty($target['mailing_lists']['mlid'])) {
            throw new Exception('Configurations should contain mailing lists mapping');
        }
        // There should be equal number of mailing lists mlid
        if(@count($origin['mailing_lists']['mlid']) != @count($target['mailing_lists']['mlid'])) {
            throw new Exception('Number of mailing lists map should match');
        }
    }

    /**
     * Set message exception.
     *
     * @param string $message
     * @param int $code
     * @return EmailLabs_Sync
     */
    protected function _setMessageException($message, $code)
    {
        $this->_messageException = new Exception($message, $code, null);
        return $this;
    }

    /**
     * Attach observer.
     *
     * @param SplObserver $observer
     */
    public function attach(SplObserver $observer)
    {
        $id = spl_object_hash($observer);
        $this->_observers[$id] = $observer;
        return $this;
    }

    /**
     * Detach observer.
     *
     * @param SplObserver $observer
     */
    public function detach(SplObserver $observer)
    {
        $id = spl_object_hash($observer);
        unset($this->_observers[$id]);
        return $this;
    }

    /**
     * Exeption is used as means of message exchange with observers.
     *
     * @return Exception
     */
    public function getLoggableMessage()
    {
        if($this->_messageException instanceof Exception) {
            return $this->_messageException;
        }
    }

    /**
     * Get target site configuration.
     *
     * @return array
     */
    public function getTarget()
    {
        return $this->_target;
    }

    /**
     * Get target client.
     *
     * @return EmailLabs_Client
     */
    public function getTargetClient()
    {
        return $this->_targetClient;
    }

    /**
     * Get origin site configuration.
     *
     * @return array
     */
    public function getOrigin()
    {
        return $this->_origin;
    }

    /**
     * Get origin client.
     *
     * @return EmailLabs_Client
     */
    public function getOriginClient()
    {
        return $this->_originClient;
    }

    /**
     * Notify observers by calling each's update method.
     */
    public function notify()
    {
        foreach ($this->_observers as $observer) {
            $observer->update($this);
        }

        return $this;
    }

    /**
     * Sync mailing lists (memeber records and demographic data) between
     * EmailLabs sites (organizations)
     * 
     * If you don't update demographic data,
     * syncing demographic will perform faster
     * since only the mapping will be procesuired.
     *
     * @param array $params see 4.5 Activity: Query-Listdata
     * @param boolean $updateExistingDemographic
     */
    public function syncRecords(array $params, $updateExistingDemographic = false)
    {
        // Get configs
        $origin = $this->getOrigin();
        $target = $this->getTarget();

        // Check mailing lists requirements
        $this->_checkMailingListsRequirements();

        // Instantiate EmailLabs clients
        $originClient = $this->getOriginClient();
        $targetClient = $this->getTargetClient();

        // Set all available query listdata parameters

        // Encoding
        if(isset($params['encoding'])) $originClient->addData($params['encoding'], 'extra', 'encoding');
        // Go to Page Number
        if(isset($params['page'])) $originClient->addData((int)$params['page'], 'extra', 'page');
        // # of Records to Show
        if(isset($params['pagelimit'])) $originClient->addData((int)$params['pagelimit'], 'extra', 'pagelimit');
        // Show by Date
        if(isset($params['date'])) {
            if(preg_match("/^[A-Z]+[a-z]+\s\d{1,2},\s\d{4,4}$/", $params['date'])) {
                $originClient->addData((int)$params['date'], 'extra', 'date');
            }
        }
        // Show records after start_datetime
        if(isset($params['start_datetime'])) {
            if(is_int($params['start_datetime'])) {
                $originClient->addData(date("F d, Y", time() - abs($params['start_datetime']) * 86400), 'extra', 'start_datetime');
            } else {
                if(preg_match("/^[A-Z]+[a-z]+\s\d{1,2},\s\d{4,4}$/", $params['start_datetime'])) {
                    $originClient->addData($params['start_datetime'], 'extra', 'start_datetime');
                }
            }
        }
        // Show records before end_datetime
        if(isset($params['end_datetime'])) {
            if(is_int($params['end_datetime'])) {
                $originClient->addData(date("F d, Y", time() - abs($params['end_datetime']) * 86400), 'extra', 'end_datetime');
            } else {
                if(preg_match("/^[A-Z]+[a-z]+\s\d{1,2},\s\d{4,4}$/", $params['end_datetime'])) {
                    $originClient->addData($params['end_datetime'], 'extra', 'end_datetime');
                }
            }
        }
        // Show by Type: active, trashed, unsubscribed, bounced, or trashedbyadmin
        if(isset($params['type'])) {
            $type = in_array($params['type'], array('active', 'trashed', 'unsubscribed', 'bounced', 'trashedbyadmin')) ? $params['type'] : 'active';
            $originClient->addData($type, 'extra', 'type');
        }

        // End of Set all available query listdata parameters

        // Sync mailing lists
        foreach ($origin['mailing_lists']['mlid'] as $key => $mlid) {

            // Set message list id
            $originClient->setMlid($mlid);
            $targetClient->setMlid($target['mailing_lists']['mlid'][$key]);

            // Change origin client's password if one is set for mailing list
            if(isset($origin['mailing_lists']['password'][$key])) $originClient->setPassword($origin['mailing_lists']['password'][$key]);
            else $originClient->setPassword($origin['password']);

            // Change target client's password if one is set for mailing list
            if(isset($target['mailing_lists']['password'][$key])) $targetClient->setPassword($target['mailing_lists']['password'][$key]);
            else $targetClient->setPassword($target['password']);

            // Sync demographic data. Mapping between origin and target will be returned.
            $demographicMapping = $this->syncDemographic($mlid, $target['mailing_lists']['mlid'][$key], $updateExistingDemographic);

            // Get mailing list records
            $originResult = $originClient->recordQueryListdata();
            
            if($originResult->isError()) {
                $this->_setMessageException("Could not fetch origin records for mlid $mlid: {$originResult->getData()}", Zend_Log::ERR)->notify();
                continue;
            }

            $records = $originResult->getData();

            // For each record update target list
            foreach ($records as $key => $record) {
                
                $data = array();
                $targetClient->clearData(); // Clear previous data so we don't forge bad request
                $email = "";

                foreach($record as $type => $value) {
                    // If value is array, like: extra, demographic etc
                    if(is_array($value)) {
                        foreach ($value as $id => $name) {
                            // If demographic get id from demographic mapping
                            if($type == "demographic") {
                                if(isset($demographicMapping[$id])) { // Mapping exists
                                    $data[] = array("type" => $type, "id" => $demographicMapping[$id], "value" => $name);
                                } else {
                                    $this->_setMessageException("Demographic mismatch $name($id)", Zend_Log::WARN)->notify();
                                }
                            } else {
                                $data[] = array("type" => $type, "id" => $id, "value" => $name);
                            }
                        }
                    } else {
                        if($type == "email") $email = $value;
                        $data[] = array("type" => $type, "value" => $value); // Notice no id
                    }
                }
                
                // Perform record add request
                if($data) {
                    // See if record with same email exists
                    // TODO: Make fewer http requests
                    $recordQueryDataResult = $targetClient->recordQueryData($email);
                    // Add or update
                    if($recordQueryDataResult->isSuccess()) { // Record exists, update
                        $recordUpdateResult = $targetClient->perform('record', 'update', $data);
                        if($recordUpdateResult->isError()) {
                            $this->_setMessageException("Record update failed for '$email': " . $recordUpdateResult->getData(), Zend_Log::ERR)->notify();
                        } else {
                            $this->_setMessageException("Record update succeeded for '$email': " . $recordUpdateResult->getData(), Zend_Log::INFO)->notify();
                        }
                    } else { // Add record
                        $recordAddResult = $targetClient->perform('record', 'add', $data);
                        if($recordAddResult->isError()) {
                            $this->_setMessageException("Record add failed for '$email': " . $recordAddResult->getData(), Zend_Log::ERR)->notify();
                        } else {
                            $this->_setMessageException("Record add succeeded for '$email': " . $recordAddResult->getData(), Zend_Log::INFO)->notify();
                        }
                    }
                }

            } // End loop records

        } // End loop mailing lists
    }

    /**
     * Sync demographic data between separate mailing lists
     *
     * Returns hashed map array where key is origin's demographic id
     * and value is target's demographic id.
     *
     * @param int $originMlid
     * @param int $targetMlid
     * @param boolean $updateExisting
     * @return array Hashed map
     */
    public function syncDemographic($originMlid, $targetMlid, $updateExisting = false)
    {
        // Origin client
        $originClient = $this->getOriginClient();
        $originClient->setMlid($originMlid);
        // Target client
        $targetClient = $this->getTargetClient();
        $targetClient->setMlid($targetMlid);

        // Query enabled demographic on origin site
        $originEnabledDetails = $originClient->perform('demographic', 'query-enabled-details', array(), 'EmailLabs_Result_Record');
        $originEnabledDetailsData = $originEnabledDetails->getData();
        // Query all (enabled/disabled) demographic on target site - options excluded
        $targetEnabledDetails = $targetClient->perform('demographic', 'query-all', array(), 'EmailLabs_Result_Record');
        $targetEnabledDetailsData = $targetEnabledDetails->getData();
        
        // Origin and target id mapping
        $mapping = array();

        foreach($originEnabledDetailsData as $originRecord) {
            if(is_array($targetEnabledDetailsData)) {
                foreach($targetEnabledDetailsData as $targetRecord) {
                    // If demographic match do update, skip rest of target records
                    if($originRecord['name'] == $targetRecord['name']) {
                        // Update
                        if($updateExisting) {
                            // Add data
                            $targetClient->clearData()->addData($originRecord['name'], $originRecord['type'])
                                                      ->addData($targetRecord['id'], 'id')
                                                      ->addData('enabled', 'state');
                            // Add options (Select List)
                            if(isset($originRecord['option'])) {
                                foreach($originRecord['option'] as $option) {
                                    $targetClient->addData($option, 'option');
                                }
                            }
                            // Perform demographic update request
                            $result = $targetClient->perform('demographic', 'update');

                            if($result->isError()) {
                                $this->_setMessageException("Demographic update failed for {$originRecord['name']}({$originRecord['id']}): " . $result->getData(), Zend_Log::ERR)->notify();
                            } else {
                                $this->_setMessageException("Demographic update succeeded for {$originRecord['name']}({$originRecord['id']}): " . $result->getData(), Zend_Log::INFO)->notify();
                            }
                        }
                        // Add mapping ids
                        $mapping[$originRecord['id']] = $targetRecord['id'];
                        continue 2; // continue on origin records
                    }
                }
            }
            
            // Add data
            $targetClient->clearData()->addData($originRecord['name'], $originRecord['type'])
                                      ->addData('enabled', 'state'); // Set initial state
            // Add options (Select List)
            if(isset($originRecord['option'])) {
                foreach($originRecord['option'] as $option) {
                    $targetClient->addData($option, 'option');
                }
            }
            // Perform demographic add request
            $result = $targetClient->perform('demographic', 'add');
            // If successfully added, map target's demographic id to origin's
            if($result->isSuccess()) {
                $targetDemographicId = $result->getData();
                $mapping[$originRecord['id']] = $targetDemographicId;
                $this->_setMessageException("Demographic add succeeded {$originRecord['name']}({$originRecord['id']}): " . $result->getData(), Zend_Log::INFO)->notify();
            } else {
                $this->_setMessageException("Demographic add failed {$originRecord['name']}({$originRecord['id']}): " . $result->getData(), Zend_Log::ERR)->notify();
            }
        }

        return $mapping;
    }
}
