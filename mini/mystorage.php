<?php
function api_install($my){
$table = $my->cfg['dbTable'];
$stmt = $my->query('SELECT 1 FROM '.$table.' LIMIT 1');
if($stmt && $stmt->columnCount() > 0)return 1;

$s = "CREATE TABLE `".$table."` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`name` text NOT NULL,
`value` text NOT NULL,
`social` tinyint(4) NOT NULL DEFAULT '0',
`userid` bigint(20) NOT NULL,
`appid` bigint(20) NOT NULL,
`create_time` int(11) NOT NULL DEFAULT '0',
`update_time` int(11) NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
KEY `id` (`id`),
KEY `userid` (`userid`),
KEY `appid` (`appid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$my->query($s);
if($my->dberror)return 0;
$stmt = $my->query('SELECT 1 FROM '.$table.' LIMIT 1');
if($my->dberror)return 0;
if($stmt->columnCount() > 0)return 1;
return 0;
}

function api_execute($my){
$limit = $my->cfg['executeLimit'];
$data = $my->data;
$items = array();
$arr = ($data && isset($data['code']) && is_array($data['code'])) ? $data['code'] : null;
if($arr){
$nums = count($arr);

if($nums > $limit)return $my->error('api', array('message' => 'execute limit '.$limit));

for($i = 0; $i < $nums; $i++){
$item = $arr[$i];
$m = ($item && isset($item['method'])) ? $item['method'] : '';
$res = null;
if($m && isset($my->methodsList[$m])){

if($m == 'execute'){
$items[] = $my->error('api', array('message' => 'method "'.$m.'" not allowed'));
continue;
}

if(strpos($m, 'admin.') === 0){
if(!$my->isAdmin()){
$items[] = $my->error('api', array('message' => 'access denied'));
continue;
}
}
	
$cb = $my->methodsList[$m];
$lastData = $my->data;
$my->data = $item;
$res = $cb($my);
$my->data = $lastData;
}else{
$res = $my->error('api', array('message' => 'method "'.$m.'" not found'));
}
$items[] = $res;
}
}

return $items;
}

function api_storage_set($my){
$data = $my->data;
$limit = $my->cfg['maxCreateVars'];
$name = isset($data['key']) ? $data['key'] : '';
$value = isset($data['value']) ? $data['value'] : '';
if(!$name || !$my->isValidKey($name) || strlen($name) > $my->cfg['maxVarNameSize']){
return $my->error('api', array('message' => 'key "'.$name.'" not valid'));
}
$status = $my->updateVarValue($my->user, $name, $value);
if($status == 2)return $my->error('api', array('message' => 'limit '.$limit.' keys'));
return $status;
}

function api_storage_get($my){
$items = array();
$obj = array();
$authData = $my->user;
$data = $my->data;
$table = $my->cfg['dbTable'];
$maxVarNameSize = $my->cfg['maxVarNameSize'];
$keys = null;

if(isset($data['keys'])){
if(is_string($data['keys']))$keys = explode(',', $data['keys']);
else if(is_array($data['keys']))$keys = $data['keys'];
if($keys){
for($i = 0; $i < count($keys); $i++){
if(!$my->isValidKey($keys[$i]) || strlen($keys[$i]) > $maxVarNameSize){
return $my->error('api', array('message' => 'key "'.$keys[$i].'" not valid'));
}
}
}
}else if(isset($data['key'])){
$name = $data['key'];
if(!$my->isValidKey($name) || strlen($name) > $maxVarNameSize)return $my->error('api', array('message' => 'key "'.$name.'" not valid'));
$keys = array($name);
}

if($keys && count($keys) > 0){
$s = 'userid=? AND name IN('.implode(',', array_fill(0, count($keys), '?')).')';

$stmt = $my->query('SELECT * FROM '.$table.' WHERE '.$s, array_merge(array($authData['userid']), $keys));
if($my->dberror)return $items;

while($row = $stmt->fetch()){
if($row['social'] == $authData['socialid'] && $row['appid'] == $authData['appid']){
$obj[$row['name']] = $row['value'];
}
}

for($i = 0; $i < count($keys); $i++){
$key = $keys[$i];
$value = (isset($obj[$key]) && !empty($obj[$key])) ? $obj[$key] : '';
$items[] = array('key' => $key, 'value' => $value);
}

}

return $items;
}

function api_storage_getKeys($my){
$items = array();
$authData = $my->user;
$data = $my->data;
$table = $my->cfg['dbTable'];
$limit = $my->cfg['maxCreateVars'];
$offset = isset($data['offset']) ? intval($data['offset']) : 0;
$nums = isset($data['count']) ? intval($data['count']) : $limit;
if($offset < 0)$offset = 0;
if($nums < 0)$nums = 0;
if($nums > $limit)$nums = $limit;
$s = 'social=? AND userid=? AND appid=? AND value!=? LIMIT '.$offset;
if($nums > 0)$s .= ','.$nums;
$stmt = $my->query('SELECT id, name FROM '.$table.' WHERE '.$s, array($authData['socialid'], $authData['userid'], $authData['appid'], ''));
if(!$my->dberror){
while($row = $stmt->fetch()){
$items[] = $row['name'];
}
}
return $items;
}


function api_storage_clear($my){
$user = $my->user;
$table = $my->cfg['dbTable'];
$stmt = $my->query('DELETE FROM '.$table.' WHERE social=? AND userid=? AND appid=?', array($user['socialid'], $user['userid'], $user['appid']));
if($my->dberror)return 0;
return 1;
}

function api_admin_storage_getUsers($my){
$arr = array();
$obj = array();
$table = $my->cfg['dbTable'];
$authData = $my->user;
$stmt = $my->query('SELECT social, userid FROM '.$table.' WHERE social=? AND appid=?', array($authData['socialid'], $authData['appid']));
if($my->dberror)return $arr;
while($row = $stmt->fetch()){
$u = $row['userid'];
if(!isset($obj[$u])){
$obj[$u] = 1;
$arr[] = $u;
}
}
return $arr;
}

class MyStorage{

private $sql;

public $appsList;
public $methodsList;
public $socialsLocalId;
public $adminsList;

public $data;
public $user;
public $cfg;

public function __construct($d, $cfg){
$data = null;
$s = file_get_contents('php://input');
if($s && $s[0] == '{'){
$dataJson = json_decode($s, 1);
if($dataJson)$data = $dataJson;
}

$this->user = null;
$this->sql = null;
$this->data = $data;
$this->cfg = $cfg;
$this->appsList = array();
$this->methodsList = array();
$this->adminsList = array();
$this->socialsLocalId = array('vk' => 1, 'ok' => 2);

$this->addMethod('install', 'api_install');
$this->addMethod('execute', 'api_execute');
$this->addMethod('storage.set', 'api_storage_set');
$this->addMethod('storage.get', 'api_storage_get');
$this->addMethod('storage.getKeys', 'api_storage_getKeys');
$this->addMethod('storage.clear', 'api_storage_clear');
$this->addMethod('admin.storageGetUsers', 'api_admin_storage_getUsers');
}

public function connectDB($cfg){
try{
$d = new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['dbname'].';charset='.$cfg['charset'], $cfg['user'], $cfg['pass']);
$d->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$d->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$this->sql = $d;
return 1;
}catch(Exception $e){
}
return 0;
}

public function query($s, $arr = null){
if($this->sql){
if(!$arr)$arr = array();
$this->dberror = null;
try{
$stmt = $this->sql->prepare($s);
$stmt->execute($arr);
return $stmt;
}catch(Exception $e){
$this->dberror = $e;
}
}
return null;
}

public function addMethod($method, $cb){
$this->methodsList[$method] = $cb;
}

public function addAdmin($s){
$social = substr($s, 0, 2);
$userid = substr($s, 2);
$this->adminsList[] = array('social' => $social, 'userid' => $userid);
}

public function isAdmin(){
$user = $this->user;
if($user){
for($i = 0; $i < count($this->adminsList); $i++){
$el = $this->adminsList[$i];
if($el['userid'] === $user['userid'] && $el['social'] === $user['social'])return 1;
}
}
return 0;
}

public function addApp($s, $secret){
$social = substr($s, 0, 2);
$appid = substr($s, 2);
$socialid = isset($this->socialsLocalId[$social]) ? $this->socialsLocalId[$social] : 0;
$this->appsList[] = array('social' => $social, 'socialid' => $socialid, 'appid' => $appid, 'secret' => $secret);
}

public function findApp($social, $appid){
if(!$appid)return null;
for($i = 0; $i < count($this->appsList); $i++){
$item = $this->appsList[$i];
if($item['appid'] == $appid && $item['social'] == $social)return $item;
}
return null;
}

public function isValidKey($key){
$pat = '/^[a-zA-Z_\-0-9]+$/';
if(preg_match($pat, $key))return 1;
return 0;
}

public function updateVarValue($user, $name, $value){
$table = $this->cfg['dbTable'];
$limit = $this->cfg['maxCreateVars'];
$args = array($user['socialid'], $user['userid'], $user['appid']);
$ts = time();
$stmt = $this->query('UPDATE '.$table.' SET value=?, update_time=? WHERE name=? AND social=? AND userid=? AND appid=?', array_merge(array($value, $ts, $name), $args));
if($this->dberror)return 0;
if($stmt->rowCount() > 0)return 1;

$stmt = $this->query('SELECT COUNT(*) FROM '.$table.' WHERE social=? AND userid=? AND appid=?', $args);
if($this->dberror)return 0;
$allCount = $stmt->fetchColumn();
if($allCount >= $limit)return 2;
if(!$value)return 1;

$stmt = $this->query('INSERT INTO '.$table.' (name, value, social, userid, appid, create_time, update_time) VALUES (?,?,?,?,?,?,?)', array_merge(array($name, $value), $args, array($ts, $ts)));
if($this->dberror)return 0;
if($stmt->rowCount() > 0)return 1;
return 0;
}

public function sendJSON($o){
echo json_encode($o);
}

public function send($o){
echo $this->sendJSON(array('response' => $o));
}

public function error($type, $o){
return array('error' => array('type' => $type, 'data' => $o));
}

public function auth($s){
if(!$s)return null;
if($s[0] != '?')$s = '?'.$s;
$query_params = array();
parse_str(parse_url($s, PHP_URL_QUERY), $query_params);

// для старой авторизации vk по viewer_id и auth_key
if(isset($query_params['api_id']) && isset($query_params['viewer_id']) && isset($query_params['auth_key'])){
$appInfo = $this->findApp('vk', $query_params['api_id']);
if($appInfo && $query_params['auth_key'] === md5(implode('_', array($query_params['api_id'], $query_params['viewer_id'], $appInfo['secret'])))){
return array('social' => 'vk', 'socialid' => $appInfo['socialid'], 'appid' => $query_params['api_id'], 'userid' => $query_params['viewer_id']);
}
return null;
}

$isSocialOK = isset($query_params['vk_ok_app_id']);
$social = $isSocialOK ? 'ok' : 'vk';
$prefixField = $isSocialOK ? 'vk_ok' : 'vk';
$appid = isset($query_params[$prefixField.'_app_id']) ? $query_params[$prefixField.'_app_id'] : null;
$appInfo = $this->findApp($social, $appid);

if(!isset($query_params['sign']) || !$appInfo)return null;

$sign_params = array();
foreach($query_params as $name => $value){
if(strpos($name, 'vk_') === 0)$sign_params[$name] = $value;
}
ksort($sign_params);
$sign = rtrim(strtr(base64_encode(hash_hmac('sha256', http_build_query($sign_params), $appInfo['secret'], 1)), '+/', '-_'), '=');
if($sign !== $query_params['sign'])return null;
$userid = $query_params[$prefixField.'_user_id'];
return array('social' => $social, 'socialid' => $appInfo['socialid'], 'appid' => $appid, 'userid' => $userid);
}

public function run(){
header("Content-Type: application/json");
$data = $this->data;
$method = ($data && isset($data['method'])) ? $data['method'] : '';
$authStr = ($data && isset($data['q'])) ? $data['q'] : null;
$table = $this->cfg['dbTable'];

if(!$this->connectDB($this->cfg['db'])){
$this->sendJSON($this->error('db', array('message' => 'connect error')));
exit;
}

$this->user = $this->auth($authStr);
if(!$this->user){
$this->sendJSON($this->error('api', array('message' => 'auth error')));
exit;
}

if($method && isset($this->methodsList[$method])){
$cb = $this->methodsList[$method];
$res = $cb($this);
if($res && is_array($res) && isset($res['error'])){
$this->sendJSON($res);
}else{
$this->send($res);
}
}else{
$this->sendJSON($this->error('api', array('message' => 'method "'.$method.'" not found')));
}

}

}
?>