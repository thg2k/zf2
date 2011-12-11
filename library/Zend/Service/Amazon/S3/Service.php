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

/**
 * Amazon S3 PHP connection class
 *
 * @see        http://docs.amazonwebservices.com/AmazonS3/latest/API/
 */
class Service extends AbstractS3
{
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
     * Returns a list of all buckets owned by the authenticated client.
     *
     * @return array List of bucket names
     */
    public function listBuckets()
    {
        $xml = $this->_getXml($this->_makeRequest('GET'),
                             'ListAllMyBucketsResult');

        $buckets = array();
        foreach ($xml->Buckets->Bucket as $bucket) {
            $buckets[] = (string) $bucket->Name;
        }

        return $buckets;
    }

    /**
     * Creates a new bucket
     *
     * By creating a bucket, you become the bucket owner.
     *
     * @throws BucketAlreadyExists, TooManyBuckets
     *
     * @see validateBucketName
     * @param  string $bucket
     * @return boolean
     */
    public function createBucket($bucket, $location = null, $acl = self::ACL_PRIVATE)
    {
        if ($location != "") {
            $data = new SimpleXMLElement("<CreateBucketConfiguration />");
            $data->addChild("LocationContraint", $location);
        }
        else {
            $data = null;
        }

        $this->_makeRequest('PUT', $bucket, '/', null, $data);

        return $this->getBucket($bucket);
    }

    /**
     * Returns a new Bucket object for the given bucket name
     *
     * @param string $bucket ...
     * @return boolean ...
     */
    public function getBucket($bucket) {
        return new Bucket($this->_getAccessKey(), $this->_getSecretKey(), $bucket);
    }

    /**
     * Deletes an existing bucket.
     *
     * All the objects in the bucket must be deleted before the bucket itself
     * can be deleted.
     *
     * @throws NoSuchBucket, BucketNotEmpty
     * @param string|Bucket $bucket ...
     */
    public function removeBucket($bucket)
    {
        if ($bucket instanceof Bucket) {
            $bucket = $bucket->getName();
        }

        $this->_makeRequest('DELETE', $bucket);
    }

    /**
     * Checks if a given bucket name is available
     *
     * @param  string $bucket
     * @return boolean
     */
    public function isBucketAvailable($bucket)
    {
        try {
            $this->_makeRequest('HEAD', $bucket, '/');
        }
        catch (Zend_Service_Amazon_S3_Exception $e) {
            if ($e->getCode() == 404)
                return true;
        }

        return false;
    }
}
