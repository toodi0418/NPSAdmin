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
        if(is_file('./server.log')) {
            $logfext = true;
        }
        $file = fopen('./server.log',"a+");
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
        if(!empty($configs['Memcached']['0']['use']) && $configs['Memcached']['0']['use'] == 'yes' && !empty($configs['Memcached']['1']['host']) && !empty($configs['Memcached']['2']['port']) && is_numeric($configs['Memcached']['2']['port'])) { //檢查Memcached設定是否正確
            return true;
        }else {
            return false;
        }
    } else {
        return false;
    }
}
function uuid() {
    return md5(uniqid(mt_rand(), true));
}
if(check_memcache()) { //如果Memcached有啟用就設定它
    $memcache = new memcached();
    $memcache->addServer($configs['Memcached']['1']['host'], $configs['Memcached']['2']['port']);
}
function get_config($key) {
    if(!empty($key)) {
        switch($key) {
            case 'DBNAME':
                if(!empty($configs['DB']['1']['NAME'])) {
                    return $configs['DB']['1']['NAME'];
                }else {
                    goto false;
                }
                break;
        }
    }else {
        false:
        return false;
    }
}
if(!empty($configs['DB']['0']['HOST']) && !empty($configs['DB']['1']['NAME']) && !empty($configs['DB']['2']['USER'])) { //判斷資料庫設定是否正確
    try //連線資料庫
    {
        $conn = new PDO("mysql:host=".$configs['DB']['0']['HOST'].";dbname=".$configs['DB']['1']['NAME'],$configs['DB']['2']['USER'],$configs['DB']['3']['PSWD']);
        $conn->exec("SET CHARACTER SET utf8");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch(PDOException $e) //如果資料庫發生錯誤
    {
        logm('error','MySQL',$e->getMessage()); //紀錄訊息並繼續運作
    }
}
function user_info($username,$key) { //顯示使用者資訊
    global $memcache,$conn; //引入Memcached和conn變數
    if(!empty($username) && !empty($key)) {
        if(check_memcache()) { //檢查Memcached是否開啟
            $str = $memcache->get(base64_encode("｜DB｜".get_config('DBNAME')."｜users｜".$username));
            if(!empty($str)) { //判斷是否快取
                if($str == 'Untitled') { //是，但沒有資料
                    return false; //回傳使用者不存在
                }else { //是，有資料
                    $result = $str;
                    goto showdata; //滾去showdata
                }
            }else { //沒有快取
                goto sqlgogo; //滾去sqlgogo部分
            }
        }else {
            sqlgogo:
            $sql = "SELECT * FROM `users` WHERE username = :username"; //資料庫查詢語法
            if(!empty($conn)) {
                $sth = $conn->prepare($sql);
                $sth->execute(array(':username' => $username));
                $result = $sth->fetch(PDO::FETCH_ASSOC);
            }else {
                header('Content-Type: application/json; charset=utf-8');
                die('{"status":"error","type":"system","msg":"There is a problem with the system, please contact the webmaster, code 0x01"}');
            }
            showdata:
            if(!empty($result['id'])) { //檢查資料是否存在
                $memcache->set(base64_encode("｜DB｜".get_config('DBNAME')."｜users｜".$username),$result); //加入快取
                switch($key) { //依照所需資料輸出
                    case 'id':
                        echo $result['id'];
                        break;
                    case 'uid':
                        echo $result['uid'];
                        break;
                    case 'username':
                        echo $result['username'];
                        break;
                    case 'password':
                        echo $result['password'];
                        break;
                    case 'permission':
                        echo $result['permission'];
                        break;
                    default:
                        return false;
                }
            }else {
                $memcache->set(base64_encode("｜DB｜".get_config('DBNAME')."｜users｜".$username),'Untitled'); //加入快取
                return false; //回傳使用者不存在
            }
        }
    }
}
echo user_info('toodi0418','uid');
$time = microtime(true) - $time_start;
echo "\n <br>" . $time;