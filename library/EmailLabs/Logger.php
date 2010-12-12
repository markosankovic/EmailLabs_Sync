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
 * @see Zend_Log
 */
require_once 'Zend/Log.php';

/**
 * @see Zend_Log_Writer_Abstract
 */
require_once 'Zend/Log/Writer/Abstract.php';

/**
 * @see Zend_Log_Writer_Stream
 */
require_once 'Zend/Log/Writer/Stream.php';

/**
 * EmailLabs Logger implemented as observer.
 *
 * @uses Zend_Log
 * @uses Zend_log_Writer_Abstract
 * @uses Zend_Log_Writer_Stream
 * @category EmailLabs
 */
class EmailLabs_Logger implements SplObserver
{
    /**
     * Logger.
     *
     * @var Zend_Log
     */
    private $_logger;

    /**
     * Instantiate new logger.
     *
     * @param string $filename
     */
    public function  __construct($filename)
    {
        $writer = new Zend_Log_Writer_Stream($filename);
        $this->_logger = new Zend_Log($writer);
    }

    /**
     * Add additional writers.
     *
     * @param Zend_Log_Writer_Abstract $writer
     * @return EmailLabs_Logger
     */
    public function addWriter($writer)
    {
        if($writer instanceof Zend_Log_Writer_Abstract) {
            $this->_logger->addWriter($writer);
        }

        return $this;
    }

    /**
     * Get logger.
     *
     * @return Zend_Log
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * EmailLabs Logger observer has been called within the subject.
     *
     * @param SplSubject $subject
     */
    public function update(SplSubject $subject)
    {
        if($subject instanceof EmailLabs_Loggable) {
            $loggableMessage = $subject->getLoggableMessage();
        }

        if(isset($loggableMessage)) {
            if(is_string($loggableMessage)) {
                $message = $loggableMessage; $priority = Zend_Log::INFO;
            } else if(is_array($loggableMessage)) {
                list($message, $priority) = $loggableMessage;
            } else if($loggableMessage instanceof Exception) {
                $message = $loggableMessage->getMessage(); $priority = $loggableMessage->getCode();
            }

            $this->_logger->log($message, $priority);
        }
    }
}
