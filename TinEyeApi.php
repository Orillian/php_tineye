<?php
/**
 * TinEyeApi.php
 *
 * PHP7 library to ease communication with the TinEye API server.
 *
 * This library is loosely based on the Node Javascript version of found at https://github.com/TinEye/tineye_api_node
 * It implements all the same methods and returns data in a similar fashion.
 *
 * Notes: I wrote the request method using basic curl, so as to limit dependencies. All of it could be just as
 * easily converted to guzzle or some such.
 *
 * Author: Richard Hobson
 * Date: 2017-01-20
 * Time: 3:36 PM
 */

require_once('TinEyeBaseApi.php');

class TinEyeApi
{
    private $apiURL;
    private $publicKey;
    private $privateKey;
    private $apiRequest;

    /**
     * @param string $apiURL
     * @param string $publicKey
     * @param string $privateKey
     */
    public function __construct(string $apiURL, string $publicKey, string $privateKey)
    {
        $this->apiURL = $apiURL ?? 'https://api.tineye.com/rest/';
        $this->publicKey = $publicKey ?? null;
        $this->privateKey = $privateKey ?? null;

        $this->apiRequest = new TinEyeBaseApi($this->apiURL, $this->publicKey, $this->privateKey);
    }

    /**
     * Lists the number of indexed images on TinEye.
     *
     * Returns: TinEye image count.
     *
     * @return mixed|string
     */
    public function imageCount()
    {
        return $this->request('image_count');
    }


    /**
     * Send request to API and process results.
     *
     * - `method`, API method to call.
     * - `params`, dictionary of fields to send to the API call.
     * - `imageFile`, tuple containing info (filename, data) about image to send.
     *
     * @param string $method
     * @param array $params
     * @param null $imageFile
     * @return mixed|string
     */
    private function request(string $method, array $params = [], $imageFile = null)
    {
        $response = null;
        $obj = null;

        if ($imageFile === null) {

            $requestData = $this->apiRequest->getRequest($method, $params);
            $handle = curl_init();
            curl_setopt($handle, CURLOPT_URL, $requestData['requestUrl']);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
            $api_response = curl_exec($handle);
            return json_decode($api_response, True);

        } else {

            $boundary = "---------------------" . md5(mt_rand() . microtime());

            $requestData = $this->apiRequest->postRequest($method, $imageFile[0], $params, $boundary);

            $header_boundary = $boundary;
            // modify boundary for the post fields string.
            $boundary = '--' . $boundary;
            $contentDisposition = "\nContent-Disposition: form-data; name=";
            $post_fields = $boundary . $contentDisposition."\"image_upload\"; filename=\"{$imageFile[0]}\"\nContent_Type: application/octet-stream\n\n{$imageFile[1]}\n";
            $post_fields .= $boundary . $contentDisposition."\"api_key\"\n\n{$this->publicKey}\n";
            $post_fields .= $boundary . $contentDisposition."\"date\"\n\n{$requestData['date']}\n";
            $post_fields .= $boundary . $contentDisposition."\"nonce\"\n\n{$requestData['nonce']}\n";
            $post_fields .= $boundary . $contentDisposition."\"api_sig\"\n\n{$requestData['api_sig']}\n";

            foreach ($requestData['params'] as $key => $param) {
                $post_fields .= $boundary . $contentDisposition."\"{$key}\"\n\n{$param}\n";
            }

            $post_fields .= $boundary . "--";

            $handle = curl_init();
            curl_setopt($handle, CURLOPT_URL, $this->apiURL . $method . '/');
            curl_setopt($handle, CURLOPT_POST, 1);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($handle, CURLOPT_HTTPHEADER, [
                "Expect: 100-continue",
                "Content-Type: multipart/form-data; boundary=$header_boundary",
            ]);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);

            // Call API and convert results to a usable JSON object.
            $api_response = curl_exec($handle);
            curl_close($handle);

            $api_json_response = json_decode($api_response, True);

            if ($api_json_response['code'] == 200) {
                return $api_json_response;
            } else {
                return 'Something went wrong!';
            }
        }
    }

    /**
     * Lists the number of searches you have left in your current active block.
     *
     * Returns: a dictionary with remaining searches, start time and end time of block.
     *
     * @return mixed|string
     */
    public function remainingSearches()
    {
        return $this->request('remaining_searches');
    }

    /**
     * Perform searches on the TinEye index using image data.
     *
     * - `data`, image data to use for searching. Pass in the "contents" of the image file as a string.
     * - `offset`, offset of results from the start, defaults to 0.
     * - `limit`, number of results to return, defaults to 10.
     * - `sort`, sort results by score, file size (size), or crawl date (crawl_date), defaults to score.
     * - `order`, sort results by ascending (asc) or descending criteria.
     *
     * Returns: a TinEye Response object.
     *
     * @param string $data
     * @param array $options
     * @return bool|mixed
     */
    public function searchData(string $data, array $options = [])
    {
        $options = self::searchOptions($options);
        $imageFile = [ 'image.jpg', $data ];
        return $this->request('search', $options, $imageFile);
    }

    /**
     * Parse search options from array.
     *
     * - `options`, array of search options such as 'offset', 'limit', 'sort' and 'order'.
     *
     * Returns: search options.
     *
     * @param array $options
     * @return array
     */
    private function searchOptions(array $options = [])
    {
        $options['offset'] = $options['offset'] ?? 0;
        $options['limit'] = $options['limit'] ?? 10;
        $options['sort'] = $options['sort'] ?? 'score';
        $options['order'] = $options['order'] ?? 'desc';
        return $options;
    }

    /**
     * Perform searches on the TinEye index using an image URL.
     *
     * - `url`, the URL of the image that will be searched for, must be urlencoded.
     * - `offset`, offset of results from the start, defaults to 0.
     * - `limit`, number of results to return, defaults to 10.
     * - `sort`, sort results by score, file size (size), or crawl date (crawl_date), defaults to score.
     * - `order`, sort results by ascending (asc) or descending criteria.
     *
     * Returns: a TinEye Response object.
     *
     * @param string $url
     * @param array $options
     * @return bool|mixed
     */
    public function searchUrl(string $url, array $options = [])
    {
        $options = self::searchOptions($options);
        $options['image_url'] = $url ?? '';
        return $this->request('search', $options);
    }
}
