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
 * @see Zend_Http_Client
 */
require_once 'Zend/Http/Client.php';

/**
 * EmailLabs Client class
 *
 * @uses      Zend_Http_Client
 * @category  EmailLabs
 */
class EmailLabs_Client
{
    /**
     * Endpoint refers to API: https://secure.elabs10.com/API/mailing_list.html
     *
     * @var string
     */
    protected $_endpoint;

    /**
     * Site id refers to Account Home / Company Info / Customer ID
     *
     * @var string
     */
    protected $_siteId;

    /**
     * Password refers to either global password (Account Home / Account Settings / Global Settings / Global API Settings / Password)
     * or API password per mailing list (Mailing List Home / List Settings / API Security / Password required for API access)
     *
     * @var string
     */
    protected $_password;

    /**
     * Refers to message list id found in query parameters when accessing message list.
     *
     * @var string
     */
    protected $_mlid;

    /**
     * Request data parts. Should contain keys: value, type and optionally id.
     *
     * @var array
     */
    protected $_data = array();

    /**
     * HTTP Client performing the actual request.
     * Can use different adapters: socket, proxy, curl
     * In this case its socket, fsockopen() PHP's built-in function.
     *
     * @var Zend_Http_Client
     */
    protected $_httpClient;

    /**
     * Construct new EmailLabs client.
     *
     * @param string $endpoint
     * @param string $siteId
     * @param string $password
     */
    public function __construct($endpoint, $siteId, $password)
    {
        $this->setEndpoint($endpoint);
        $this->setSiteId($siteId);
        $this->setPassword($password);
    }

    /**
     * Perform http request.
     *
     * @param string $type
     * @param string $activity
     * @return string raw result (response body)
     */
    protected function _request($type, $activity)
    {
        $dataPart = $this->getDataPrepared();
        $mlid = $this->getMlid();
        if($mlid) $mlid = "<MLID>$mlid</MLID>";

        $xml = <<<XML
<DATASET>
    <SITE_ID>{$this->getSiteId()}</SITE_ID>
    <DATA type="extra" id="password">{$this->getPassword()}</DATA>
    $mlid
    $dataPart
</DATASET>
XML;
        //echo "$xml\n";

        $this->getHttpClient()->setUri($this->getEndpoint())
                              ->setParameterPost('type', $type)
                              ->setParameterPost('activity', $activity)
                              ->setParameterPost('input', $xml);
        $response = $this->getHttpClient()->request();

        //echo $response->getBody() . "\n";

        return $response->getBody();
    }

    /**
     * Add data part.
     *
     * @param string $value
     * @param string $type
     * @param string $id
     * @param urlencode boolean
     * @return EmailLabs_Client
     */
    public function addData($value, $type, $id = null, $urlencode = false)
    {
        $part = array('value' => $urlencode ? urlencode($value) : $value, 'type' => $type);
        if(null !== $id) $part['id'] = $id;
        $this->_data[] = $part;
        return $this;
    }

    /**
     * Clear data.
     *
     * @return EmailLabs_Client
     */
    public function clearData()
    {
        $this->_data = array();
        return $this;
    }

    public function demographicAdd()
    {
        return new EmailLabs_Result_Data($this->_request('demographic', 'add'));
    }

    /**
     * Get data.
     *
     * @return array
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * Get data part prepared for request.
     *
     * @param boolean $urlencode
     * @return string
     */
    public function getDataPrepared()
    {
        $part = "";
        $data = $this->getData();
        if($data && is_array($data)) {
            foreach ($data as $one) {
                $part .= "<DATA type=\"{$one['type']}\"" . (isset($one['id']) ? " id=\"{$one['id']}\">" : ">") . $one['value'] . "</DATA>";
            }
        }
        return $part;
    }

    /**
     * Get endpoint.
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->_endpoint;
    }

    /**
     * Get mlid.
     *
     * @return string
     */
    public function getMlid()
    {
        return $this->_mlid;
    }

    /**
     * Retrieve or instantiate http client.
     * We are assuming secure http protocol and text/xml as content type.
     *
     * @return Zend_Http_Client
     */
    public function getHttpClient()
    {
        if(!$this->_httpClient) {
            $this->_httpClient = new Zend_Http_Client(null, array(
                'adapter'      => 'Zend_Http_Client_Adapter_Socket',
                'ssltransport' => 'tls'
            ));
            $this->_httpClient->setMethod(Zend_Http_Client::POST);
            $this->_httpClient->setHeaders('Content-type', 'text/xml');
        }

        return $this->_httpClient;
    }

    /**
     * Get password.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * Get site id.
     *
     * @return string
     */
    public function getSiteId()
    {
        return $this->_siteId;
    }

    /**
     * Perform request.
     *
     * This method could be used for performing almost all
     * types and activities described in API documentation.
     *
     * @param string $type
     * @param string $activity
     * @param array $data
     * @param EmailLabs_Result_Data $result
     * @return EmailLabs_Result_Data
     */
    public function perform($type, $activity, array $data = array(), $result = 'EmailLabs_Result_Data')
    {
        $this->setData($data);
        return new $result($this->_request($type, $activity));
    }

    /**
     * Data Handling
     * 4.5 Activity: Query-Data
     *
     * @param string $email
     * @param string $mlid
     * @return EmailLabs_Result_Data
     */
    public function recordQueryData($email, $mlid = null)
    {
        if($mlid) $this->setMlid($mlid);
        if(empty($this->_mlid)) throw new Exception('Message list id is required');
        if(empty($email)) throw new Exception('Email is required');
        $this->addData($email, 'email');
        return new EmailLabs_Result_Record($this->_request('record', 'query-data'));
    }

    /**
     * Data Handling
     * 4.5 Activity: Query-Listdata
     *
     * @return EmailLabs_Result_Record
     */
    public function recordQueryListdata()
    {
        return new EmailLabs_Result_Record($this->_request('record', 'query-listdata'));
    }

    /**
     * Data Handling
     * 4.6 Activity: Update
     *
     * @param string $email
     * @param string $mlid
     * @return EmailLabs_Result_Data
     */
    public function recordUpdate($email, $mlid = null)
    {
        if($mlid) $this->setMlid($mlid);
        if(empty($this->_mlid)) throw new Exception('Message list id is required');
        if(empty($email)) throw new Exception('Email is required');
        $this->addData($email, 'email');
        return new EmailLabs_Result_Data($this->_request('record', 'update'));
    }

    /**
     * Set data.
     *
     * @param array $data
     * @return EmailLabs_Client
     */
    public function setData(array $data)
    {
        foreach($data as $one) {
            $this->addData($one['value'], $one['type'], @$one['id']);
        }

        return $this;
    }

    /**
     * Set endpoint.
     *
     * @param string $endpoint
     * @return EmailLabs_Client
     */
    public function setEndpoint($endpoint)
    {
        $this->_endpoint = $endpoint;
        return $this;
    }

    /**
     * Get mlid.
     *
     * @param string $mlid
     * @return EmailLabs_Client
     */
    public function setMlid($mlid)
    {
        $this->_mlid = $mlid;
        return $this;
    }

    /**
     * Get password.
     *
     * @param string $password
     * @return EmailLabs_Client
     */
    public function setPassword($password)
    {
        $this->_password = $password;
        return $this;
    }

    /**
     * Set site id.
     *
     * @param string $siteId
     * @return EmailLabs_Client
     */
    public function setSiteId($siteId)
    {
        $this->_siteId = $siteId;
        return $this;
    }
}
