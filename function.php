<?php
if (is_file('config.php')) include_once ('config.php');
else die('The file config.php does not exist');
$configs = json_decode($config, true);
$time_start = microtime(true);
function check_memcache() {
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
if(check_memcache()) {
    $memcache = new memcached();
    $memcache->addServer($configs['Memcached']['0']['host'], $configs['Memcached']['0']['port']);
}
$time = microtime(true) - $time_start;
echo "\n <br>" . $time;
