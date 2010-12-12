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
 * Holds EmailLabs result in raw text format and as SimpleXMLElement.
 * Additional data parsing should be implemented in extended classes.
 *
 * @category   EmailLabs
 * @package    EmailLabs_Result
 */
class EmailLabs_Result_Data
{
    /**
     * Parsed result.
     *
     * @var array
     */
    protected $_data;

    /**
     * EmailLabs result in raw text.
     *
     * @var string
     */
    protected $_result;

    /**
     * Xml represenatation of result.
     *
     * @var SimpleXMLElement
     */
    protected $_xml;

    /**
     * Construct new EmailLabs object by providing raw text result.
     *
     * @param string $result
     */
    public function __construct($result)
    {
        if(is_string($result)) {
            $this->_xml = new SimpleXMLElement($result);
            $this->_result = $result;
        }
    }

    /**
     * Get parsed data.
     *
     * @return array
     */
    public function getData()
    {
        if(!isset($this->_data)) {
            $this->parse();
        }
        return $this->_data;
    }

    /**
     * Get raw result text.
     *
     * @return string
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * Get xml representation of result.
     *
     * @return SimpleXMLElement
     */
    public function getXml()
    {
        return $this->_xml;
    }

    /**
     * Get result type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->getXml()->TYPE->__toString();
    }

    /**
     * See if error occured.
     *
     * @return boolean
     */
    public function isError()
    {
        return $this->getType() == 'error';
    }

    /**
     * See if result is success.
     *
     * @return boolean
     */
    public function isSuccess()
    {
        return $this->getType() == 'success';
    }

    /**
     * Parse general data response.
     */
    protected function parse()
    {
        // TODO: If DATA has attributes return assoc array
        $this->_data = isset($this->getXml()->DATA) ? $this->getXml()->DATA->__toString() : "";
        return $this;;
    }
}
