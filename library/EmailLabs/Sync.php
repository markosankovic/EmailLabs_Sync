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
     * Sync from old site to new
     */
    const DIRECTION_OLD_NEW = 1;

    /**
     * Sync from new site to old
     */
    const DIRECTION_NEW_OLD = 2;

    /**
     * Old site configuration.
     *
     * @var array
     */
    protected $_oldSiteConfig;

    /**
     * New site configuration.
     *
     * @var array
     */
    protected $_newSiteConfig;

    /**
     * Attachable observers.
     *
     * @var array
     */
    private $_observers = array();

    /**
     * Use exception as means of holding current object's state.
     *
     * @var Exception
     */
    private $_exception;

    /**
     * Instantiate new EmailLabs Sync object.
     * Two array configs should be supplied: old(origin) and new(target).
     * 
     * @param array $oldSiteConfig
     * @param array $newSiteConfig
     */
    public function __construct(array $oldSiteConfig, array $newSiteConfig)
    {
        $this->_oldSiteConfig = $oldSiteConfig;
        $this->_newSiteConfig = $newSiteConfig;
    }

    /**
     * Check mailing lists requirements. If they are not met throw appropriate exception.
     */
    protected function _checkMailingListsRequirements()
    {
        $old = $this->getOldSiteConfig();
        $new = $this->getNewSiteConfig();
        // Mailing lists mlid mapping is required
        if(empty($old['mailing_lists']['mlid']) or empty($old['mailing_lists']['mlid'])) {
            throw new Exception('Configurations should contain mailing lists mapping');
        }
        // There should be equal number of mailing lists mlid
        if(@count($old['mailing_lists']['mlid']) != @count($new['mailing_lists']['mlid'])) {
            throw new Exception('Number of mailing lists map should match');
        }
    }

    /**
     * Get direction list in order: $origin, $target
     *
     * @param int $direction
     * @return array
     */
    protected function _getDirectionList($direction)
    {
        if($direction == self::DIRECTION_OLD_NEW) {
            $origin = $this->getOldSiteConfig();
            $target = $this->getNewSiteConfig();
        } else {
            $origin = $this->getNewSiteConfig();
            $target = $this->getOldSiteConfig();
        }
        
        return array($origin, $target);
    }

    /**
     * Set exception.
     *
     * @param string $message
     * @param int $code
     * @return EmailLabs_Sync
     */
    protected function _setException($message, $code)
    {
        $this->_exception = new Exception($message, $code, null);
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
    }

    /**
     * Exeption is used as means of message exchange with observers.
     *
     * @return Exception
     */
    public function getLoggableMessage()
    {
        if($this->_exception instanceof Exception) {
            return $this->_exception;
        }
    }

    /**
     * Get new site configuration.
     *
     * @return array
     */
    public function getNewSiteConfig()
    {
        return $this->_newSiteConfig;
    }

    /**
     * Get old site configuration.
     *
     * @return array
     */
    public function getOldSiteConfig()
    {
        return $this->_oldSiteConfig;
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
     * Retrieve the newly added (since start of yesterday) records with a status of "active" from the oldsite.
     * Add retrieved records to the equivalent list on newsite.
     * If record already exists on the list on newsite, the record is not added again rather the record on newsite is updated.
     *
     * @param int $joinedDays Number of previous days
     * @param int $direction
     */
    public function syncActive($joinedDays = 200, $direction = self::DIRECTION_OLD_NEW)
    {
        $this->_setException("Some message", Zend_Log::CRIT)->notify();

        // Get origin and target upon direction
        list($origin, $target) = $this->_getDirectionList($direction);

        // Check mailing lists requirements
        $this->_checkMailingListsRequirements();

        // Instantiate EmailLabs clients
        $emailLabsClientOrigin = new EmailLabs_Client($origin['endpoint'], $origin['site_id'], $origin['password']);
        $emailLabsClientTarget = new EmailLabs_Client($target['endpoint'], $target['site_id'], $target['password']);

        // Sync mailing lists
        foreach ($origin['mailing_lists']['mlid'] as $key => $mlid) {

            // Set message list id
            $emailLabsClientOrigin->setMlid($mlid);

            // Change client's password if one is set for mailing list
            if(isset($origin['mailing_lists']['password'][$key])) {
                $emailLabsClientOrigin->setPassword($origin['mailing_lists']['password'][$key]);
            } else {
                $emailLabsClientOrigin->setPassword($origin['password']);
            }

            // Get only active
            $emailLabsClientOrigin->addData('active', 'extra', 'type');

            // Get joined in past ($joinedDays) days. If 0 all records will be fetched.
            if($joinedDays) {
                $emailLabsClientOrigin->addData(date("F d, Y", time() - (int)$joinedDays * 86400), 'extra', 'start_datetime');
            }

            // Get mailing list records
            $originListResult = $emailLabsClientOrigin->recordQueryListdata();

            if($originListResult->isError()) {
                // Log Error
                continue;
            }
            
            
            $records = $originListResult->getData();

            // For each record update target list
            foreach ($records as $key => $record) {
                //var_dump($record);
            }

            // Log success, failure
        }
    }
    
}