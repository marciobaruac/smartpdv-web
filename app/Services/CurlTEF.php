<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;

error_reporting(E_ALL);
ini_set('display_errors', 'On');

class CurlTEF
{
    private $url;
    private $options;

    /**
     * @param string $url     Request URL
     * @param array  $options cURL options
     */
    public function __construct($url, array $options = [])
    {
        $this->url = $url;
        $this->options = $options;
    }

    /**
     * Get the response
     * @return string
     * @throws \RuntimeException On cURL error
     */

    public function executeCurl(array $post, $type = 'POST')
    {
        
        $ch = \curl_init($this->url);

        $header = array(
            'Content-Type: application/json'
        );

        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, false);
        \curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        // \curl_setopt($ch, \CURLOPT_ENCODING, '');
        // \curl_setopt($ch, \CURLOPT_MAXREDIRS, 10);
        // \curl_setopt($ch, \CURLOPT_TIMEOUT, 0);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, $header);
        \curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, json_encode((object) $post));
        \curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, $type);
        
        $response = \curl_exec($ch);
        $error    = \curl_error($ch);
        $errno    = \curl_errno($ch);

        if (\is_resource($ch)) {
            \curl_close($ch);
        }

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return $response;
    }

}
