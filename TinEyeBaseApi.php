<?php
/**
 * TinEyeBaseApi.php
 *
 * PHP7 library to ease communication with the TinEye API server.
 *
 * This library is loosely based on the Node Javascript version of found at https://github.com/TinEye/tineye_api_node
 * It implements all the same methods and returns data in a similar fashion.
 *
 * Author: Richard Hobson
 * Date: 2017-01-20
 * Time: 9:50 AM
 *
 * For more information see https://api.tineye.com/documentation/authentication
 *
 */
class TinEyeBaseApi
{
    const minNonceLength = 24;
    const maxNonceLength = 255;
    const nonceAllowableChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRTSUVWXYZ0123456789-_=.,*^";
    /**
     * @var string
     */
    private $apiUrl;
    /**
     * @var string
     */
    private $publicKey;
    /**
     * @var string
     */
    private $privateKey;

    /**
     * TinEye_BaseApi constructor.
     *
     *
     * @param string $apiUrl
     * @param string $publicKey
     * @param string $privateKey
     */
    public function __construct(string $apiUrl, string $publicKey, string $privateKey)
    {
        $this->apiUrl = $apiUrl;
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * Generate an API GET request string.
     *
     * - `method`, API method being called.
     * - `requestParams`, the list of search parameters.
     *
     * Returns: a URL to send the search request to including the search parameters.
     *
     * @param string $method
     * @param array $requestParams
     * @return string
     */
    public function getRequest(string $method, array $requestParams = [])
    {
        $nonce = $this->generateNonce();
        $date = time();
        $apiSignature = $this->generateGetHmacSignature($method, $nonce, $date, $requestParams);

        return $this->requestUrl($method, $nonce, $date, $apiSignature, $requestParams);
    }

    /**
     * Generate a nonce used to make a request unique.
     *
     * - `nonceLength`, length of the generated nonce.
     *
     * Returns: a nonce.
     *
     * @param int $nonceLength
     * @return string
     * @throws Exception
     */
    private function generateNonce(int $nonceLength = self::minNonceLength)
    {
        if (empty($nonceLength) || $nonceLength < self::minNonceLength || $nonceLength > self::maxNonceLength) {
            throw new Exception('Nonce length must be an int between ' . self::minNonceLength . ' and' . self::maxNonceLength . ' characters.');
        }

        $nonce = '';

        $length = strlen(self::nonceAllowableChars);
        for ($i = 0; $i < $nonceLength; $i++) {

            $nonce .= substr(self::nonceAllowableChars, rand(0, $length - 1), 1);
        }

        return $nonce;
    }

    /**
     * Generate the HMAC signature hash for a GET request.
     *
     * - `method`, the API method being called.
     * - `nonce`, a nonce.
     * - `date`, UNIX timestamp of the request.
     * - `requestParams`, other search parameters.
     *
     * Returns: an HMAC signature hash.
     *
     * @param string $method
     * @param string $nonce
     * @param int $date
     * @param array $requestParams
     * @return string
     */
    private function generateGetHmacSignature(string $method, string $nonce, int $date, array $requestParams = [])
    {
        $httpVerb = 'GET';
        $paramStr = $this->sortParams($requestParams);
        $requestURL = $this->apiUrl . $method . '/';
        $toSign = $this->privateKey . $httpVerb . (string)$date . $nonce . $requestURL . $paramStr;

        return $this->generateHmacSignature($toSign);
    }

    /**
     * Helper method to sort request parameters.
     * If requestParams has the imageUrl parameter it is URL
     * encoded and then lowercased.
     *
     * - `$requestParams`, list of extra search parameters.
     *
     * Returns: the search parameters in alphabetical order in query
     * string params.
     *
     * @param array $requestParams
     * @param bool $lowercase
     * @return string
     */
    private function sortParams(array $requestParams = [], bool $lowercase = true)
    {
        $keys = [];
        $unsortedParams = [];
        $specialKeys = [ 'api_key', 'api_sig', 'date', 'nonce', 'image_upload' ];

        foreach ($requestParams as $key => $value) {

            $lowercaseKey = strtolower((string)$key);
            if (!in_array($lowercaseKey, $specialKeys)) {
                if ($lowercaseKey === 'image_url') {
                    if (!strpos($value, '%')) {
                        $value = $this->encodeURIComponent($value);
                        $value = preg_replace([ '~!~', '~%20~' ], [ '%21', '+' ], $value);
                    }
                    $unsortedParams[$lowercaseKey] = (string)$value;
                    if ($lowercase) {
                        $unsortedParams[$lowercaseKey] = strtolower((string)$value);
                    }
                } else {
                    $unsortedParams[$lowercaseKey] = (string)$requestParams[$key];
                }
            }
            $keys[] = (string)$key;
        }
        sort($keys);
        $sortedPairs = [];

        foreach ($keys as $key) {
            $sortedPairs[] = (string)$key . '=' . $unsortedParams[strtolower((string)$key)];
        }

        return join('&', $sortedPairs);
    }

    /**
     * PHP equivalent to the Javascript encodeURIComponent
     *
     * Found on stack overflow: http://stackoverflow.com/questions/1734250/what-is-the-equivalent-of-javascripts-encodeuricomponent-in-php
     * Credit: Gumbo
     *
     * @param string $str
     * @return string
     */
    private function encodeURIComponent(string $str)
    {
        $revert = [ '%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')' ];
        return strtr(rawurlencode($str), $revert);
    }

    /**
     * Generate the HMAC signature hash given a message to sign.
     *
     * - `toSign`, the message to sign.
     *
     * Returns: HMAC signature hash.
     *
     * @param string $toSign
     * @return string
     */
    private function generateHmacSignature(string $toSign) : string
    {
        return hash_hmac('sha1', $toSign, $this->privateKey);
    }

    /**
     *  * Helper method to generate a URL to call given a method,
     * a signature and parameters.
     *
     * - `method`, API method being called.
     * - `nonce`, a nonce.
     * - `date`, UNIX timestamp of the request.
     * - `apiSignature`, the signature to be included with the URL.
     * - `requestParams`, the parameters to be included with the URL.
     *
     * Returns: A Request data object with the requestUrl as well as the nonce,date,api_sig and the params list..
     *
     * @param string $method
     * @param string $nonce
     * @param int $date
     * @param string $apiSignature
     * @param array $requestParams
     * @return array
     */
    private function requestUrl(string $method, string $nonce, int $date, string $apiSignature, array $requestParams = [])
    {
        $baseURL = $this->apiUrl . $method . '/';
        $requestUrl = $baseURL . '?api_key=' . $this->publicKey . '&date=' . (string)$date . '&nonce=' . $nonce . '&api_sig=' . $apiSignature;

        $extraParams = $this->sortParams($requestParams, false);

        if ($extraParams !== '') {
            $requestUrl .= '&' . $extraParams;
        }

        $requestData = [
            'requestUrl' => $requestUrl,
            'nonce'      => $nonce,
            'date'       => (string)$date,
            'api_sig'    => $apiSignature,
            'params'     => $requestParams,
        ];

        return $requestData;
    }

    /**
     * Generate an API POST request string for an image upload search.
     * The POST request string can be sent as is to issue the POST
     * request to the API server.
     *
     * - `method`, API method being called.
     * - `filename`, the filename of the image that is being searched for.
     * - `requestParams`, the list of search parameters.
     *
     * Returns:
     * - `requestUrl`, the URL to send the search to.
     *
     * @param string $method
     * @param string $filename
     * @param array $requestParams
     * @param string $boundary
     * @return string
     * @throws Exception
     */
    public function postRequest(string $method, string $filename, array $requestParams = [], string $boundary)
    {
        if (($filename === null) || (strlen(preg_replace('~^\s+|\s+$~', '', (string)$filename)) === 0)) {
            throw new Exception('Must specify an image to search for.', 400);
        }

        $nonce = $this->generateNonce();
        $date = time();
        $apiSignature = $this->generatePostHmacSignature('search', $boundary, $nonce, $date, $filename, $requestParams);

        return $this->requestUrl($method, $nonce, $date, $apiSignature, $requestParams);
    }

    /**
     * Generate the HMAC signature hash for a GET request.
     *
     * - `$method`, the API method being called.
     * - `$nonce`, a nonce.
     * - `$date`, UNIX timestamp of the request.
     * - `$requestParams`, other search parameters.
     *
     * Returns: an HMAC signature hash.
     *
     * @param string $method
     * @param string $boundary
     * @param string $nonce
     * @param int $date
     * @param string $filename
     * @param array $requestParams
     * @return string
     */
    private function generatePostHmacSignature(string $method, string $boundary, string $nonce, int $date, string $filename, array $requestParams = [])
    {
        $httpVerb = 'POST';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $paramStr = $this->sortParams($requestParams);
        $requestURL = $this->apiUrl . $method . '/';
        $filename = strtolower(preg_replace('~%20~', '+', $this->encodeURIComponent($filename)));
        $toSign = $this->privateKey . $httpVerb . $contentType . $filename . (string)$date . $nonce . $requestURL . $paramStr;

        return $this->generateHmacSignature($toSign);
    }
}