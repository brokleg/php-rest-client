<?php

/**
 * PHP REST Client build with cURL
 * 
 * @author Fabio Agostinho Boris <fabioboris@gmail.com>
 */
class RestClient
{
    private $host;        // the url to the rest server
    private $token;       // Auth token        
    
    public function __construct($host, $token = null)
    {
        $this->host = $host;
        $this->token = $token;
    }

    /**
     * Returns the absolute URL
     * 
     * @param string $url
     */
    private function url($url = null)
    {
        $_host = rtrim($this->host, '/');
        $_url = ltrim($url, '/');

        return "{$_host}/{$_url}";
    }

    /**
     * Returns the URL with encoded query string params
     * 
     * @param string $url
     * @param array $params
     */
    private function urlQueryString($url, $params = null)
    {
        $qs = array();
        if ($params) {
            foreach ($params as $key => $value) {
                $qs[] = "{$key}=" . urlencode($value);
            }
        }

        $url = explode('?', $url);
        if ($url[1]) $url_qs = $url[1];
        $url = $url[0];
        if ($url_qs) $url = "{$url}?{$url_qs}";

        if (count($qs)) return "{$url}?" . implode('&', $qs);
        else return $url;
    }

    /**
     * Make an HTTP request using cURL
     * 
     * @param string $verb
     * @param string $url
     * @param array $params 
     */
    private function request($verb, $url, $params = array())
    {
        $ch = curl_init();       // the cURL handler
        $url = $this->url($url); // the absolute URL
        $request_headers = array("Authorization: {$this->token}");
        
        // encoded query string on GET
        switch (true) {
            case 'GET' == $verb:
            $url = $this->urlQueryString($url, $params);
            break;
            case in_array($verb, array('POST', 'PUT', 'DELETE')):
            $request_headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
                
        // set the URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // set the HTTP verb for the request
        switch ($verb) {
            case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            break;
            case 'PUT':
            case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
        }

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);

        $response = curl_exec($ch);
        
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = http_parse_headers(substr($response, 0, $header_size));
        $response = substr($response, $header_size);
        $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if (strpos($content_type, 'json')) $response = json_decode($response, true);
        
        switch (true) {
            case 'GET' == $verb:
            if ($http_code !== 200) $this->throw_error($response, $http_code);
            return $response;
            case in_array($verb, array('POST', 'PUT', 'DELETE')):
            if ($http_code !== 303) $this->throw_error($response, $http_code);
            return str_replace(rtrim($this->host, '/') . '/', '', $headers['Location']);
        }
    }

    private function throw_error($response, $http_code)
    {
        if (is_array($response) && array_key_exists('error', $response)) {
            throw new RestClientException($response['error']['description'], intval($response['error']['code']), $http_code);
        } else {
            throw new RestClientException('Unknown RestClient error', 0, $http_code);
        }
    }
    
    /**
     * Make an HTTP GET request
     * 
     * @param string $url
     * @param array $params
     */
    public function get($url, $params = array())
    {
        return $this->request('GET', $url, $params);
    }

    /**
     * Make an HTTP POST request
     * 
     * @param string $url
     * @param array $params
     */
    public function post($url, $params = array())
    {
        return $this->request('POST', $url, $params);
    }

    /**
     * Make an HTTP PUT request
     * 
     * @param string $url
     * @param array $params
     */
    public function put($url, $params = array())
    {
        return $this->request('PUT', $url, $params);
    }

    /**
     * Make an HTTP DELETE request
     * 
     * @param string $url
     * @param array $params
     */
    public function delete($url, $params = array())
    {
        return $this->request('DELETE', $url, $params);
    }
}

class RestClientException extends Exception
{
    protected $http_code;
    
    public function __construct($message, $code = 0, $http_code, Exception $previous = null)
    {
        $this->http_code = $http_code;
        parent::__construct($message, $code, $previous);
    }

    public function getHttpCode() 
    {
        return $this->http_code;
    }
    
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message} (HTTP status code: {$this->http_code})\n";
    }

}
