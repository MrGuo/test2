<?php
namespace Libs\Curl;

class MultiCurl {

    const DefaultTimeout = 2; // 默认接囗超时时间
    const DefaultTimeoutConn = 1; // 默认连接时间

    private $useragent = 'Retailerp';
    private $headers = array();
    private $curlMultiHandle = NULL;
    private $requestMap = array();


    public static function instance() {
        static $instance = null;
        is_null($instance) && $instance = new self();
        return $instance;
    }

    public function open() {
        $this->curlMultiHandle = curl_multi_init();
        $this->headers = $this->getHeaders();
    }

    public function send(Array $requests) {
        if (empty($requests)) {
            return NULL;
        }
        foreach ($requests as $key => $request) {
            $curlHandle = $this->initSingleCurl();
            $curlHandle = $this->setOpt($curlHandle, $request);
            $curlHandle = $this->setUrl($curlHandle, $request);
            curl_multi_add_handle($this->curlMultiHandle, $curlHandle);
            $this->requestMap[(string) $curlHandle] = $key;
        }
    }

    private function initSingleCurl() {
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, $this->useragent);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curlHandle, CURLOPT_HEADER, FALSE);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, 1);
        return $curlHandle;
    }

    private function getHeaders() {
        $headers = RetailerpHeaderCreator::getHeaders();
        $headerArr = array('Auth:' . $headers['Auth']);
        return $headerArr;
    }

    private function setOpt($curlHandle, $request) {
        $options = $request->opt;
        if (empty($options['timeout'])) {
            $options['timeout'] = self::DefaultTimeout;
        }
        if (empty($options['connect_timeout'])) {
            $options['connect_timeout'] = self::DefaultTimeoutConn;
        }
        foreach ($options as $type => $value) {
            switch ($type) {
                case 'timeout':
                    curl_setopt($curlHandle, CURLOPT_TIMEOUT, $value);
                    break;
                case 'connect_timeout':
                    curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $value);
                    break;
                case 'timeout_ms':
                    curl_setopt($curlHandle, CURLOPT_TIMEOUT_MS, $value);
                    break;
                case 'connect_timeout_ms':
                    curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT_MS, $value);
                    break;
            }
        }
        return $curlHandle;
    }

    private function setUrl($curlHandle, $request) {
        $params = http_build_query($request->params);
        $url = $request->url;
        $method = $request->method;
        switch ($method) {
            case 'POST':
                curl_setopt($curlHandle, CURLOPT_POST, TRUE);
                curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $params);
                break;
            case 'GET':
                curl_setopt($curlHandle, CURLOPT_HTTPGET, TRUE);
                $url .= '?' . $params;
                break;
        }
        curl_setopt($curlHandle, CURLOPT_URL, $url);
        return $curlHandle;
    }


    public function exec() {
        $active = null;
        do {
            do {
                $status = curl_multi_exec($this->curlMultiHandle, $active);
            } while ($status == CURLM_CALL_MULTI_PERFORM);
            if ($status != CURLM_OK) {
                break;
            }
            $response = array();
            while ($respond = curl_multi_info_read($this->curlMultiHandle)) {
                $responses[$this->requestMap[(string) $respond['handle']]]['content'] = json_decode(curl_multi_getcontent($respond['handle']), TRUE);
                $responses[$this->requestMap[(string) $respond['handle']]]['httpcode'] = curl_getinfo($respond['handle'], CURLINFO_HTTP_CODE);
                // parent::wlog($respond['handle'], $this->file);
                curl_multi_remove_handle($this->curlMultiHandle, $respond['handle']);
                curl_close($respond['handle']);
            }
            if ($active > 0) {
                curl_multi_select($this->curlMultiHandle, 0.05);
            }
        } while ($active);
        return $responses;
    }

    public function close() {
        curl_multi_close($this->curlMultiHandle);
        $this->curlMultiHandle = NULL;
        $this->requestMap = array();
        $this->headers = array();
    }

}
