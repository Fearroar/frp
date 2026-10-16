<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(!current_user()){http_response_code(401);exit('请先登录');}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1){http_response_code(404);exit;}
$r=query_one('SELECT stored_name,mime FROM uploads WHERE id=?',[$id]);if(!$r){http_response_code(404);exit;}
$p=APP_ROOT.'/storage/uploads/'.basename($r['stored_name']);if(!is_file($p)){http_response_code(404);exit;}
header('Content-Type: '.$r['mime']);header('Content-Disposition: inline');header('Cache-Control: private, max-age=1800');
header('Content-Length: '.filesize($p));readfile($p);
