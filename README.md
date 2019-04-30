# kis
kis lib is private lib
Kis for PHP
=======================================



Install
-------

To install with composer:

```sh
composer require fiveone/kis
```

Requires PHP 5.6 or newer.

Usage
-----

Here's a basic usage example:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Kis\Network\Common\Curlhttp;

Curlhttp::$logPath = "d:/myf/mylog.log";//日志地址，不设置使用"/tmp/curl_debug.log." . date("Ymd");
Curlhttp::$logRet = true;//true 日志中记录response
Curlhttp::$proxyForce = false;//true使用代理，转发到Curlhttp::$proxyUrl
Curlhttp::$proxyUrl = "http://xxxx";//转发代理地址
Curlhttp::$_cTimeout = 1;
Curlhttp::$_rTimeout = 1;
Curlhttp::$_wTimeout = 1;
Curlhttp::$logPostData = false;//true表示记录post参数
$response = Curlhttp::get("http://xyz111.com/sumeurl");
?>

```



