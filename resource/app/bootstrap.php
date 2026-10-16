<?php
declare(strict_types=1);
const APP_ROOT = __DIR__ . '/..';
date_default_timezone_set('Asia/Shanghai');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('MINGCHI_WMS_SESSION');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
function env_config(): array {
    $file=APP_ROOT.'/config.local.php';
    if (!is_file($file)) return [];
    $c=require $file;
    return is_array($c)?$c:[];
}
function db(): PDO {
    static $pdo=null;
    if($pdo)return $pdo;
    $c=env_config();
    if(!$c) throw new RuntimeException('系统尚未安装，请先访问 /install.php');
    $pdo=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['db'].';charset=utf8mb4', $c['user'],$c['password'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::MYSQL_ATTR_INIT_COMMAND=>'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'
    ]);
    return $pdo;
}
function json_out(array $data,int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function fail(string $message,int $code=400): never {json_out(['ok'=>false,'error'=>$message],$code);}
function request_body(): array {
    $raw=file_get_contents('php://input');
    if($raw===false||strlen($raw)>2000000) fail('提交内容过大',413);
    $x=json_decode($raw,true);
    if(!is_array($x)) fail('请求内容不是有效 JSON');
    return $x;
}
function csrf(): string {if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(24));return $_SESSION['csrf'];}
function require_csrf(): void {
    if(!hash_equals(csrf(),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) fail('安全令牌已失效，请刷新页面',419);
}
function current_user(): ?array {
    $id=(int)($_SESSION['uid']??0);
    if(!$id)return null;
    $stmt=db()->prepare('SELECT id,name,email,phone,role,permissions_json,active,must_change_password FROM users WHERE id=?');
    $stmt->execute([$id]);$u=$stmt->fetch();
    if(!$u||(int)$u['active']!==1){unset($_SESSION['uid']);return null;}
    $u['permissions']=json_decode($u['permissions_json'],true)?:[];
    unset($u['permissions_json']);return $u;
}
function user_required(): array {return current_user()??(fail('登录已失效，请重新登录',401));}
function authorized(string $perm): bool {
    $u=current_user();
    if(!$u)return false;
    return $u['role']==='superadmin'||in_array($perm,$u['permissions'],true);
}
function can(string $perm): void {if(!authorized($perm)) fail('当前账号没有执行此操作的权限',403);}
function query_one(string $sql,array $args=[]): ?array {$s=db()->prepare($sql);$s->execute($args);return $s->fetch()?:null;}
function query_all(string $sql,array $args=[]): array {$s=db()->prepare($sql);$s->execute($args);return $s->fetchAll();}
function execute_sql(string $sql,array $args=[]): int {$s=db()->prepare($sql);$s->execute($args);return $s->rowCount();}
function j($value): string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);}
function unj($value,$default=[]){$r=json_decode((string)$value,true);return is_array($r)?$r:$default;}
function param($data,string $key,string $default=''): string {return trim((string)($data[$key]??$default));}
function positive($x,string $label='数值', bool $allowZero=true): float {
    if(!is_numeric($x)||!is_finite((float)$x)||($allowZero?(float)$x<0:(float)$x<=0))fail($label.'必须是'.($allowZero?'非负':'大于零').'数字');
    return round((float)$x,4);
}
function rate($x): float {$n=positive($x,'汇率',false);if($n>100000)fail('汇率超出允许范围');return $n;}
function currency(string $code): string {$s=strtoupper(trim($code));if(!in_array($s,['CNY','USD','EUR','AUD','CAD','GBP','SAR','PHP','BRL'],true))fail('不支持该币种');return $s;}
function ymd($v): string {$v=(string)$v;if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)||date('Y-m-d',strtotime($v))!==$v)fail('日期格式应为 YYYY-MM-DD');return $v;}
function intid($v): int {$i=filter_var($v,FILTER_VALIDATE_INT);if($i===false||$i<1)fail('无效的记录编号');return $i;}
function settings(string $key,$default=null){$r=query_one('SELECT value_json FROM app_settings WHERE `key`=?',[$key]);return $r?json_decode($r['value_json'],true):$default;}
function setting_set(string $key,$value): void {execute_sql('INSERT INTO app_settings(`key`,value_json) VALUES (?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json)',[$key,j($value)]);}
function audit(string $action,string $entity,$entityId=null,$payload=[]): void {
    $u=current_user();$ip=substr((string)($_SERVER['REMOTE_ADDR']??''),0,45);
    execute_sql('INSERT INTO audit_logs (user_id,action,entity,entity_id,payload_json,ip) VALUES (?,?,?,?,?,?)',[$u['id']??null,$action,$entity,(string)$entityId,j($payload),$ip]);
}
function sql_page(int $page,int $limit=50): string {return ' LIMIT '.min(max($limit,1),100).' OFFSET '.((max($page,1)-1)*min(max($limit,1),100));}
function ref_options(string $group): array {return query_all('SELECT id,value,position,active FROM reference_values WHERE group_code=? ORDER BY position,id',[$group]);}
function keycode(string $prefix,string $table): string {
    // ID based code generated only after INSERT; generated externally from LAST_INSERT_ID.
    return $prefix.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
}
function safe_custom(string $module,array $input): array {
    $fields=query_all('SELECT field_key,field_type,options_json FROM field_definitions WHERE module=? AND builtin=0 AND active=1',[$module]);
    $out=[];
    foreach($fields as $f){$key=$f['field_key'];if(!array_key_exists($key,$input))continue;
        $val=$input[$key];$type=$f['field_type'];
        if($type==='number')$val=positive($val,$key);
        elseif($type==='checkbox')$val=(bool)$val;
        elseif($type==='select'){$val=trim((string)$val);$choices=unj($f['options_json']);if($val!==''&&$choices&&!in_array($val,$choices,true))fail('自定义字段选项不合法：'.$key);}
        elseif($type==='date'){$val=$val?ymd($val):'';}
        else $val=mb_substr_safe(trim((string)$val),1500);
        $out[$key]=$val;
    }
    return $out;
}
function mb_substr_safe(string $v,int $n): string {return function_exists('mb_substr')?mb_substr($v,0,$n):substr($v,0,$n*3);}
function check_custom_required(string $module,array $custom): void {
    foreach(query_all('SELECT field_key,label FROM field_definitions WHERE module=? AND builtin=0 AND active=1 AND visible=1 AND required_field=1',[$module]) as $f){
        $v=$custom[$f['field_key']]??null;
        if($v===null||$v==='')fail('必填字段未填写：'.$f['label']);
    }
}
function company_profit(float $revenue,float $costs,float $expenses): array {return ['revenue_cny'=>round($revenue,2),'direct_cost_cny'=>round($costs,2),'order_profit_cny'=>round($revenue-$costs,2),'operating_expense_cny'=>round($expenses,2),'company_profit_cny'=>round($revenue-$costs-$expenses,2)];}
