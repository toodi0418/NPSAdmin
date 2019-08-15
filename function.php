<?php
if (is_file('config.php')) include_once ('config.php');
else die('The file config.php does not exist');
$configs = json_decode($config, true);
$time_start = microtime(true);
function ipadr() { //取得使用者IP
    if (!empty($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        return $myip = $_SERVER["HTTP_CF_CONNECTING_IP"];
    } else if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $myip = $_SERVER['HTTP_CLIENT_IP'];
    } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $myip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $myip = $_SERVER['REMOTE_ADDR'];
    }
}
function logm($type, $t2, $msg) { //伺服器紀錄
    if (!empty($type) && !empty($msg) && !empty($t2)) {
        if (is_file('./server.log')) {
            $logfext = true;
        }
        $file = fopen('./server.log', "a+");
        if (!empty($logfext) && $logfext = true) {
            fwrite($file, "\n" . json_encode(array("type" => $type, "t2" => $t2, "msg" => $msg, "time" => date("Y/m/d H:i:s"), "IP" => ipadr())));
        } else {
            fwrite($file, json_encode(array("type" => $type, "t2" => $t2, "msg" => $msg, "time" => date("Y/m/d H:i:s"), "IP" => ipadr())));
        }
        fclose($file);
    }
}
function check_memcache() { //檢查Memcached是否啟用
    global $configs;
    if (class_exists('memcached')) {
        if (!empty($configs['Memcached']['0']['use']) && $configs['Memcached']['0']['use'] == 'yes' && !empty($configs['Memcached']['1']['host']) && !empty($configs['Memcached']['2']['port']) && is_numeric($configs['Memcached']['2']['port'])) { //檢查Memcached設定是否正確
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}
function uuid() {
    return md5(uniqid(mt_rand(), true));
}
if (check_memcache()) { //如果Memcached有啟用就設定它
    $memcache = new memcached();
    $memcache->addServer($configs['Memcached']['1']['host'], $configs['Memcached']['2']['port']);
}
function get_config($key) { //取得網站基本設定
    if (!empty($key)) {
        switch ($key) {
            case 'DBNAME':
                if (!empty($configs['DB']['1']['NAME'])) {
                    return $configs['DB']['1']['NAME'];
                } else {
                    goto false;
                }
                break;
        }
    } else {
        false:
        return false;
    }
}
if (!empty($configs['DB']['0']['HOST']) && !empty($configs['DB']['1']['NAME']) && !empty($configs['DB']['2']['USER'])) { //判斷資料庫設定是否正確
    try
        //連線資料庫
    {
        $conn = new PDO("mysql:host=" . $configs['DB']['0']['HOST'] . ";dbname=" . $configs['DB']['1']['NAME'], $configs['DB']['2']['USER'], $configs['DB']['3']['PSWD']);
        $conn->exec("SET CHARACTER SET utf8");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch(PDOException $e) //如果資料庫發生錯誤
    {
        logm('error', 'MySQL', $e->getMessage()); //紀錄訊息並繼續運作

    }
}
function str_is_special($l1) //判斷特殊符號
{
    $l2 = "&,',\",<,>,!,%,#,$,@,=,?,/,(,),[,],{,},.,+,*,_";
    $I2 = explode(',', $l2);
    $I2[] = ",";
    foreach ($I2 as $v) {
        if (strpos($l1, $v) !== false) {
            return true;
        }
    }
    return false;
}
function user_info($username, $key) { //顯示使用者資訊
    global $memcache, $conn; //引入Memcached和conn變數
    if (!empty($username) && !empty($key)) {
        if (check_memcache()) { //檢查Memcached是否開啟
            $str = $memcache->get(base64_encode("｜DB｜" . get_config('DBNAME') . "｜users｜" . $username));
            if (!empty($str)) { //判斷是否快取
                if ($str == 'Untitled') { //是，但沒有資料
                    return false; //回傳使用者不存在

                } else { //是，有資料
                    $result = $str;
                    goto showdata; //滾去showdata

                }
            } else { //沒有快取
                goto sqlgogo; //滾去sqlgogo部分

            }
        } else {
            sqlgogo:
            $sql = "SELECT * FROM `users` WHERE username = :username"; //資料庫查詢語法
            if (!empty($conn)) {
                $sth = $conn->prepare($sql);
                $sth->execute(array(':username' => $username));
                $result = $sth->fetch(PDO::FETCH_ASSOC);
            } else {
                header('Content-Type: application/json; charset=utf-8');
                die('{"status":"error","type":"system","msg":"There is a problem with the system, please contact the webmaster, code 0x01"}');
            }
            showdata:
            if (!empty($result['id'])) { //檢查資料是否存在
                $memcache->set(base64_encode("｜DB｜" . get_config('DBNAME') . "｜users｜" . $username), $result); //加入快取
                switch ($key) { //依照所需資料輸出

                    case 'id':
                        return $result['id'];
                        break;
                    case 'uid':
                        return $result['uid'];
                        break;
                    case 'username':
                        return $result['username'];
                        break;
                    case 'password':
                        return $result['password'];
                        break;
                    case 'permission':
                        return $result['permission'];
                        break;
                    default:
                        return false;
                }
            } else {
                $memcache->set(base64_encode("｜DB｜" . get_config('DBNAME') . "｜users｜" . $username), 'Untitled'); //加入快取
                return false; //回傳使用者不存在

            }
        }
    }
}
function is_user($username) {
    if (!empty(user_info($username, 'id'))) {
        return true;
    } else {
        return false;
    }
}
function check_permission($username, $permission) {
    if (!empty($username) && !empty($permission)) {
        $str = user_info($username, 'permission');
        if ($str) {
            $arr1 = str_split($str);
            if (strlen($permission) == 66) {
                if (is_numeric($permission)) {
                    $arr2 = str_split($permission);
                    foreach ($arr1 as $key => $value) {
                        if ($value < $arr2[$key]) {
                            goto false;
                        }
                    }
                } else {
                    false:
                    return false;
                }
            } else {
                switch ($permission) {
                    case 'login':
                        if (!empty($arr1['0']) && $arr1[0] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'API':
                        if (!empty($arr1['1']) && $arr1[1] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addAPI':
                        if (!empty($arr1['2']) && $arr1[2] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageAPI':
                        if (!empty($arr1['3']) && $arr1[3] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewAPI':
                        if (!empty($arr1['4']) && $arr1[4] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyAPI':
                        if (!empty($arr1['5']) && $arr1[5] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteAPI':
                        if (!empty($arr1['6']) && $arr1[6] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addUser':
                        if (!empty($arr1['7']) && $arr1[7] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageUser':
                        if (!empty($arr1['8']) && $arr1[8] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewUser':
                        if (!empty($arr1['9']) && $arr1[9] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ChangeUserPwd':
                        if (!empty($arr1['10']) && $arr1[10] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'resetUserPwd':
                        if (!empty($arr1['11']) && $arr1[11] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addServer':
                        if (!empty($arr1['12']) && $arr1[12] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageServer':
                        if (!empty($arr1['13']) && $arr1[13] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewServer':
                        if (!empty($arr1['14']) && $arr1[14] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'viewClientPort':
                        if (!empty($arr1['15']) && $arr1[15] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewTotalClient':
                        if (!empty($arr1['16']) && $arr1[16] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewOnlineClient':
                        if (!empty($arr1['17']) && $arr1[17] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewTCPconnect':
                        if (!empty($arr1['18']) && $arr1[18] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewConfig':
                        if (!empty($arr1['19']) && $arr1[19] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'Viewsysinfo':
                        if (!empty($arr1['20']) && $arr1[20] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewTrafficInfo':
                        if (!empty($arr1['21']) && $arr1[21] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewClientList':
                        if (!empty($arr1['22']) && $arr1[22] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageClient':
                        if (!empty($arr1['23']) && $arr1[23] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addClient':
                        if (!empty($arr1['24']) && $arr1[24] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyClient':
                        if (!empty($arr1['25']) && $arr1[25] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteClient':
                        if (!empty($arr1['26']) && $arr1[26] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addHost':
                        if (!empty($arr1['27']) && $arr1[27] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageHost':
                        if (!empty($arr1['28']) && $arr1[28] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewHostList':
                        if (!empty($arr1['29']) && $arr1[29] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyHost':
                        if (!empty($arr1['30']) && $arr1[30] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteHost':
                        if (!empty($arr1['31']) && $arr1[31] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addTCPTunnelList':
                        if (!empty($arr1['32']) && $arr1[32] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageTCPTunnel':
                        if (!empty($arr1['33']) && $arr1[33] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewTCPTunnelList':
                        if (!empty($arr1['34']) && $arr1[34] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyTCPTunnel':
                        if (!empty($arr1['35']) && $arr1[35] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteTCPTunnel':
                        if (!empty($arr1['36']) && $arr1[36] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addUDPTunnel':
                        if (!empty($arr1['37']) && $arr1[37] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageUDPTunnel':
                        if (!empty($arr1['38']) && $arr1[38] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewUDPTunnelList':
                        if (!empty($arr1['39']) && $arr1[39] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyUDPTunnel':
                        if (!empty($arr1['40']) && $arr1[40] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteUDPTunnel':
                        if (!empty($arr1['41']) && $arr1[41] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addHproxy':
                        if (!empty($arr1['42']) && $arr1[42] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageHProxy':
                        if (!empty($arr1['43']) && $arr1[43] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewHproxyList':
                        if (!empty($arr1['44']) && $arr1[44] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyHproxy':
                        if (!empty($arr1['45']) && $arr1[45] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteHproxy':
                        if (!empty($arr1['46']) && $arr1[46] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addSProxy':
                        if (!empty($arr1['47']) && $arr1[47] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageSProxy':
                        if (!empty($arr1['48']) && $arr1[48] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewSProxyList':
                        if (!empty($arr1['49']) && $arr1[49] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflySProxyList':
                        if (!empty($arr1['50']) && $arr1[50] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteSProxyList':
                        if (!empty($arr1['51']) && $arr1[51] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addSEroxy':
                        if (!empty($arr1['52']) && $arr1[52] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageSEroxy':
                        if (!empty($arr1['53']) && $arr1[53] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewSEroxyList':
                        if (!empty($arr1['54']) && $arr1[54] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflySEroxyList':
                        if (!empty($arr1['55']) && $arr1[55] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteSEroxyList':
                        if (!empty($arr1['56']) && $arr1[56] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addPProxy':
                        if (!empty($arr1['57']) && $arr1[57] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManagePProxy':
                        if (!empty($arr1['58']) && $arr1[58] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewPProxyList':
                        if (!empty($arr1['59']) && $arr1[59] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyPProxyList':
                        if (!empty($arr1['60']) && $arr1[60] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeletePProxyList':
                        if (!empty($arr1['61']) && $arr1[61] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'addFProxy':
                        if (!empty($arr1['62']) && $arr1[62] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ManageFProxy':
                        if (!empty($arr1['63']) && $arr1[63] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ViewFProxyList':
                        if (!empty($arr1['64']) && $arr1[64] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'ModiflyFProxyList':
                        if (!empty($arr1['65']) && $arr1[65] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                    case 'DeleteFProxyList':
                        if (!empty($arr1['66']) && $arr1[66] == 1) {
                            return true;
                        } else {
                            return false;
                        }
                        break;
                }
            }
        } else {
            return false;
        }
    } else {
        return false;
    }
}
echo check_permission('toodi0418', '000000000000000000000000000000000000000000000000000000000000000001');
//echo user_info('toodi018','uid');
echo is_user('toodi418');
$time = microtime(true) - $time_start;
echo "\n <br>" . $time;
