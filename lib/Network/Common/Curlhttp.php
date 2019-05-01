<?php

namespace Kis\Network\Common;

/**
 * Class Curlhttp
 * @package Curlhttp\src
 */
class Curlhttp
{


    /**
     * @ignore
     * 连接超时时间
     */
    public static $_cTimeout = 1.0;

    /**
     * @ignore
     * 读取超时时间
     */
    public static $_rTimeout = 1.0;

    /**
     * @ignore
     * 写入超时时间
     */
    public static $_wTimeout = 1.0;

    /**
     * @ignore
     * User Agent
     */
    public static $_userAgent = 'FO UA v1.0';

    /**
     * @ignore
     * CURL 请求完成后的信息
     */
    private static $_info;

    /**
     * 是否记录post参数
     * @var type
     */
    public static $logPostData = false;

    /**
     * 是否记录请求结果
     * @var type
     */
    public static $logRet = false;

    /**
     * 当cookie值不为空时，就设置cookie
     * @var type
     */
    public static $CURLOPT_COOKIE = '';

    /**
     * 转发请求的URL，如果不为空，失败的情况下就转发到这个地址
     * 默认是不转发的，只有重要的业务，如支付、发货需要设置转发接口
     * @var type
     */
    public static $proxyUrl = '';

    /**
     * 转发加密key
     * @var type
     */
    public static $proxyKey = '51proxy';

    /**
     * 强制使用代理
     * @var type
     */
    public static $proxyForce = false;
    /*
     * log的路路径
     * */
    public static $logPath = "";

    /**
     * 发起get请求并获取返回值
     * @param string $url
     * @return string
     */
    public static function get($url)
    {
        return self::request($url);
    }

    /**
     * 发起post请求并获取返回值
     * @param string $url
     * @param array|string $postFields
     * @return string
     */
    public static function post($url, $postFields = null)
    {
        return self::request($url, 'POST', $postFields);
    }

    /**
     * 发起请求并获取返回值
     * @param string $url
     * @param string $method
     * @param array|string $postFields
     * @param array $headers e.g.  ["Host: kis.51.com"]
     * @return string
     */
    public static function request($url, $method = 'GET', $postFields = null, $headers = null)
    {
        $postFields_pre = $postFields;
        if (is_array($postFields)) {
            $postFields = http_build_query($postFields);
        }
        if (self::$proxyUrl != '' && self::$proxyForce) {
            //强制代理,代理url不为空，显要求强制代理
            //强制走代理，让代理重新请求,注意要处理死循环，代理的url跟当前的url不能是同一个
            if (substr($url, 0, strlen(self::$proxyUrl)) != self::$proxyUrl) {
                //注意：带cookie，带agent的参数带不过去，只支持get,post数据
                $ret = self::proxy($url, $method, $postFields_pre, $headers);
            }
        } else {
            //走正常的curl
            $ci = curl_init();
            curl_setopt($ci, CURLOPT_FOLLOWLOCATION, true); //302的也可以获取
            curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            curl_setopt($ci, CURLOPT_URL, $url);
            curl_setopt($ci, CURLOPT_RETURNTRANSFER, true); // 不直接输出
            curl_setopt($ci, CURLOPT_HEADER, false); // 返回中不包含header
            curl_setopt($ci, CURLOPT_USERAGENT, self::$_userAgent);
            curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, self::$_cTimeout);
            curl_setopt($ci, CURLOPT_TIMEOUT, (self::$_cTimeout + self::$_rTimeout + self::$_wTimeout));
            if (self::$CURLOPT_COOKIE != '') {
                //设置cookie
                curl_setopt($ci, CURLOPT_COOKIE, self::$CURLOPT_COOKIE);
            }
            if ($headers) {
                curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
            }
            if ('POST' == strtoupper($method)) {
                curl_setopt($ci, CURLOPT_POST, true);
                curl_setopt($ci, CURLOPT_POSTFIELDS, $postFields);
            }

            $ret = curl_exec($ci);
            self::$_info = curl_getinfo($ci);

            self::$_info['postfields'] = $postFields;
            if ($ret === false) {
                self::$_info['error'] = curl_errno($ci) . ' ' . curl_error($ci);
            }
            if (self::$logRet && self::$_info['http_code'] == '200') {
                //记录结果,当取仅当是200时才记录结果，并且将结果处理一行，避免换行
                self::$_info['response'] = str_replace("\n", "", $ret);
            }
            curl_close($ci);
            self::logInfo();
        }
        //------ 走代理重试逻辑
        if (self::$_info['http_code'] == '0' && self::$proxyUrl != '') {
            //http_code是0的，让代理重新请求,注意要处理死循环，代理的url跟当前的url不能是同一个
            if (substr($url, 0, strlen(self::$proxyUrl)) != self::$proxyUrl) {
                //注意：带cookie，带agent的参数带不过去，只支持get,post数据
                $ret = self::proxy($url, $method, $postFields_pre, $headers);
            }
        }
        return $ret;
    }

    /**
     * 代理转发
     * @param type $url
     * @param type $method
     * @param type $postFields
     */
    public static function proxy($url, $method = 'GET', $postFields = null, $headers = null)
    {
        if (self::$proxyUrl == '') {
            return '#proxyUrl_is_null';
        }
        $parmas = [];
        $parmas['url'] = $url;
        $parmas['method'] = $method;
        $md5_pre = $parmas['method'] . $parmas['url'];
        if (!is_null($postFields)) {
            $parmas['postFields'] = json_encode($postFields);
            $md5_pre .= $parmas['postFields'];
        }
        if (!is_null($headers)) {
            $parmas['headers'] = $headers;
            $md5_pre .= $parmas['headers'];
        }
        $md5_pre .= self::$proxyKey;
        $parmas['_proxy_token_'] = strtolower(md5($md5_pre));
        $proxyUrl = self::$proxyUrl;
        $logRet = self::$logRet;
        $logPostData = self::$logPostData;
        self::$proxyUrl = ''; //置空
        self::$logRet = true;
        self::$logPostData = true;
        $ret = self::post($proxyUrl, $parmas);
        self::$proxyUrl = $proxyUrl; //还原
        self::$logRet = $logRet;
        self::$logPostData = $logPostData;
        return $ret;
    }

    public static function logInfo($_info = null, $logPostData = false)
    {
        ///data/logs/curl_debug.log
        //var_dump($message);
        /*
          Array
          (
          [url] => http://www.wuming.com/cplapi/level/haileuid?Accounts=wm267302596
          [content_type] => text/html; charset=UTF-8
          [http_code] => 200
          [header_size] => 331
          [request_size] => 119
          [filetime] => -1
          [ssl_verify_result] => 0
          [redirect_count] => 0
          [total_time] => 0.03669
          [namelookup_time] => 0.001866
          [connect_time] => 0.006771
          [pretransfer_time] => 0.006772
          [size_upload] => 0
          [size_download] => 55
          [speed_download] => 1499
          [speed_upload] => 0
          [download_content_length] => -1
          [upload_content_length] => 0
          [starttransfer_time] => 0.036677
          [redirect_time] => 0
          [redirect_url] =>
          [primary_ip] => 118.89.210.218
          [certinfo] => Array
          (
          )

          [postfields] =>
          )

         */
        //time:,url:,http_code:,redirect_count:,total_time:,namelookup_time:, connect_time:, pretransfer_time:, starttransfer_time:, redirect_time:, redirect_url:, primary_ip:

        $logfields = ['time', 'url', 'http_code', 'redirect_count', 'total_time', 'namelookup_time', 'connect_time', 'pretransfer_time', 'starttransfer_time', 'redirect_time', 'redirect_url', 'primary_ip'];
        if ($logPostData) {
            $logfields[] = 'postfields';
        } else {
            if (self::$logPostData) {
                $logfields[] = 'postfields';
            }
        }
        if (self::$logRet) {
            //记录结果
            $logfields[] = 'response';
        }
        if (!$_info) {
            $tmpinfo = self::$_info;
        } else {
            $tmpinfo = $_info;
        }
        $tmpinfo['time'] = date("Y-m-d H:i:s");
        $tmpinfo['url'] = str_replace(["|", "\n"], ["_", ""], $tmpinfo['url']);
        $msg = [];
        foreach ($logfields as $f) {
            $msg[] = $f . ':' . (isset($tmpinfo[$f]) ? $tmpinfo[$f] : '');
        }
        unset($tmpinfo);
        $logPath = "/tmp/curl_debug.log." . date("Ymd");
        if (self::$logPath == "") {
            self::$logPath = $logPath;
        }
        error_log(implode("|", $msg) . "\n", 3, self::$logPath);
    }

    public static function getHttpCode()
    {
        if (!is_array(self::$_info)) {
            return false;
        }
        return self::$_info['http_code'];
    }

    public static function getInfo()
    {
        return self::$_info;
    }

    public static function setRTimeout($rTimeout)
    {
        self::$_rTimeout = $rTimeout;
    }

    //高度自定义的curl请求方法
    public static function customRequest($url, $postFields, $method = 'POST', $has_header = false, $has_body = false, &$ret_header)
    {
        if (is_array($postFields)) {
            $postFields = http_build_query($postFields);
        }
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true); // 不直接输出
        curl_setopt($ci, CURLOPT_USERAGENT, self::$_userAgent);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, self::$_cTimeout);
        curl_setopt($ci, CURLOPT_TIMEOUT, (self::$_cTimeout + self::$_rTimeout + self::$_wTimeout));
        curl_setopt($ci, CURLOPT_NOBODY, $has_body);      //表示是否需要response body,true:不需要 false：需要
        curl_setopt($ci, CURLOPT_HEADER, $has_header);    //表示是否需要response header
        if ('POST' == strtoupper($method)) {
            curl_setopt($ci, CURLOPT_POST, true);
            curl_setopt($ci, CURLOPT_POSTFIELDS, $postFields);
        }
        $ret = curl_exec($ci);
        self::$_info = curl_getinfo($ci);
        self::logInfo();
        curl_close($ci);

        if ($has_header) {
            $header_size = self::$_info['header_size'];
            $ret_header = substr($ret, 0, $header_size - 2);
            $body = substr($ret, $header_size);
        } else {
            $body = $ret;
        }
        return $body;
    }
}