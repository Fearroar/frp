<?php
require __DIR__.'/../app/bootstrap.php';
if(!is_file(APP_ROOT.'/storage/.installed')){header('Location: /install.php');exit;}
?><!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>明驰 FRP 库存管理</title><link rel="stylesheet" href="/assets/style.css?v=1"></head><body><div id="root"><div class="loading-screen">正在加载库存管理系统…</div></div><script src="/assets/app.js?v=1301" defer></script></body></html>
