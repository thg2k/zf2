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
 * @category   Zend
 * @package    Zend_Service
 * @subpackage Amazon_S3
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @see        http://docs.amazonwebservices.com/AmazonS3/latest/API/
 */
class Bucket extends AbstractS3
{
    /**
     * ...
     *
     * @var string
     */
    protected $_bucket;


    const SCHEMA_URI = "http://www.w3.org/2001/XMLSchema-instance";

    const ALL_USERS = "http://acs.amazonaws.com/groups/global/AllUsers";
    const AUTH_USERS = "http://acs.amazonaws.com/groups/global/AuthenticatedUsers";
    const LOG_DELIVERY = "http://acs.amazonaws.com/groups/global/LogDelivery";


    /**
     * Constructor
     *
     * @param string $accessKey
     * @param string $secretKey
     * @param string $bucket
     */
    public function __construct($accessKey = null, $secretKey = null, $bucket = null)
    {
        parent::__construct($accessKey, $secretKey);

        $this->_validateBucketName($bucket);

        $this->_bucket = $bucket;
    }

    /**
     * ...
     *
     * @return string ...
     */
    public function getName()
    {
        return $this->_bucket;
    }

    public function getService()
    {
        require_once 'Zend/Service/Amazon/S3/Service.php';
        return new Zend_Service_Amazon_S3_Service($this->_getAccessKey(), $this->_getSecretKey());
    }

    public function _serializeActor($actor) {
        if ($actor['type'] == 'CanonicalUser') {
            
        }
    }

// class Zend_Service_Amazon_S3_Group {
    // public $uri;
// }

// class Zend_Service_Amazon_S3_CanonicalUser {
    // public $ID;
    // public $DisplayName;
// }
    private $_owner;

    public function getOwner() {
        if ($this->_owner === null) {
            $this->getAcl();
        }

        return $this->_owner;
    }

    public function getAcl()
    {
        $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', array('acl' => true)),
                              'AccessControlPolicy');

        $retval = array(
            'owner' => array(
                'type' => 'CanonicalUser',
                'ID' => (string) $xml->Owner->ID,
                'DisplayName' => (string) $xml->Owner->DisplayName),
            'acl' => array()
        );

        foreach ($xml->AccessControlList->Grant as $grant) {

            $grantee = array();
            $grantee['type'] = (string) $grant->Grantee->attributes(self::SCHEMA_URI)->type;
            foreach ($grant->Grantee->children() as $el) {
                $grantee[$el->getName()] = (string) $el;
            }

            $retval['acl'][] = array(
                'grantee' => $grantee,
                'permission' => (string) $grant->Permission
            );
        }

        return $retval;
    }

    /**
     * Sets the access control list (ACL) permissions for the bucket.
     *
     * To set the ACL of a bucket, you must have <tt>WRITE_ACP</tt> permission.
     *
     * @throws MalformedACLError, InvalidArgument
     */
    public function setAcl(array $acl) {
        $xml = new SimpleXMLElement("<AccessControlPolicy xmlns=\"" . self::XML_NAMESPACE . "\" />");

        $xml_owner = $xml->addChild("Owner");
        $xml_owner->addChild("ID", "f166d4707ae5dc8972f4d33a966f17d5d35a494c71f02fc68b9ecfebafa3b67d");
        $xml_owner->addChild("DisplayName", "giovanni");

        $xml_acl = $xml->addChild('AccessControlList');

        foreach ($acl as $entry) {
            $xml_grant = $xml_acl->addChild("Grant");

            $xml_grant_grantee = $xml_grant->addChild("Grantee");

            $xml_grant_grantee->addAttribute('xsi:type', $entry['grantee']['type'], self::SCHEMA_URI);
            foreach ($entry['grantee'] as $key => $value) {
                if ($key == 'type')
                    continue;

                $xml_grant_grantee->addChild($key, $value);
            }

            $xml_grant->addChild("Permission", $entry['permission']);
        }

//print $xml->asXML(); exit();

        $this->_makeRequest('PUT', $this->_bucket, '/', array('acl' => true), null, $xml);
    }

    /**
     * Returns the policy of this bucket.
     *
     * To use this operation, you must have <tt>GetPolicy</tt> permission,
     * and you must be the bucket owner.
     *
     * @return array ...
     */
    public function getPolicy() {
        try {
            $response = $this->_makeRequest('GET', $this->_bucket, '/', array('policy' => true));
        }
        catch (Exception $e) {
            if ($e->getCode() == 404)
                return null;

            throw $e;
        }

        return json_decode($response->getBody());
    }

    /**
     * Adds or replaces a policy on this bucket.
     *
     * If the bucket already has a policy, the new one completely replaces it.
     *
     * To perform this operation, you must be the bucket owner or someone
     * authorized by the bucket owner to set a policy on the bucket, and have
     * <tt>PutPolicy</tt> permissions.
     *
     * @param ???
     */
    public function setPolicy($policy)
    {
        $this->_makeRequest('PUT', $this->_bucket, '/', array('policy' => true), null, json_encode($policy));
    }

    /**
     * Removes the policy on this bucket.
     *
     * To use this operation, you must have <tt>DeletePolicy</tt> permission
     * and be the bucket owner.
     */
    public function removePolicy()
    {
        $this->_makeRequest('DELETE', $this->_bucket, '/', array('policy' => true));
    }

    /**
     * Returns the bucket's region.
     *
     * You can set the bucket's region using the location parameter of the
     * createBucket() method.
     *
     * @return string Region of the bucket
     */
    public function getLocation()
    {
        $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', array('location' => true)),
                              'LocationConstraint');

        return (string) $xml;
    }

    /**
     * Returns the logging status of a bucket and the permissions users have
     * to view and modify that status.
     *
     * @return array
     */
    public function getLogging()
    {
        $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', array('logging' => true)),
                              'BucketLoggingStatus');

        if (isset($xml->LoggingEnabled)) {
            $retval = array();
            $retval['bucket'] = (string) $xml->LoggingEnabled->TargetBucket;
            $retval['prefix'] = (string) $xml->LoggingEnabled->TargetPrefix;
            $retval['grants'] = array();

            foreach ($xml->LoggingEnabled->TargetGrants as $grant) {
                $email = (string) $grant->Grantee->EmailAddress;
                $permission = (string) $grant->Permission;
                $retval['grants'][$email] = strtolower($permission);
            }
        }
        else {
            $retval = null;
        }

        return $retval;
    }

    /**
     * Sets the logging parameters for a bucket and specifies permissions of
     * who can view and modify the logging parameters.
     *
     * To set the logging status of a bucket, you must be the bucket owner.
     *
     * The bucket owner is automatically granted <tt>FULL_CONTROL</tt> to all logs.
     *
     * @param string $bucket Target bucket for logging
     * @param string $prefix Prefix of the created keys
     * @param array $grants ...
     */
    public function setLogging($bucket, $prefix, array $grants = null)
    {
        $this->_validateBucketName($bucket);

        $xml = new SimpleXMLElement("<BucketLoggingStatus />");
       // xmlns="http://doc.s3.amazonaws.com/2006-03-01

        $xml_logging = $xml->addChild("LoggingEnabled");

        $xml_logging->addChild("TargetBucket", $bucket);
        $xml_logging->addChild("TargetPrefix", $prefix);

        $xml_grants = $xml_logging->addChild("TargetGrants");

        if ($grants) {
            foreach ($grants as $grant) {
            }
        }

                print($xml->asXML());

        $response = $this->_makeRequest('PUT', $this->_bucket, '/', array('logging' => true), null, $xml);
    }

    public function disableLogging()
    {
        $xml = new SimpleXMLElement("<BucketLoggingStatus />");

        $response = $this->_makeRequest('PUT', $this->_bucket, '/', array('logging' => true), null, $xml);
    }

    /**
     * Returns the notification configuration for this bucket.
     *
     * If notifications ar not enabled for this bucket, the operation returns
     * an empty array.
     *
     * @return array ...
     */
    public function getNotifications()
    {
        $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', array('notification' => true)),
                              'NotificationConfiguration');

        $notifications = array();

        foreach ($xml->TopicConfiguration as $xml_topic) {
            $entry = array();
            $entry['topic'] = (string) $xml_topic->Topic;
            $entry['event'] = (string) $xml_topic->Event;

            $notifications[] = $entry;
        }

        return $notifications;
    }

    /**
     * Enables notifications of specified events for this bucket.
     *
     * The owner of the topic must create a policy to enable the bucket owner
     * to publish to the topic.
     *
     * By default, only the bucket owner can configure notifications on a
     * bucket. However, bucket owners can use a bucket policy to grant
     * permission to other users to get this configuration with
     * <tt>s3:PutBucketNotification</tt> permission.
     *
     * After you call this method, a test notification is published to ensure
     * that the bucket owner has permission to publish to the specified topic.
     *
     * @param array $topics ...
     */
    public function setNotifications(array $notifications)
    {
        $xml = new SimpleXMLElement('<NotificationConfiguration xmlns="' . self::XML_NAMESPACE . '" />');

        foreach ($notifications as $notification) {
            $xml_notification = $xml->addChild('TopicConfiguration');
            $xml_notification->addChild('Topic', $notification['topic']);
            $xml_notification->addChild('Event', $notification['event']);
        }

        $this->_makeRequest('PUT', $this->_bucket, '/', array('notification' => true), null, $xml);
    }

    /**
     * Returns the request payment configuration of this bucket.
     *
     * To use this operation, you must be the bucket owner.
     *
     * @return string One of the values {@link self::PAYER_REQUESTER} or {@link self::PAYER_BUCKET_OWNER}
     */
    public function getRequestPayment()
    {
        $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', array('requestPayment' => true)),
                              'RequestPaymentConfiguration');

        $payer = (string) $xml->Payer;

        assert('($payer == self::PAYER_REQUESTER) || ' .
               '($payer == self::PAYER_BUCKET_OWNER)');

        return $payer;
    }

    /**
     * Sets the request payment configuration of this bucket.
     *
     * By default, the bucket owner pays for downloads from the bucket. This
     * configuration parameter enables the bucket owner to specify that the
     * person requesting the download will be charged for it.
     *
     * To use this operation, you must be the bucket owner.
     *
     * @see http://docs.amazonwebservices.com/AmazonS3/latest/dev/index.html?RequesterPaysBuckets.html
     * @param string $payer One of the values {@link self::PAYER_REQUESTER} or {@link self::PAYER_BUCKET_OWNER}
     */
    public function setRequestPayment($payer)
    {
        // if (($payer !== self::PAYER_REQUESTER) &&
            // ($payer !== self::PAYER_BUCKET_OWNER)) {
            // throw new Exception("FIXME");
        // }

        $xml = $this->_newXml('RequestPaymentConfiguration');
        $xml->addChild('Payer', $payer);

        $this->_makeRequest('PUT', $this->_bucket, '/', array('requestPayment' => true), null, $xml);
    }

    /**
     * Returns the versioning state of this bucket.
     *
     * To retrieve the versioning state of a bucket, you must be the bucket owner.
     *
     * @return bool Versioning state
     */
    public function getVersioning()
    {
        $xml = $this->_getXml($this->_makeRequest("GET", $this->_bucket, '/', array('versioning' => true)),
                              'VersioningConfiguration');

        return ((string) $xml->Status == "Enabled" ? true : false);
    }

    /**
     * Sets the versioning state of this bucket.
     *
     * To set the versioning state, you must be the bucket owner.
     *
     * @param bool Versioning state to set
     */
    public function setVersioning($state)
    {
        $xml = $this->_newXml('VersionConfiguration');

        $xml->addChild("State", ($state ? "Enabled" : "Suspended"));
        $xml->addChild("MfaDelete", "Disabled");

        $this->_makeRequest('PUT', $this->_bucket, '/', array('versioning' => true), null, $xml);
    }

    /**
     * Retrieves the website configuration for this bucket.
     *
     * This operation requires the <tt>S3:GetBucketWebsite</tt>
     *
     * @see setWebsiteConfiguration()
     * @return array An array with two elements, the first being the
     *     indexSuffix and the second being the errorObject value if
     *     available, <tt>null</tt> otherwise
     */
    public function getWebsiteConfiguration() {
        $xml = $this->_getXml($this->_makeRequest("GET", $this->_bucket, '/',
                              array('website' => true)),
                              'WebsiteConfiguration');

        // FIXME: try responses with and without configuration

        ... 
        return array($indexSuffix, $errorObject);
    }

    /**
     * Sets the website configuration for this bucket.
     *
     * This operation requires the <tt>S3:PutBucketWebsite</tt> permission.
     * By default, only the bucket owner can configure the website attached to
     * a bucket. However, bucket owners can allow other users to set the
     * website configuration by writing a bucket policy granting them the
     * <tt>S3:PutBucketWebsite</tt> permission.
     *
     * @param string $suffix ...
     * @param string $errorObject ...
     * @see http://docs.amazonwebservices.com/AmazonS3/latest/dev/WebsiteHosting.html
     */
    public function setWebsiteConfiguration($indexSuffix, $errorObject = null) {
        $xml = $this->_newXml('WebsiteConfiguration');

        $xml_index = $xml->addChild('IndexDocument');
        $xml_index->addChild('Suffix', (string) $indexSuffix);

        if ($errorObject !== null) {
            $xml_error = $xml->addChild('ErrorDocument');
            $xml_error->addChild('Key', (string) $errorObject);
        }

        $this->_makeRequest('PUT', $this->_bucket, '/', array('website' => true), null, $xml);
    }

    /**
     * Removes the website configuration for this bucket.
     *
     * This operation requires the <tt>S3:DeleteBucketWebsite</tt> permission.
     *
     * @see setWebsiteConfiguration()
     */
    public function removeWebsiteConfiguration() {
        $this->_makeRequest('DELETE', $this->_bucket, '/', array('website' => true));
    }

    /**
     * Multiple delete many objects with one request
     *
     * Each entry in the objects array can be a simple string, to represent
     * the latest version of an object, or an simple array with two entries
     * (i.e. keys zero and one), the first one being the object key and the
     * second one the object version id.
     *
     * @param array $objects
     */
    public function multipleDelete(array $objects) {
        $xml = new SimpleXMLElement("<Delete xmlns=\"" . self::XML_NAMESPACE . "\" />");

        $xml->addChild("Quiet", "true");

        foreach ($objects as $object) {
            $xml_object = $xml->addChild("Object");
            if (is_string($object)) {
                $xml_object->addChild("Key", $object);
            }
            elseif (is_array($object) && isset($object[0]) &&
                    isset($object[1])) {
                $xml_object->addChild("Key", $object[0]);
                $xml_object->addChild("VersionId", $object[0]);
            }
            else
                throw new Exception("Invalid array format for multipleDelete() request");
        }

        $this->_makeRequest('PUT', $this->_bucket, '/', array('delete' => true), null, $xml);
    }


    public function listVersions()
    {
      $xml = $response->getXML("ListVersionsResult");
      foreach ($xml as $xmlnode) {
          if ($xmlnode->tagName == "Version") {
          }
      }
    }




    public function listUploads($prefix, $delimiter)
    {
        $params = array('uploads' => true);

        $uploads = array();

        do {
            $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', $params),
                                  'ListMultipartUploadsResult');

            foreach ($xml->Upload as $upload) {
                $entry = array();
                $entry['key'] = (string) $upload->Key;
                $entry['upload_id'] = (string) $upload->UploadId;
                $entry['owner'] = (string) $upload->Owner->DisplayName;
                $entry['ctime'] = (string) strtotime($upload->Initiated);
            }

        } while ((string) $xml->IsTruncated == "true");
    }

    /**
     * List the objects in a bucket.
     *
     * Provides the list of object keys that are contained in the bucket.  Valid params include the following.
     * prefix - Limits the response to keys which begin with the indicated prefix. You can use prefixes to separate a bucket into different sets of keys in a way similar to how a file system uses folders.
     * marker - Indicates where in the bucket to begin listing. The list will only include keys that occur lexicographically after marker. This is convenient for pagination: To get the next page of results use the last key of the current page as the marker.
     * max-keys - The maximum number of keys you'd like to see in the response body. The server might return fewer than this many keys, but will not return more.
     * delimiter - Causes keys that contain the same string between the prefix and the first occurrence of the delimiter to be rolled up into a single result element in the CommonPrefixes collection. These rolled-up keys are not returned elsewhere in the response.
     *
     * @param  string $bucket
     * @param array $params S3 GET Bucket Paramater
     * @return array|false
     */
    public function listObjects($prefix = null, $delimiter = null)
    {
        $params = array();
        $params['prefix'] = $prefix;
        $params['delimiter'] = $delimiter;
        //$params['max-keys'] = 10;

        $objects = array();

        do {
            $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/', $params),
                                  'ListBucketResult');

            // this will become the marker for the next request, if needed
            $marker = "";

            // collect the regular objects
            foreach ($xml->Contents as $contents) {
                $entry = array();
                $entry['type'] = 'object';
                $entry['key'] = (string) $contents->Key;
                $entry['size'] = (int) $contents->Size;
                $entry['mtime'] = strtotime($contents->LastModified);
                $entry['md5'] = (string) $contents->ETag;
                $entry['owner'] = (string) $contents->Owner->DisplayName;
                $objects[$entry['key']] = $entry;
                $marker = $entry['key'];
            }

            // update the temporary marker
            $params['marker'] = $marker;

            // collect the "subfolders"
            foreach ($xml->CommonPrefixes as $folder) {
                $entry = array();
                $entry['type'] = 'prefix';
                $entry['prefix'] = (string) $folder->Prefix;
                $objects[$entry['prefix']] = $entry;
                $marker = $entry['prefix'];
            }

            // update the final marker
            if (strcmp($params['marker'], $marker) < 0)
                $params['marker'] = $marker;

        } while ((string) $xml->IsTruncated == "true");

        return $objects;
    }



    public function statObject($object, $version = null) {
      
    }

    /**
     * Get an object
     *
     * @param  string $object
     * @param  bool   $paidobject This is "requestor pays" object
     * @return string|false
     */
    public function getObject($object)
    {
        $this->_fixupObjectName($object);

        $headers = array();

        $paidobject = false;

        if ($paidobject) {
            $headers['x-amz-request-payer'] = 'requester';
        }

        $response = $this->_makeRequest('GET', $this->_bucket, $object, null, $headers);

        return $response->getBody();
    }

    /**
     * Get an object using streaming
     *
     * Can use either provided filename for storage or create a temp file if none provided.
     *
     * @param  string $object Object path
     * @param  string $streamfile File to write the stream to
     * @param  bool   $paidobject This is "requestor pays" object
     * @return Zend_Http_Response_Stream|false
     */
    public function getObjectStream($object, $streamfile = null, $paidobject = false)
    {
        $this->_fixupObjectName($object);

        self::getHttpClient()->setStream($streamfile ? $streamfile : true);

        if ($paidobject) {
            $response = $this->_makeRequest('GET', $object, null, array(self::S3_REQUESTPAY_HEADER => 'requester'));
        }
        else {
            $response = $this->_makeRequest('GET', $object);
        }
        self::getHttpClient()->setStream(null);

        if ($response->getStatus() != 200 || !($response instanceof Zend_Http_Response_Stream)) {
            return false;
        }

        return $response;
    }


    public function getObjectAcl($object)
    {
        // FIXME: merge with getAcl() they do the same thing
        $xml = $this->_getXml($this->_makeRequest('GET', $this->_bucket, '/' . $object, array('acl' => true)),
                              'AccessControlPolicy');

        $retval = array(
            "owner" => (string) $xml->Owner->DisplayName,
            "acl" => array()
        );

        foreach ($xml->AccessControlList->Grant as $grant) {
            $grantee = array();
            $grantee['type'] = (string) $grant->Grantee->attributes(self::SCHEMA_URI)->type;

            foreach ($grant->Grantee->children() as $el) {
                $grantee[$el->getName()] = (string) $el;
            }

            $retval['acl'][] = array(
                'grantee' => $grantee,
                'permission' => (string) $grant->Permission
            );
        }

        return $retval;
    }

    public function storeObjectFile($object, $filename, $type = null, $acl = self::ACL_PRIVATE, array $meta = array())
    {
        $this->storeObject($object, file_get_contents($filename), $type, $acl, $meta);
    }

    /**
     * Adds or replaces an object to a bucket.
     *
     * You must have <tt>WRITE</tt> permissions on a bucket to add an object to it.
     *
     * @param string ...
     */
    public function storeObject($object, $data, $type = null, $acl = self::ACL_PRIVATE, array $meta = array())
    {
        $this->_fixupObjectName($object);

        $headers = array();

        // set the mime-type if given, otherwise defaults to binary because in
        // this request it is mandatory
        $headers['Content-Type'] = ($type != "" ? $type : 'application/octet-stream');

        // verify with the content MD5 for integrity reasons
        $headers['Content-MD5'] = base64_encode(md5($data, true));

        // the default access list policy
        $headers['x-amz-acl'] = $acl;

        // FIXME: investigate?
        //$headers['Expect'] = '100-continue';

        // add custom meta information
        foreach ($meta as $key => $value) {
            $headers['x-amz-meta-' . $key] = $value;
        }

        $this->_makeRequest('PUT', $this->_bucket, '/' . $object, null, $headers, $data);

    }

    /**
     * Remove a given object
     *
     * @param string $object ...
     * @param string $version ...
     */
    public function removeObject($object, $version = null)
    {
        $this->_fixupObjectName($object);

        $params = array();

        if ($version != "") {
            $params['versionId'] = $version;
        }

        $response = $this->_makeRequest('DELETE', $this->_bucket, '/' . $object, $params);

        // Look for a 204 No Content response
        if ($response->getStatus() != 204)
            throw new Exception("Invalid HTTP return status, expected 204, got " . $response->getStatus());
    }

    /**
     * Copy an object
     *
     * You can copy objects only up to 5 GB in size.
     *
     * @param  string $sourceObject  Source object name
     * @param  string $destObject    Destination object name
     * @param  array  $meta          (OPTIONAL) Metadata to apply to desination object.
     *                               Set to null to copy metadata from source object.
     * @return boolean
     */
    public function copyObject($sourceBucket, $sourceObject, $destObject, $meta = null)
    {
        $sourceObject = $this->_fixupObjectName($sourceObject);
        $destObject   = $this->_fixupObjectName($destObject);

        $headers = (is_array($meta)) ? $meta : array();
        $headers['x-amz-copy-source'] = $sourceObject;
        $headers['x-amz-metadata-directive'] = $meta === null ? 'COPY' : 'REPLACE';

        $response = $this->_makeRequest('PUT', $destObject, null, $headers);

        if ($response->getStatus() == 200 && !stristr($response->getBody(), '<Error>')) {
            return true;
        }

        return false;
    }

    /**
     * Move an object
     *
     * Performs a copy to dest + verify + remove source
     *
     * @param string $sourceObject  Source object name
     * @param string $destObject    Destination object name
     * @param array  $meta          (OPTIONAL) Metadata to apply to destination object.
     *                              Set to null to retain existing metadata.
     */
    public function moveObject($sourceObject, $destObject, $meta = null)
    {
        $sourceInfo = $this->getInfo($sourceObject);

        $this->copyObject($sourceObject, $destObject, $meta);
        $destInfo = $this->getInfo($destObject);

        if ($sourceInfo['etag'] === $destInfo['etag']) {
            return $this->removeObject($sourceObject);
        } else {
            return false;
        }
    }

}
