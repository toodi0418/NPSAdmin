<?php
if (is_file('config.php')) include_once ('config.php');
else die('The file config.php does not exist');
$configs = json_decode($config, true);
$time_start = microtime(true);
function ipadr() { //取得使用者IP
    if(!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        return $myip = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    else if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        return $myip = $_SERVER['HTTP_CLIENT_IP'];
    }else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        return $myip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else{
        return $myip= $_SERVER['REMOTE_ADDR'];
    }
}
function logm($type,$t2,$msg) { //伺服器紀錄
    if(!empty($type) && !empty($msg) && !empty($t2)) {
        if(is_file($_SERVER['DOCUMENT_ROOT'].'/server.log')) {
            $logfext = true;
        }
        $file = fopen($_SERVER['DOCUMENT_ROOT'].'/server.log',"a+");
        if(!empty($logfext) && $logfext = true) {
            fwrite($file,"\n".json_encode(array("type" => $type , "t2" => $t2 , "msg" => $msg , "time" => date("Y/m/d H:i:s") , "IP" => ipadr())));
        }else {
            fwrite($file,json_encode(array("type" => $type , "t2" => $t2 , "msg" => $msg , "time" => date("Y/m/d H:i:s") , "IP" => ipadr())));
        }
        fclose($file);
    }
}
function check_memcache() { //檢查Memcached是否啟用
    global $configs;
    if (class_exists('memcached')) {
        if(!empty($configs['Memcached']['0']['use']) && $configs['Memcached']['0']['use'] == 'yes' && !empty($configs['Memcached']['0']['host']) && !empty($configs['Memcached']['0']['port']) && is_numeric($configs['Memcached']['0']['port'])) {
            return true;
        }else {
            return false;
        }
    } else {
        return false;
    }
}
if(check_memcache()) { //如果Memcached有啟用就設定它
    $memcache = new memcached();
    $memcache->addServer($configs['Memcached']['0']['host'], $configs['Memcached']['0']['port']);
}
if(!empty($configs['DB']['0']['HOST']) && !empty($configs['DB']['0']['NAME']) && !empty($configs['DB']['0']['USER'])) { //判斷資料庫設定是否正確
    try //連線資料庫
    {
        $conn = new PDO("mysql:host=".$configs['DB']['0']['HOST'].";dbname=".$configs['DB']['0']['NAME'],$configs['DB']['0']['USER'],$configs['DB']['0']['PSWD']);
        $conn->exec("SET CHARACTER SET utf8");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected Successfully";
    }
    catch(PDOException $e) //如果資料庫發生錯誤
    {
        logm('error','MySQL',$e->getMessage()); //紀錄訊息並繼續運作
    }
}
$time = microtime(true) - $time_start;
echo "\n <br>" . $time;
