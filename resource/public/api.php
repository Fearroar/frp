<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(!is_file(APP_ROOT.'/storage/.installed'))fail('请先完成系统安装',503);
require APP_ROOT.'/app/actions.php';
try {
    $action=(string)($_GET['action']??'');
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if($action==='session'&&$method==='GET'){
        $user=current_user();json_out(['ok'=>true,'authenticated'=>(bool)$user,'csrf'=>csrf(),'user'=>$user]);
    }
    if($action==='login'&&$method==='POST'){
        require_csrf();$v=request_body();$login=trim((string)($v['login']??''));$password=(string)($v['password']??'');
        $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$key=hash('sha256',$ip.'|'.strtolower($login));
        $attempt=query_one('SELECT COUNT(*) AS c FROM login_attempts WHERE address_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[$key]);
        if((int)($attempt['c']??0)>=8)fail('登录尝试过多，请15分钟后再试',429);
        $u=query_one('SELECT * FROM users WHERE (email=? OR phone=?) AND active=1 LIMIT 1',[$login,$login]);
        if(!$u||!password_verify($password,$u['password_hash'])){
            execute_sql('INSERT INTO login_attempts(address_hash) VALUES(?)',[$key]);
            fail('账号或密码错误',401);
        }
        execute_sql('DELETE FROM login_attempts WHERE address_hash=?',[$key]);
        session_regenerate_id(true);$_SESSION['uid']=(int)$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(24));
        execute_sql('UPDATE users SET last_login_at=NOW() WHERE id=?',[$u['id']]);audit('login','users',$u['id']);
        json_out(['ok'=>true,'csrf'=>csrf(),'user'=>current_user()]);
    }
    $user=user_required();
    if($method==='GET'){
        switch($action){
            case 'bootstrap':json_out(app_bootstrap());
            case 'list':$entity=(string)($_GET['entity']??'');json_out(list_rows($entity,$_GET));
            case 'detail':json_out(get_detail((string)($_GET['entity']??''),intid($_GET['id']??0)));
            case 'report':json_out(reports($_GET));
            case 'export':download_entity_export((string)($_GET['entity']??''),$_GET);
            case 'references':can('settings.manage');json_out(['ok'=>true,'rows'=>query_all('SELECT * FROM reference_values ORDER BY group_code,position,id')]);
            default:fail('未知读取接口',404);
        }
    }
    if($method!=='POST')fail('请求方式不允许',405);
    require_csrf();
    if($action==='upload')json_out(upload_media());
    $v=request_body();
    if($user['must_change_password']&&$action!=='password'&&$action!=='logout')fail('首次登录请先修改密码',403);
    switch($action){
        case 'logout':audit('logout','users',$user['id']);$_SESSION=[];session_regenerate_id(true);json_out(['ok'=>true]);
        case 'password':json_out(change_password($v));
        case 'save':json_out(save_simple(param($v,'entity'),$v['record']??[]));
        case 'product_archive':json_out(set_product_active(intid($v['id']??0),false));
        case 'product_restore':json_out(set_product_active(intid($v['id']??0),true));
        case 'entity_archive':json_out(set_entity_active(param($v,'entity'),intid($v['id']??0),false));
        case 'entity_restore':json_out(set_entity_active(param($v,'entity'),intid($v['id']??0),true));
        case 'movement_reverse':json_out(reverse_stock_movement(intid($v['id']??0)));
        case 'move':json_out(stock_move($v));
        case 'reserve':json_out(reserve_stock($v));
        case 'transfer':json_out(transfer_stock($v));
        case 'order_line_add':json_out(add_order_line($v));
        case 'order_line_delete':json_out(remove_order_line(intid($v['id']??0)));
        case 'cost_add':json_out(save_cost($v));
        case 'cost_delete':json_out(delete_cost(intid($v['id']??0)));
        case 'receipt_add':json_out(add_receipt($v));
        case 'receipt_delete':json_out(remove_receipt(intid($v['id']??0)));
        case 'quote_calc':json_out(['ok'=>true,'result'=>quote_calculate($v)]);
        case 'quote_save':json_out(save_quote($v));
        case 'user_save':json_out(save_user($v));
        case 'field_save':json_out(save_field($v));
        case 'field_disable':json_out(disable_field($v));
        case 'sort':json_out(sort_settings($v));
        case 'brand_save':json_out(save_brand($v));
        case 'finance_save':json_out(save_finance_config($v));
        case 'ref_save':json_out(save_ref($v));
        case 'import_customers':json_out(import_customers($v));
        default:fail('未知写入接口',404);
    }
}catch(PDOException $e){
    error_log('WMS PDO ERROR: '.$e->getMessage());
    if($e->getCode()==='23000')fail('保存失败：编号重复、引用不存在或关联数据冲突，请检查填写内容',409);
    fail('数据库操作失败，请联系管理员检查日志',500);
}catch(Throwable $e){
    if($e instanceof RuntimeException||$e instanceof InvalidArgumentException)fail($e->getMessage());
    error_log('WMS ERROR: '.$e->getMessage());fail('服务器发生错误，请联系管理员检查日志',500);
}
