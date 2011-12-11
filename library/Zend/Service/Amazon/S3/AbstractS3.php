<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon_S3
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Service\Amazon\S3;

class Zend_Service_Amazon_S3_Exception extends Exception {
    protected $_s3_code;

    public static function createFromXml($http_status, \SimpleXMLElement $xml)
    {
        if ($xml->getName() != 'Error')
            return null;

        $code = (string) $xml->Code;
        $message = (string) $xml->Message;

        if ($code == 'BadDigest') {
            return new Zend_Service_Amazon_S3_ExceptionBadDigest($message, $http_status,
                (string) $xml->ExpectedDigest, (string) $xml->CalculatedDigest);
        }
        else if ($code == 'SignatureDoesNotMatch') {
            return new Zend_Service_Amazon_S3_ExceptionSignatureDoesNotMatch($message, $http_status,
                (string) $xml->StringToSign);
        }
        else
            return new Zend_Service_Amazon_S3_Exception($message, $http_status, $code);
    }

    public function __construct($message, $code, $s3_code = "") {
        parent::__construct($message, $code);

        $this->_s3_code = $s3_code;
    }
}

class Zend_Service_Amazon_S3_ExceptionBadDigest extends Zend_Service_Amazon_S3_Exception {
    protected $_expectedDigest;
    protected $_calculatedDigest;

    public function __construct($message, $code, $expectedDigest, $calculatedDigest)
    {
        parent::__construct($message, $code, 'BadDigest');
        $this->_expectedDigest = $expectedDigest;
        $this->_calculatedDigest = $calculatedDigest;
    }
}

class Zend_Service_Amazon_S3_ExceptionSignatureDoesNotMatch extends Zend_Service_Amazon_S3_Exception {
    protected $_stringToSign;

    public function __construct($message, $code, $stringToSign)
    {
        parent::__construct($message, $code, 'SignatureDoesNotMatch');

        $this->_stringToSign = $stringToSign;
    }
}

// BucketAlreadyExists   args: BucketName


/**
 * @see Zend_Service_Amazon_Abstract
 */
require_once 'Zend/Service/Amazon/Abstract.php';

/**
 * Amazon S3 PHP connection class
 *
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon_S3
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @see        http://docs.amazonwebservices.com/AmazonS3/latest/API/
 */
class AbstractS3 extends AbstractAmazon
{
    /**
     * Store for stream wrapper clients
     *
     * @var array
     */
    protected static $_wrapperClients = array();

    const ENDPOINT = 's3.amazonaws.com';

    const XML_NAMESPACE = 'http://s3.amazonaws.com/doc/2006-03-01/';

    /**
     * ...
     */
    const PAYER_REQUESTER = "Requester";

    /**
     * ...
     */
    const PAYER_BUCKET_OWNER = "BucketOwner";



    const MAX_AUTO_RETRY = 3;

    const PERMISSION_FULL_CONTROL = 'FULL_CONTROL';
    const PERMISSION_WRITE = 'WRITE';
    const PERMISSION_WRITE_ACP = 'WRITE_ACP';
    const PERMISSION_READ = 'READ';
    const PERMISSION_READ_ACP = 'READ_ACP';

    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_WRITE = 'public-read-write';
    const ACL_AUTH_READ = 'authenticated-read';
    const ACL_BUCKET_OWNER_READ = 'bucket-owner-read';
    const ACL_BUCKET_OWNER_FULL = 'bucket-owner-full-control';

    // for notifications

    /**
     * Event: Reduced Redundacy Storage Lost Object.
     *
     * This event is triggered when Amazon S3 detects that it has lost all
     * replicas of a Reduced Redundancy Storage object and can no longer
     * service requests for that object.
     */
    const EVENT_RRS_LOST_OBJECT = 's3:ReducedRedundancyLostObject';

    protected $_use_bucket_hostname = true;

    /**
     * Constructor
     *
     * @param string $accessKey
     * @param string $secretKey
     */
    public function __construct($accessKey = null, $secretKey = null)
    {
        parent::__construct($accessKey, $secretKey);
    }

    /**
     * Verify if the bucket name is valid
     *
     * @param string $bucket
     */
    protected function _validateBucketName($bucket)
    {
        //preg_match('/^[0-9a-z][a-z0-9\.-]{2,62}[a-z0-9$/'
        //preg_match('/\.\.|\.-|-\./', fail

        $len = strlen($bucket);
        if ($len < 3 || $len > 255) {
            throw new Exception\InvalidArgumentException("Bucket name \"$bucket\" must be between 3 and 255 characters long");
        }

        if (preg_match('/[^a-z0-9\._-]/', $bucket)) {
            throw new Exception\InvalidArgumentException("Bucket name \"$bucket\" contains invalid characters");
        }

        if (preg_match('/(\d){1,3}\.(\d){1,3}\.(\d){1,3}\.(\d){1,3}/', $bucket)) {
            throw new Exception\InvalidArgumentException("Bucket name \"$bucket\" cannot be an IP address");
        }
    }


    /**
     * Make sure the object name is valid
     *
     * @param  string $object
     * @return string
     */
    protected function _fixupObjectName(&$object)
    {
        if ($object == "") {
            throw new Exception("Missing object name");
        }

        //if (substr($object, 0, 1) == '/') {
        //    throw new Exception("Object names cannot start with '/'");
        //}

        return;

        $nameparts = explode('/', $object);

        $this->_validBucketName($nameparts[0]);

        return $firstpart . '/' . join('/', array_map('rawurlencode', $nameparts));
    }


    /**
     * Make a request to Amazon S3
     *
     * @param  string $method        Request method (i.e. GET, POST, PUT, ...)
     * @param  string $bucket        Bucket selected
     * @param  string $path          Path to requested object, must start with slash
     * @param  array  $params        Additional query string parameters (?example)
     * @param  array  $headers       Additional and custom HTTP headers
     * @param  string|resource $data Request data
     * @return Zend_Http_Response
     */
    public function _makeRequest($method, $bucket = "", $path = '/', array $params = null, array $headers = null, $data = null)
    {
        $headers['Date'] = gmdate(DATE_RFC1123);

        if (is_resource($data) && $method != 'PUT') {
            /**
             * @see Zend_Service_Amazon_S3_Exception
             */
            require_once 'Zend/Service/Amazon/S3/Exception.php';
            throw new Zend_Service_Amazon_S3_Exception("Only PUT request supports stream data");
        }

        // build the end point
        $endpoint = Zend_Uri::factory('http');

        if ($this->_use_bucket_hostname && ($bucket != "")) {
            // prepend bucket name to the hostname
            $endpoint->setHost($bucket . '.' . self::ENDPOINT);
            $endpoint->setPath($path);
        }
        else {
            $endpoint->setHost(self::ENDPOINT);
            $endpoint->setPath(($bucket != "" ? '/' . $bucket : "") . $path);
        }

        /* add the query params to the endpoint */
        $endpoint->setQuery($params);

        $client = self::getHttpClient();
        $client->setConfig(array('keepalive' => true));

        $client->resetParameters();
        $client->setUri($endpoint);
        $client->setAuth(false);
        // Work around buglet in HTTP client - it doesn't clean headers
        // Remove when ZHC is fixed
        $client->setHeaders(array('Content-Md5'              => null,
                                  'Content-Encoding'         => null,
                                  'Expect'                   => null,
                                  'Range'                    => null,
                                  'x-amz-acl'                => null,
                                  'x-amz-copy-source'        => null,
                                  'x-amz-metadata-directive' => null));

        if (($method == 'PUT') && ($data !== null)) {
            if ($data instanceof \SimpleXMLElement) {
                $headers['Content-Type'] = 'application/xml';
                $client->setRawData($data->asXML(), $headers['Content-Type']);
            }
            else {
                // if (!isset($headers['Content-type'])) {
                    // $headers['Content-type'] = self::getMimeType($path);
                // }

                $client->setRawData($data, (isset($headers['Content-Type']) ? $headers['Content-Type'] : null));
            }
        }

        $this->addSignature($method, $bucket, $path, $params, $headers);

        $client->setHeaders($headers);

        $retry_count = 0;

        do {
            $retry = false;

            $response = $client->request($method);
            $response_code = $response->getStatus();

//var_dump($response);

            // Some 5xx errors are expected, so retry automatically
            if ($response_code >= 500 && $response_code < 600 && $retry_count < self::MAX_AUTO_RETRY) {
                $retry = true;
                $retry_count++;
                sleep($retry_count / 4 * $retry_count);
            }
            else if ($response_code == 307) {
                // Need to redirect, new S3 endpoint given
                // This should never happen as Zend_Http_Client will redirect automatically
            }
            else if ($response_code == 100) {
                // echo 'OK to Continue';
            }
            else if (($response_code >= 400) && ($response_code < 500)) {
                if ($response->getBody() != "") {
                    $xml = $this->_getXml($response, 'Error');
                    throw Zend_Service_Amazon_S3_Exception::createFromXml($response->getStatus(), $xml);
                }
                else
                    throw new Zend_Service_Amazon_S3_Exception("Request returned HTTP error", $response->getStatus());
            }
        } while ($retry);

        return $response;
    }

    /**
     * Add the S3 Authorization signature to the request headers
     *
     * @see http://s3.amazonaws.com/doc/s3-developer-guide/RESTAuthentication.html
     * @param  string $method
     * @param  string $path
     * @param  array &$headers
     * @return string
     */
    protected function addSignature($method, $bucket, $path, $query, array &$headers)
    {
        $sig_str = $method . "\n" .
                   (isset($headers['Content-MD5']) ? $headers['Content-MD5'] : "") . "\n" .
                   (isset($headers['Content-Type']) ? $headers['Content-Type'] : "") . "\n" .
                   (isset($headers['Date']) ? $headers['Date'] : "") . "\n";

        // For x-amz- headers, combine like keys, lowercase them, sort them
        // alphabetically and remove excess spaces around values
        $amz_headers = array();
        foreach ($headers as $key => $val) {
            $key = strtolower($key);
            if (substr($key, 0, 6) == 'x-amz-') {
                if (is_array($val)) {
                    $amz_headers[$key] = $val;
                }
                else {
                    $amz_headers[$key][] = preg_replace('/\s+/', ' ', $val);
                }
            }
        }

        ksort($amz_headers);
        foreach ($amz_headers as $key => $val) {
            $sig_str .= $key . ':' . implode(',', $val) . "\n";
        }

        /* add CanonicalizedResource (first part) */
        $sig_str .= ($bucket != "" ? '/' . $bucket : "") . $path;

        /* add special queries */
        $special_queries = array(
            // sub-resources for the bucket
            "acl", "policy", "location", "logging", "notification", "requestPayment", "versioning"
        );

        foreach ($special_queries as $sq) {
            if (isset($query[$sq])) {
                $sig_str .= "?" . $sq;
            }
        }

        //print "SIG_STR=\"$sig_str\"\n";

        /**
         * @see Zend_Crypt_Hmac
         */
        require_once 'Zend/Crypt/Hmac.php';

        $signature = base64_encode(Crypt\Hmac::compute($this->_getSecretKey(), 'sha1', utf8_encode($sig_str), Crypt\Hmac::BINARY));
        $headers['Authorization'] = 'AWS ' . $this->_getAccessKey() . ':' . $signature;

        return $sig_str;
    }

    protected function _newXml($container) {
      return new SimpleXMLElement('<' . $container . ' xmlns="' . self::XML_NAMESPACE . '" />');
    }

    protected function _getXml(Zend_Http_Response $response, $rootElement = null) {
        $xml = new SimpleXMLElement($response->getBody());

        /* format the output xml */
        // $dom = new \DOMDocument();
        // $dom->loadXML($xml->asXML());
        // $dom->formatOutput = true;
        // print $dom->saveXML();
 //print $response->getBody() . "\n";

        if (($rootElement != "") && ($xml->getName() != $rootElement)) {
            throw new Exception("Response XML has root <" . $xml->getName() .
                                ">, expected <" . $rootElement . ">");
        }

        return $xml;
    }

    /**
     * Register this object as stream wrapper client
     *
     * @param  string $name
     * @return Zend_Service_Amazon_S3
     */
    public function registerAsClient($name)
    {
        self::$_wrapperClients[$name] = $this;
        return $this;
    }

    /**
     * Unregister this object as stream wrapper client
     *
     * @param  string $name
     * @return Zend_Service_Amazon_S3
     */
    public function unregisterAsClient($name)
    {
        unset(self::$_wrapperClients[$name]);
        return $this;
    }

    /**
     * Get wrapper client for stream type
     *
     * @param  string $name
     * @return Zend_Service_Amazon_S3
     */
    public static function getWrapperClient($name)
    {
        return self::$_wrapperClients[$name];
    }

    /**
     * Register this object as stream wrapper
     *
     * @param  string $name
     */
    public function registerStreamWrapper($name = 's3')
    {
        stream_register_wrapper($name, 'Zend\Service\Amazon\S3\Stream');
        $this->registerAsClient($name);
    }

    /**
     * Unregister this object as stream wrapper
     *
     * @param  string $name
     */
    public function unregisterStreamWrapper($name = 's3')
    {
        stream_wrapper_unregister($name);
        $this->unregisterAsClient($name);
    }
}
