<?php

//精度为毫秒的时间戳
function stime(){
    list($msec, $sec) = explode(' ', microtime());
    $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}

/**
 * 并行发送curl请求
 * 普通的循环curl是执行完(这里的执行是指执行并完整返回数据回来完毕)一个curl然后再重新开启另一个curl
 * 这里采用了curl_multi模式，把curl循环用curl_multi_add_handle放入列里
 * 然后用curl_multi_exec来一瞬间一起执行所有的curl
 * 达到节省时间的目的
 */
function curl_multi_post($arr,$header=null){
    $mh = curl_multi_init();
    foreach($arr as $k => $v) {
        $ch[$k] = curl_init($v['url']);
        curl_setopt($ch[$k], CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($ch[$k], CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($ch[$k], CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($ch[$k], CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($ch[$k], CURLOPT_POSTFIELDS, $v['data']); // Post提交的数据包
        curl_setopt($ch[$k], CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($ch[$k], CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($ch[$k], CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($ch[$k], CURLOPT_HTTPHEADER, $header);
        curl_multi_add_handle($mh, $ch[$k]); //决定exec输出顺序
    }
    $running = null;
    do { //执行批处理句柄
        curl_multi_exec($mh, $running); //CURLOPT_RETURNTRANSFER如果为0,这里会直接输出获取到的内容.如果为1,后面可以用curl_multi_getcontent获取内容.
        curl_multi_select($mh); //阻塞直到cURL批处理连接中有活动连接,不加这个会导致CPU负载超过90%.
    } while ($running > 0);

    foreach($ch as $v) {
        $json[] = curl_multi_getcontent($v);
        curl_multi_remove_handle($mh, $v);
    }
    curl_multi_close($mh);

    return $json;
}

//下载图片
function getImage($imagePath){
    $path = '/usr/local/uploads/';
    $arr = explode('/',$imagePath);

    if(!is_dir($path.$arr[5])){
        mkdir($path.$arr[5],0777,true);
    }

    ob_start();
    readfile($imagePath);		//读取图片
    $img = ob_get_contents();	//得到缓冲区中保存的图片
    ob_end_clean();		//清空缓冲区

    $fp = fopen($path.$arr[5].'/'.$arr[6],'w');	//写入图片
    if(fwrite($fp,$img))
    {
        fclose($fp);
        return true;
    }else{
        fclose($fp);
        return false;
    }
}

//get
function get($url,$headers=null){
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
    empty($headers) || curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);  // 从证书中检查SSL加密算法是否存在
    $tmpInfo = curl_exec($curl);     //返回api的json对象
    //关闭URL请求
    curl_close($curl);
    return $tmpInfo;    //返回json对象
}

//get
function http_get($url,$headers=null){
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
    empty($headers) || curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $tmpInfo = curl_exec($curl);     //返回api的json对象
    //关闭URL请求
    curl_close($curl);
    return $tmpInfo;    //返回json对象
}


//post
function post($url,$data,$headers=null){ // 模拟提交数据函数
    $curl = curl_init(); // 启动一个CURL会话
    curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
    curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
    curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
    empty($headers) || curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    $tmpInfo = curl_exec($curl); // 执行操作
    if (curl_errno($curl)) {
        echo 'Errno'.curl_error($curl);//捕抓异常
    }
    curl_close($curl); // 关闭CURL会话
    return $tmpInfo; // 返回数据，json格式
}

//xls导出类
class cXlsExport
{
    public $title;
    public $data;
    public $filename;

    /**
     * 导出Xls
     * @name export_xls
     * @retu string
     */
    public function export_xls(){
        $title = implode("\t",$this->iconvData($this->title)) . "\n";
        $data = $this->iconvData($this->data);
        array_walk($data,function(&$val){$val = (implode("\t",$val))."\n";});
        $data = implode("",$data);

        $this->headerSet();
        echo $title.$data;
    }

    /**
     * 字符转码
     * @name iconvData
     * @param array $data 转码数据
     * @retu array
     */
    public function iconvData($data){
        array_walk($data,function(&$val){
            if(is_array($val)){
                array_walk($val,function(&$val2){$val2 = iconv('utf-8','gbk',$val2);});
            }else{
                $val = iconv('utf-8','gbk',$val);
            }
        });

        return $data;
    }

    /**
     * 设置header头
     * @name headerSet
     * @retu string
     */
    public function headerSet(){
        header("Pragma:public");
        header("Expires:0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header("Content-Disposition:attachment;filename='".$this->filename."'");
        header("Content-Type:Transfer-Encoding:binary");
    }
}

//单例数据库
class MySQL{
    private static $db;

    private function __construct(){
    }

    private function MySQL(){}

    public static function newObject($host,$user,$pwd,$dbname,$port){
        if(self::$db === null){
            self::$db = new mysqli($host, $user, $pwd, $dbname,$port);
            self::$db->set_charset('utf8');
        }
        return self::$db;
    }

    public static function ins_sql($arr,$table){
        $sql = 'INSERT '.$table.'(';
        $val = 'VALUES(';
        foreach($arr as $k => $v){
            $sql .= $k.',';
            $val .= '"'.$v.'",';
        }
        return rtrim($sql,',').') '.rtrim($val,',').')';
    }

    public static function geterror(){
        return self::$db->error;
    }
}