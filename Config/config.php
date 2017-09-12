<?php
/**
 * Created by YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */
return [
    'uriAutoAddressing' => false,//uri地址自动定位到控制器
    'defaultCtl' => 'Index', // 默认控制器名称
    'defaultAct' => 'index', // 默认操作名称

    'ctrBaseNamespace' => 'Controllers',//默认的控制器层名称
    'modelBaseNamespace' => "Models",//默认的模型层名称
    'contentType' => 'text/html', //指定客户端能够接收的内容类型
    'charset' => 'UTF-8', //采用编码格式
    'timezone' => 'PRC', //时区
    'urlSuffix' => '.html',     // 默认URL文件后缀


    /* -----------错误处理----------------------------------*/
    'logRecord' => true,   // bool 默认错误记录日志
    'logDir' => APP_PATH . 'Runtime/Logs/', // 日志记录目录


    /*--------------------以下是模版配置---------------------------------------*/
    'setTemplateDir' => APP_PATH . "views/", //设置模板目录位置
    /**
     * /模板文件扩展后缀默认值
     * 如 display('index')：将找到模板目录下的index.html文件
     * 如果设置了后缀如：display('index.php');
     * 将不理会配置中的该参数，找到模板目录下的index.php文件
     */
    'templateExt' => 'php',
    'setCompileDir' => APP_PATH . "Runtime/CompileTpl/", //设置模板被编译成PHP文件后的文件位置
    'caching' => 0, //缓存(静态化)开关 1开启，0为关闭
    'setCacheDir' => APP_PATH . "Runtime/Cache/", //设置缓存的目录
    'cacheLifetime' => 60 * 60 * 24 * 7, //设置缓存的时间
    'leftDelimiter' => "{", //模板文件中使用的“左”分隔符号
    'rightDelimiter' => "}", //模板文件中使用的“右”分隔符号


    /*--------------------以下是数据库配置---------------------------------------*/
    'openCache' => false, //是否开启数据库数据缓存
    'defaultFilter' => 'htmlspecialchars', // 默认参数过滤方法 用于I函数过滤 多个用|分割 如：stripslashes|htmlspecialchars
    'sqlLog' => true,//bool 是否记录sql日志

    /*--------------------以下是数据缓存配置---------------------------------------*/
    'cacheCompress' => false,  // 数据缓存是否压缩缓存
    'dbCacheTime' => 0, //数据缓存时间0表示永久
    'dbCacheType' => 'file', //数据缓存类型 file|memcache|memcached|redis
    //单个item大于1M的数据存memcache和读取速度比file
    'dbCachePath' => APP_PATH . 'Runtime/Data/',//数据缓存文件地址(仅对file有效)
    'dbCacheExt' => 'php',//生成的缓存文件后缀(仅对file有效)

    'memcache' => '127.0.0.1:11211',//string|array多个用数组传递 array('127.0.0.1:11211','127.0.0.1:1121')
    'redis' => '127.0.0.1:6379',//string|array多个用数组传递 array('127.0.0.1:6379','127.0.0.1:6378')


    /*--------------------以下是session配置---------------------------------------*/
    'session' => [
        'expire' => 7200,//有效期时长
        'saveHandler' => 'files',
        'savePath' => APP_PATH . "Runtime/Session/",
        'name' => 'YrPHP',
        'domain' => '',//设置域，默认为当前域名
    ],
    /*--------------------以下是cookie配置---------------------------------------*/
    'cookiePrefix' => 'yrPHP_',
    'cookieExpire' => 7200,//有效期时长
    'cookiePath' => "/",
    'cookieDomain' => '',//设置域，默认为当前域名

    /*--------------------以下是加密配置---------------------------------------*/
    'cryptMode' => 'des3',//现在加密方式只有DES3
    'cryptKey' => '123456789',//密钥
    'cryptIv' => '123456789',//初始向量

    /*--------------------以下是全局中间件配置---------------------------------------*/
    'middleware' => [
        //在实例化控制器之前
        'before' => [
              YrPHP\Middleware\VerifyCsrfToken::class,
        ],
        //在实例化控制器实例化之后，未调用方法之前
        'middle' => [

        ],
        //调用方法之后
        'after' => [

        ],
    ],
];


