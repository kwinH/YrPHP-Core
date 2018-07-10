<?php
/**
 * Project: YrPHP.
 * User: Kwin
 * QQ:284843370
 * Email:kwinwong@hotmail.com
 * GitHub:https://github.com/kwinH/YrPHP
 */

namespace YrPHP;

use \DB;
use Route;

class Debug
{
    public static $info = array();
    public static $queries = array();
    public static $startTime;                //保存脚本开始执行时的时间（以微秒的形式保存）
    public static $stopTime;                //保存脚本结束执行时的时间（以微秒的形式保存）
    public static $includeFile;

    /**
     * 在脚本开始处调用获取脚本开始时间的微秒值
     */
    public static function start()
    {
        static::$startTime = microtime(true);   //将获取的时间赋给成员属性$startTime
    }

    /**
     *在脚本结束处调用获取脚本结束时间的微秒值
     */
    public static function stop()
    {
        static::$stopTime = microtime(true);   //将获取的时间赋给成员属性$stopTime
    }

    /**
     * 添加调试消息
     * @param    string $msg 调试消息字符串
     * @param    int $type 消息的类型
     */
    public static function addMsg($msg, $type = 0)
    {
        if (defined("DEBUG") && DEBUG == 1) {
            switch ($type) {
                case 0:
                    static::$info[] = $msg;
                    break;
                case 1:
                    static::$includeFile[] = $msg;
                    break;
                case 2:
                    static::$queries[] = $msg;
                    break;
                default:
                    return false;
            }
        }
    }

    public static function listenSql()
    {
        DB::listen(function ($sql, $param, $time) {
            static::$queries[] = [
                'sql' => $sql,
                'time' => $time,
                'param' => $param
            ];
        });
    }

    /**
     * 已经实例化的自定义类集合
     * @return array
     */
    public static function newClasses()
    {
        $declaredClasses = [];
        foreach (get_declared_classes() as $class) {
            //实例化一个反射类
            $reflectionClass = new \ReflectionClass($class);
            //如果该类是自定义类
            if ($reflectionClass->isUserDefined()) {
                //导出该类信息
                // \Reflection::export($reflectionClass);
                $declaredClasses[] = $class;
            }

        }
        return $declaredClasses;
    }

    /**
     *  返回被 include 和 require 文件名的 array
     * @return array
     */
    public static function getIncludedFiles()
    {
        return get_included_files();
    }

    /**
     * 调试时代码高亮显示
     * @param $str
     * @return mixed
     */
    public static function highlightCode($str)
    {
        /* The highlight string function encodes and highlights
         * brackets so we need them to start raw.
         *
         * Also replace any existing PHP tags to temporary markers
         * so they don't accidentally break the string out of PHP,
         * and thus, thwart the highlighting.
         */
        $str = str_replace(
            array('&lt;', '&gt;', '<?', '?>', '<%', '%>', '\\', '</script>'),
            array('<', '>', 'phptagopen', 'phptagclose', 'asptagopen', 'asptagclose', 'backslashtmp', 'scriptclose'),
            $str
        );

        // The highlight_string function requires that the text be surrounded
        // by PHP tags, which we will remove later
        $str = highlight_string('<?php ' . $str . ' ?>', TRUE);

        // Remove our artificially added PHP, and the syntax highlighting that came with it
        $str = preg_replace(
            array(
                '/<span style="color: #([A-Z0-9]+)">&lt;\?php(&nbsp;| )/i',
                '/(<span style="color: #[A-Z0-9]+">.*?)\?&gt;<\/span>\n<\/span>\n<\/code>/is',
                '/<span style="color: #[A-Z0-9]+"\><\/span>/i'
            ),
            array(
                '<span style="color: #$1">',
                "$1</span>\n</span>\n</code>",
                ''
            ),
            $str
        );

        // Replace our markers back to PHP tags.
        return str_replace(
            array('phptagopen', 'phptagclose', 'asptagopen', 'asptagclose', 'backslashtmp', 'scriptclose'),
            array('&lt;?', '?&gt;', '&lt;%', '%&gt;', '\\', '&lt;/script&gt;'),
            $str
        );
    }

    /**
     * 输出调试消息
     * @return string
     */
    public static function message()
    {
        $mess = "";
        $mess .= '<div style="clear:both;font-size:12px;background:#ddd;border:1px solid #009900;z-index:100;position: fixed;right: 0;bottom: 0;width: auto" id="_yrcms_debug">';
        $mess .= '<div style="float:left;width:100%;" ><span><b>运行信息</b>( <font color="red">' . static::spent(STARTTIME, microtime(true)) . ' </font>秒)：</span><span onclick="_debug_details=document.getElementById(\'_debug_details\');_yrcms_debug=document.getElementById(\'_yrcms_debug\');if(_debug_details.style.display==\'none\'){_debug_details.style.display=\'inline\';_yrcms_debug.style.width=\'100%\';this.innerHTML=\'隐藏X\';}else{_debug_details.style.display=\'none\';_yrcms_debug.style.width=\'auto\';this.innerHTML=\'详情√\';}" style="cursor:pointer;float:right;width:35px;background:#500;border:1px solid #555;color:white">详情√</span></div><br/><div id="_debug_details" style="display:none">';
        $mess .= '<ul style="margin:0px;padding:0 10px 0 10px;list-style:none">';


        static::$info[] = '内存使用：<strong style="color:red">' . round(memory_get_usage() / 1024, 2) . ' KB</strong>';
        static::$info[] = '控制器地址：' . Route::getCurrentRoute()->getCtlPath();
        static::$info[] = '调用方法：' . Route::getCurrentRoute()->getAction('uses');
        if (count(static::$info) > 0) {
            $mess .= '<br>［系统信息］';
            foreach (static::$info as $info) {
                $mess .= '<li>&nbsp;&nbsp;&nbsp;&nbsp;' . $info . '</li>';
            }
        }
        // Key words we want bolded
        $highlight = array('SELECT', 'DISTINCT', 'FROM', 'WHERE', 'AND', 'LEFT&nbsp;JOIN', 'ORDER&nbsp;BY', 'GROUP&nbsp;BY', 'LIMIT', 'INSERT', 'INTO', 'VALUES', 'UPDATE', 'OR&nbsp;', 'HAVING', 'OFFSET', 'NOT&nbsp;IN', 'IN', 'LIKE', 'NOT&nbsp;LIKE', 'COUNT', 'MAX', 'MIN', 'ON', 'AS', 'AVG', 'SUM', '(', ')');

        $mess .= '<br>［SQL语句］';
        foreach (static::$queries as $val) {
            $sql = static::highlightCode($val['sql']);
            foreach ($highlight as $bold) {
                $sql = str_replace($bold, '<strong>' . $bold . '</strong>', $sql);
            }
            $mess .= '<li style="word-wrap:break-word;word-break:break-all;overflow: hidden;">[' . $val['time'] . ' 秒]&nbsp;&nbsp;&nbsp;&nbsp;' . $sql;

            $mess .= json_encode($val['param'], JSON_UNESCAPED_UNICODE);
            $mess .= '</li>';
        }

        $mess .= '</ul>';
        return $mess . '</div></div>';
    }

    /**
     * 返回同一脚本中两次获取时间的差值
     * @param int $startTime
     * @param int $stopTime
     * @return string
     */
    public static function spent($startTime = 0, $stopTime = 0)
    {
        $startTime = empty($startTime) ? static::$startTime : $startTime;
        $stopTime = empty($stopTime) ? static::$stopTime : $stopTime;
        // return round((static::$stopTime - static::$startTime), 4);  //计算后以4舍5入保留4位返回
        return sprintf("%1\$.4f", ($stopTime - $startTime));  //计算后保留4位返回
    }

    /**
     * 记录日志 保存到项目runtime下
     * @param string $content
     * @param string $fileName
     */
    public static function log($content = '', $fileName = null)
    {
        $fileName = is_null($fileName) ? 'log-' . date('Y-m-d') : $fileName;
        $fileName = config('logDir') . $fileName . '.log';
        file_put_contents($fileName, $content . "\n", FILE_APPEND);
    }
}
