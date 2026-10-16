<?php
declare(strict_types=1);
const ENTITY_PERMS=[
 'products'=>['products.view','products.edit'], 'lots'=>['lots.view','lots.edit'],
 'customers'=>['customers.view','customers.edit'], 'leads'=>['leads.view','leads.edit'],
 'orders'=>['orders.view','orders.edit'],'expenses'=>['finance.view','finance.edit'],
 'parties'=>['lots.view','lots.edit'],'warehouses'=>['lots.view','lots.edit'],
 'movements'=>['movements.view','movements.create'], 'quotes'=>['quotes.use','quotes.use'],
 'users'=>['users.manage','users.manage'],'audit'=>['settings.manage','settings.manage']
];
const ALL_PERMISSIONS=['dashboard.view','products.view','products.edit','lots.view','lots.edit',
'movements.view','movements.create','customers.view','customers.edit','leads.view','leads.edit',
'orders.view','orders.edit','finance.view','finance.edit','quotes.use','users.manage','settings.manage','export.data'];
function permission_for(string $module,bool $write=false): void {
    if(!isset(ENTITY_PERMS[$module]))fail('无效模块');
    can(ENTITY_PERMS[$module][$write?1:0]);
}
function app_bootstrap(): array {
    $u=user_required();
    $refs=[];
    foreach(query_all('SELECT group_code,value FROM reference_values WHERE active=1 ORDER BY position,id') as $r)$refs[$r['group_code']][]=$r['value'];
    $brand=settings('brand',[]);$finance=settings('finance',[]);
    return [
      'ok'=>true,'user'=>$u,'csrf'=>csrf(),
      'permissions'=>ALL_PERMISSIONS,'brand'=>$brand,'finance'=>$finance,
      'nav'=>query_all('SELECT module,label,position,visible FROM menu_settings ORDER BY position,module'),
      'fields'=>query_all('SELECT module,field_key,label,field_type,builtin,protected,visible,list_visible,required_field,position,options_json,placeholder,active FROM field_definitions ORDER BY module,position,id'),
      'refs'=>$refs, 'dashboard_cards'=>settings('dashboard_cards',[]),'settings_sections'=>settings('settings_sections',[]),
      'counts'=>can_view_counts()
    ];
}
function can_view_counts(): array {
    $counts=[];
    foreach(['products','stock_lots','customers','leads','sales_orders'] as $t){$where=$t==='products'?' WHERE active=1':'';$v=query_one("SELECT COUNT(*) AS c FROM $t$where");$counts[$t]=(int)($v['c']??0);}
    return $counts;
}
function list_rows(string $entity,array $input): array {
    permission_for($entity);
    $page=max(1,(int)($input['page']??1));$limit=min(100,max(10,(int)($input['limit']??50)));
    $q=trim((string)($input['q']??''));
    $map=[
      'products'=>['products p','p.*','p.name,p.sku,p.spec,p.color,p.material,p.category'],
      'lots'=>['stock_lots l JOIN products p ON p.id=l.product_id JOIN parties party ON party.id=l.owner_party_id JOIN warehouses w ON w.id=l.warehouse_id','l.*, p.name AS product_name,p.sku AS sku,p.unit AS unit,party.name AS owner_name,w.name AS warehouse_name','p.name,p.sku,l.lot_code,l.grade,party.name,w.name'],
      'customers'=>['customers c LEFT JOIN users u ON u.id=c.owner_user_id','c.*,u.name AS owner_name','c.name,c.customer_code,c.country,c.email,c.contact,c.source'],
      'leads'=>['leads l JOIN customers c ON c.id=l.customer_id','l.*,c.name AS customer_name','l.title,l.lead_code,c.name,l.source,l.status'],
      'orders'=>['sales_orders o JOIN customers c ON c.id=o.customer_id','o.*,c.name AS customer_name,(SELECT COALESCE(SUM(ol.qty*ol.unit_cost_cny),0) FROM order_lines ol WHERE ol.order_id=o.id) AS material_cost_cny,(SELECT COALESCE(SUM(ce.amount_cny),0) FROM cost_entries ce WHERE ce.order_id=o.id) AS extra_cost_cny,(SELECT COALESCE(SUM(r.amount_cny),0) FROM receipts r WHERE r.order_id=o.id) AS received_cny','o.order_code,c.name,o.status'],
      'expenses'=>['cost_entries e LEFT JOIN users u ON u.id=e.created_by','e.*,u.name AS creator_name','e.category,e.note'],
      'parties'=>['parties p','p.*','p.name,p.kind'],
      'warehouses'=>['warehouses w LEFT JOIN parties p ON p.id=w.party_id','w.*,p.name AS party_name','w.name,w.location,p.name'],
      'movements'=>['stock_movements m JOIN stock_lots l ON l.id=m.lot_id JOIN products p ON p.id=l.product_id LEFT JOIN users u ON u.id=m.created_by LEFT JOIN sales_orders o ON o.id=m.order_id','m.*,(SELECT COUNT(*) FROM stock_movements rev WHERE rev.reversed_movement_id=m.id) AS is_reversed,l.lot_code,p.name AS product_name,p.unit,u.name AS creator_name,o.order_code','p.name,l.lot_code,m.remark,o.order_code'],
      'quotes'=>['quotes q LEFT JOIN products p ON p.id=q.product_id','q.*,p.name AS product_name','q.quote_code,p.name,q.term'],
      'users'=>['users u','u.id,u.name,u.email,u.phone,u.role,u.permissions_json,u.active,u.must_change_password,u.last_login_at,u.created_at','u.name,u.email,u.phone'],
      'audit'=>['audit_logs a LEFT JOIN users u ON u.id=a.user_id','a.*,u.name AS actor_name','a.action,a.entity,a.entity_id']
    ];
    [$from,$columns,$searchColumns]=$map[$entity];
    $where=[];$params=[];
    if($entity==='expenses')$where[]='e.order_id IS NULL';
    if($entity==='products')$where[]='p.active='.(($input['archived']??'')==='1'?'0':'1');
    if($entity==='lots')$where[]='l.active='.(($input['archived']??'')==='1'?'0':'1');
    if($entity==='customers')$where[]='c.active='.(($input['archived']??'')==='1'?'0':'1');
    if($entity==='leads')$where[]='l.active='.(($input['archived']??'')==='1'?'0':'1');
    elseif(in_array($entity,['parties','warehouses'],true))$where[]=explode(' ',$from)[1].'.active=1';
    if($q!==''){
        $cols=explode(',',$searchColumns);$where[]='('.implode(' OR ',array_map(fn($s)=>trim($s).' LIKE ?', $cols)).')';
        foreach($cols as $_)$params[]='%'.$q.'%';
    }
    if($entity==='expenses'&&!empty($input['start'])){$where[]='e.cost_date>=?';$params[]=ymd($input['start']);}
    if($entity==='expenses'&&!empty($input['end'])){$where[]='e.cost_date<=?';$params[]=ymd($input['end']);}
    if($entity==='customers'&&!empty($input['tag'])){$where[]='c.tags_json LIKE ?';$params[]='%"'.str_replace(['%','_'],['\\%','\\_'],(string)$input['tag']).'"%';}
    if($entity==='lots'&&!empty($input['warehouse_id'])){$where[]='l.warehouse_id=?';$params[]=intid($input['warehouse_id']);}
    if($entity==='lots'&&!empty($input['owner_party_id'])){$where[]='l.owner_party_id=?';$params[]=intid($input['owner_party_id']);}
    if($entity==='lots'&&!empty($input['grade'])){$where[]='l.grade=?';$params[]=(string)$input['grade'];}
    $condition=$where?' WHERE '.implode(' AND ',$where):'';
    $total=(int)(query_one('SELECT COUNT(*) AS total FROM '.$from.$condition,$params)['total']??0);
    $sort=['movements'=>'m.created_at DESC,m.id DESC','expenses'=>'e.cost_date DESC,e.id DESC','orders'=>'o.order_date DESC,o.id DESC','audit'=>'a.id DESC','quotes'=>'q.id DESC','users'=>'u.id DESC'];
    $order=$sort[$entity]??'1 DESC';
    $rows=query_all('SELECT '.$columns.' FROM '.$from.$condition.' ORDER BY '.$order.sql_page($page,$limit),$params);
    foreach($rows as &$row){
        foreach(['custom_json','tags_json','permissions_json','fees_json'] as $f)if(isset($row[$f]))$row[$f]=unj($row[$f]);
        if($entity==='lots'){$row['available']=round((float)$row['quantity']-(float)$row['reserved'],3);if(!authorized('finance.view'))unset($row['unit_cost_cny']);}
        if($entity==='products'&&!authorized('finance.view'))unset($row['reference_price_cny']);
        if($entity==='leads'){
            $row['expected_cny']=round((float)$row['expected_amount']*(float)$row['fx_to_cny'],2);
            $row['expected_profit_cny']=round($row['expected_cny']-(float)$row['estimated_cost_cny'],2);
            $cancel=settings('finance',[])['order_cancel_status']??'已取消';
            $cv=query_one("SELECT COALESCE(SUM(amount*fx_to_cny),0) AS v FROM sales_orders WHERE lead_id=? AND status<>?",[$row['id'],$cancel]);
            $row['converted_cny']=round((float)($cv['v']??0),2);
            if(!authorized('finance.view'))unset($row['estimated_cost_cny'],$row['expected_profit_cny']);
        }
        if($entity==='orders'){
            $row['revenue_cny']=round((float)$row['amount']*(float)$row['fx_to_cny'],2);
            $row['profit_cny']=round($row['revenue_cny']-(float)$row['material_cost_cny']-(float)$row['extra_cost_cny'],2);
            if(!authorized('finance.view'))unset($row['material_cost_cny'],$row['extra_cost_cny'],$row['profit_cny']);
        }
    }
    unset($row);
    return ['ok'=>true,'rows'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit];
}
function lookup_fk(string $table,int $id): void {
    $activeTables=['products','stock_lots','customers','leads','parties','warehouses'];
    $where=in_array($table,$activeTables,true)?' AND active=1':'';
    if(!query_one("SELECT id FROM $table WHERE id=?".$where,[$id]))fail('关联记录不存在或已归档：'.$table);
}
function save_simple(string $entity,array $v): array {
    permission_for($entity,true);
    $id=!empty($v['id'])?intid($v['id']):null;
    $table=['products'=>'products','customers'=>'customers','leads'=>'leads','orders'=>'sales_orders','lots'=>'stock_lots','parties'=>'parties','warehouses'=>'warehouses'][$entity]??null;
    if(!$table)fail('不支持保存该模块');
    $old=$id?query_one("SELECT * FROM $table WHERE id=?",[$id]):null;
    if($id&&!$old)fail('记录不存在',404);
    if($id&&in_array($entity,['lots','customers','leads'],true)&&(int)$old['active']!==1)fail('该记录已归档，请先恢复后编辑');
    $cols=[];$values=[];
    $add=function(string $k,$value)use(&$cols,&$values){$cols[]=$k;$values[]=$value;};
    switch($entity){
      case 'products':
        $name=param($v,'name');if(!$name)fail('产品名称不能为空');
        $add('name',$name);$add('spec',param($v,'spec'));$add('color',param($v,'color'));$add('material',param($v,'material'));
        $add('category',param($v,'category'));$add('unit',param($v,'unit','件'));$add('description',param($v,'description'));
        $add('safety_stock',positive($v['safety_stock']??0,'安全库存阈值'));
        if(authorized('finance.edit'))$add('reference_price_cny',positive($v['reference_price_cny']??0,'参考价格'));
        $add('photo_id',!empty($v['photo_id'])?intid($v['photo_id']):null);
        if(!$id)$add('sku',keycode('MC','products'));
        break;
      case 'lots':
        $pid=intid($v['product_id']??0);$owner=intid($v['owner_party_id']??0);$warehouse=intid($v['warehouse_id']??0);
        lookup_fk('products',$pid);lookup_fk('parties',$owner);lookup_fk('warehouses',$warehouse);
        $add('product_id',$pid);$add('owner_party_id',$owner);$add('warehouse_id',$warehouse);
        $lot=param($v,'lot_code');if(!$lot)$lot=keycode('LOT','stock_lots');$add('lot_code',$lot);
        $add('grade',param($v,'grade','A级'));$add('location',param($v,'location'));
        if(authorized('finance.edit'))$add('unit_cost_cny',positive($v['unit_cost_cny']??($old['unit_cost_cny']??0),'批次成本'));
        $add('photo_id',!empty($v['photo_id'])?intid($v['photo_id']):null);
        // quantity and reserved can only change via locked stock transactions.
        if($id&&((float)$old['quantity']>0||(float)$old['reserved']>0)){
            foreach(['product_id','owner_party_id','warehouse_id'] as $immut)if((string)$old[$immut]!== (string)$v[$immut]) fail('已有库存的批次不能直接修改产品、货权或仓库，请通过调拨/建立新批次处理');
        }
        break;
      case 'customers':
        $name=param($v,'name');if(!$name)fail('客户名称不能为空');$add('name',$name);
        foreach(['country','contact','email','phone','source'] as $k)$add($k,param($v,$k));
        $add('owner_user_id',!empty($v['owner_user_id'])?intid($v['owner_user_id']):null);
        $tags=$v['tags_json']??[];if(!is_array($tags))fail('客户标签格式错误');
        $add('tags_json',j(array_values(array_unique(array_map('strval',array_slice($tags,0,30))))));
        if(!$id)$add('customer_code',keycode('CUS','customers'));
        break;
      case 'leads':
        $cid=intid($v['customer_id']??0);lookup_fk('customers',$cid);
        $add('customer_id',$cid);$title=param($v,'title');if(!$title)fail('请填写商机名称');$add('title',$title);
        $add('source',param($v,'source'));$add('status',param($v,'status','新询盘'));
        $c=currency(param($v,'currency','USD'));$add('currency',$c);
        $add('expected_amount',positive($v['expected_amount']??0,'商机金额'));
        $add('fx_to_cny',$c==='CNY'?1:rate($v['fx_to_cny']??0));
        if(authorized('finance.edit'))$add('estimated_cost_cny',positive($v['estimated_cost_cny']??($old['estimated_cost_cny']??0),'预计成本'));
        $prob=positive($v['probability']??0,'成交概率');if($prob>100)fail('成交概率不能超过100%');$add('probability',$prob);
        $add('note',param($v,'note'));if(!$id)$add('lead_code',keycode('OPP','leads'));
        break;
      case 'orders':
        $cid=intid($v['customer_id']??0);lookup_fk('customers',$cid);
        $lead=!empty($v['lead_id'])?intid($v['lead_id']):null;
        if($lead){$l=query_one('SELECT customer_id FROM leads WHERE id=?',[$lead]);if(!$l||(int)$l['customer_id']!==$cid)fail('关联商机必须属于该客户');}
        $add('customer_id',$cid);$add('lead_id',$lead);$add('order_date',ymd($v['order_date']??''));
        $add('status',param($v,'status','待处理'));$c=currency(param($v,'currency','USD'));$add('currency',$c);
        $add('amount',positive($v['amount']??0,'订单销售额'));
        $add('fx_to_cny',$c==='CNY'?1:rate($v['fx_to_cny']??0));$add('notes',param($v,'notes'));
        if(!$id){$add('order_code',param($v,'order_code')?:keycode('SO','orders'));$add('created_by',user_required()['id']);}
        break;
      case 'parties':
        if(!param($v,'name'))fail('主体名称不能为空');$add('name',param($v,'name'));$add('kind',param($v,'kind','合作工厂'));$add('note',param($v,'note'));
        break;
      case 'warehouses':
        if(!param($v,'name'))fail('仓库名称不能为空');$add('name',param($v,'name'));$add('location',param($v,'location'));
        $add('party_id',!empty($v['party_id'])?intid($v['party_id']):null);
        break;
    }
    if(in_array($entity,['products','lots','customers','leads','orders','parties','warehouses'],true)){
        $custom=safe_custom($entity,is_array($v['custom_json']??null)?$v['custom_json']:[]);
        check_custom_required($entity,$custom);
        $add('custom_json',j(array_merge(unj($old['custom_json']??null),$custom)));
    }
    db()->beginTransaction();
    try{
        if($entity==='lots'){
            $p=query_one('SELECT active FROM products WHERE id=? FOR UPDATE',[$v['product_id']??0]);
            if(!$p||(int)$p['active']!==1)fail('此产品已删除，不能建立或编辑库存批次；请先恢复产品');
        }
        if($id){$sql='UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>"`$k`=?",$cols)).' WHERE id=?';execute_sql($sql,[...$values,$id]);}
        else {$sql='INSERT INTO '.$table.' ('.implode(',',array_map(fn($k)=>"`$k`",$cols)).') VALUES('.implode(',',array_fill(0,count($cols),'?')).')';execute_sql($sql,$values);$id=(int)db()->lastInsertId();}
        audit($old?'update':'create',$entity,$id,['fields'=>$cols]);
        db()->commit();return ['ok'=>true,'id'=>$id];
    }catch(Throwable $e){db()->rollBack();throw $e;}
}
/** Product archive (soft-delete) and restore, retaining all historical references. */
function set_product_active(int $id,bool $active): array {
    can('products.edit');
    $pdo=db();$pdo->beginTransaction();
    try{
        $product=query_one('SELECT id,name,sku,active FROM products WHERE id=? FOR UPDATE',[$id]);
        if(!$product)fail('产品不存在',404);
        if((int)$product['active']===($active?1:0))fail($active?'产品已是启用状态':'产品已删除，无需重复操作');
        if(!$active){
            // Lock lots after locking the product: same order as stock operations.
            $lots=query_all('SELECT id,quantity,reserved FROM stock_lots WHERE product_id=? FOR UPDATE',[$id]);
            foreach($lots as $lot){
                if((float)$lot['quantity']>0.000001||(float)$lot['reserved']>0.000001)
                    fail('该产品仍有库存或订单预留，不能删除。请先处理库存，或保留产品档案。');
            }
        }
        execute_sql('UPDATE products SET active=? WHERE id=?',[$active?1:0,$id]);
        audit($active?'restore':'archive','products',$id,['sku'=>$product['sku'],'name'=>$product['name']]);
        $pdo->commit();
        return ['ok'=>true,'id'=>$id,'active'=>$active?1:0];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
/** Archive/restore rows without destroying historical links. Requires upgrade 1.3 columns. */
function set_entity_active(string $entity,int $id,bool $active): array {
    $map=['lots'=>['stock_lots','lots.edit'],'customers'=>['customers','customers.edit'],'leads'=>['leads','leads.edit']];
    if(!isset($map[$entity]))fail('该模块不支持归档');
    [$table,$perm]=$map[$entity];can($perm);
    $pdo=db();$pdo->beginTransaction();
    try{
        // Products are always locked before lots, matching stock movement lock order.
        if($entity==='lots'){
            $ref=query_one('SELECT product_id FROM stock_lots WHERE id=?',[$id]);
            if(!$ref)fail('库存批次不存在',404);
            $product=query_one('SELECT active FROM products WHERE id=? FOR UPDATE',[$ref['product_id']]);
            if($active&&(!$product||(int)$product['active']!==1))fail('请先恢复此批次所属产品');
        }
        $row=query_one("SELECT * FROM $table WHERE id=? FOR UPDATE",[$id]);
        if(!$row)fail('记录不存在',404);
        if((int)$row['active']===($active?1:0))fail($active?'记录已经启用':'记录已经归档');
        if(!$active&&$entity==='lots'){
            if(abs((float)$row['quantity'])>0.000001||(float)$row['reserved']>0.000001)
                fail('批次还有实际库存或预留，不能删除。请先处理库存及预留。');
            $sum=query_one('SELECT COALESCE(SUM(qty),0) AS qty FROM stock_reservations WHERE lot_id=?',[$id]);
            if((float)($sum['qty']??0)>0.000001)fail('批次尚有关联订单预留，无法删除');
        }
        execute_sql("UPDATE $table SET active=? WHERE id=?",[$active?1:0,$id]);
        audit($active?'restore':'archive',$entity,$id,['name'=>$row['name']??$row['title']??$row['lot_code']??'']);
        $pdo->commit();
        return ['ok'=>true,'id'=>$id,'active'=>$active?1:0];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
/** Safe reversal for independent stock movements; keep both records for audit. */
function reverse_stock_movement(int $id): array {
    can('movements.create');
    $ref=query_one('SELECT lot_id FROM stock_movements WHERE id=?',[$id]);
    if(!$ref)fail('原始流水不存在',404);
    $pdo=db();$pdo->beginTransaction();
    try{
        $lotRef=query_one('SELECT product_id FROM stock_lots WHERE id=?',[$ref['lot_id']]);
        if(!$lotRef)fail('原批次不存在');
        $product=query_one('SELECT active FROM products WHERE id=? FOR UPDATE',[$lotRef['product_id']]);
        if(!$product||(int)$product['active']!==1)fail('产品已归档，请先恢复');
        $lot=query_one('SELECT quantity,reserved,active FROM stock_lots WHERE id=? FOR UPDATE',[$ref['lot_id']]);
        if(!$lot||(int)$lot['active']!==1)fail('批次已归档，请先恢复');
        $original=query_one('SELECT * FROM stock_movements WHERE id=? FOR UPDATE',[$id]);
        if(!$original|| (int)$original['lot_id']!==(int)$ref['lot_id'])fail('原始流水不存在或已改变');
        if($original['reversed_movement_id']!==null)fail('冲正流水不能再次冲正');
        if(!empty($original['order_id']))fail('关联订单的流水不能自动冲正，请先由管理员核对订单与预留');
        if(!in_array($original['movement_type'],['in','out','adjust_in','adjust_out'],true))fail('调拨流水必须整体核对，不能单独冲正');
        if(query_one('SELECT id FROM stock_movements WHERE reversed_movement_id=?',[$id]))fail('此流水已经冲正，不能重复冲正');
        $originalIsIn=in_array($original['movement_type'],['in','adjust_in'],true);
        $qty=(float)$original['qty'];$before=(float)$lot['quantity'];
        $after=round($before+($originalIsIn?-$qty:$qty),3);
        if($after<0||$after+0.000001<(float)$lot['reserved'])fail('冲正后库存不足或影响订单预留，请先核对批次可用量');
        execute_sql('UPDATE stock_lots SET quantity=? WHERE id=?',[$after,$ref['lot_id']]);
        $note='冲正流水 #'.$id.'；原备注：'.$original['remark'];
        execute_sql('INSERT INTO stock_movements(lot_id,movement_type,qty,before_qty,after_qty,customer_id,remark,created_by,custom_json,reversed_movement_id) VALUES(?,?,?,?,?,?,?,?,?,?)',[
            $ref['lot_id'],$originalIsIn?'adjust_out':'adjust_in',$qty,$before,$after,$original['customer_id'],mb_substr_safe($note,500),user_required()['id'],'{}',$id
        ]);
        $newId=(int)$pdo->lastInsertId();
        audit('reverse','movements',$id,['reversal_id'=>$newId,'lot_id'=>$ref['lot_id'],'before'=>$before,'after'=>$after]);
        $pdo->commit();return ['ok'=>true,'reversal_id'=>$newId,'after'=>$after];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}

function stock_move(array $v): array {
    can('movements.create');
    $lotId=intid($v['lot_id']??0);$type=param($v,'type');
    if(!in_array($type,['in','out','adjust_in','adjust_out'],true))fail('库存操作类型无效');
    $qty=positive($v['qty']??null,'出入库数量',false);
    $oid=!empty($v['order_id'])?intid($v['order_id']):null;
    $cid=!empty($v['customer_id'])?intid($v['customer_id']):null;
    if($oid){$order=query_one('SELECT customer_id FROM sales_orders WHERE id=?',[$oid]);if(!$order)fail('订单不存在');$cid=(int)$order['customer_id'];}
    if($cid)lookup_fk('customers',$cid);
    $pdo=db();$pdo->beginTransaction();
    try{
        $src=query_one('SELECT product_id FROM stock_lots WHERE id=?',[$lotId]);
        if(!$src)fail('库存批次不存在');
        $product=query_one('SELECT active FROM products WHERE id=? FOR UPDATE',[$src['product_id']]);
        if(!$product||(int)$product['active']!==1)fail('此产品已删除，不能出入库；请先恢复产品');
        $lot=query_one('SELECT quantity,reserved,active FROM stock_lots WHERE id=? FOR UPDATE',[$lotId]);if(!$lot||(int)$lot['active']!==1)fail('批次不存在或已归档');
        $before=(float)$lot['quantity'];$reserved=(float)$lot['reserved'];$after=$before;$consumeReserved=0;
        $isOut=in_array($type,['out','adjust_out'],true);
        if($isOut){
            $allowed=$before-$reserved;$consumeReserved=0;
            if($oid){$r=query_one('SELECT qty FROM stock_reservations WHERE lot_id=? AND order_id=? FOR UPDATE',[$lotId,$oid]);$consumeReserved=min($qty,(float)($r['qty']??0));$allowed+=$consumeReserved;}
            if($qty>$allowed+0.00001)fail('可用库存不足（其他订单的预留数量不可出库）');
            $after=$before-$qty;
            if($consumeReserved>0){execute_sql('UPDATE stock_lots SET reserved=reserved-? WHERE id=?',[$consumeReserved,$lotId]);execute_sql('UPDATE stock_reservations SET qty=qty-? WHERE lot_id=? AND order_id=?',[$consumeReserved,$lotId,$oid]);}
        }else{$after=$before+$qty;}
        execute_sql('UPDATE stock_lots SET quantity=? WHERE id=?',[$after,$lotId]);
        $custom=safe_custom('movements',is_array($v['custom_json']??null)?$v['custom_json']:[]);check_custom_required('movements',$custom);
        execute_sql('INSERT INTO stock_movements(lot_id,movement_type,qty,before_qty,after_qty,customer_id,order_id,remark,created_by,custom_json) VALUES (?,?,?,?,?,?,?,?,?,?)',[$lotId,$type,$qty,$before,$after,$cid,$oid,param($v,'remark'),user_required()['id'],j($custom)]);
        $id=(int)$pdo->lastInsertId();audit('stock_'.$type,'lots',$lotId,['qty'=>$qty,'before'=>$before,'after'=>$after,'order_id'=>$oid]);
        $pdo->commit();return ['ok'=>true,'id'=>$id,'after'=>$after,'reserved'=>$reserved-$consumeReserved];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function transfer_stock(array $v): array {
    can('movements.create');can('lots.edit');
    $lotId=intid($v['lot_id']??0);$targetOwner=intid($v['owner_party_id']??0);
    $targetWarehouse=intid($v['warehouse_id']??0);$qty=positive($v['qty']??null,'调拨数量',false);
    lookup_fk('parties',$targetOwner);lookup_fk('warehouses',$targetWarehouse);
    $pdo=db();$pdo->beginTransaction();
    try {
        $src=query_one('SELECT * FROM stock_lots WHERE id=? FOR UPDATE',[$lotId]);if(!$src||(int)$src['active']!==1)fail('来源批次不存在或已归档');
        if($targetOwner==(int)$src['owner_party_id']&&$targetWarehouse==(int)$src['warehouse_id'])fail('调拨目标与原仓库及货权相同');
        $before=(float)$src['quantity'];$reserved=(float)$src['reserved'];
        if($qty>$before-$reserved+0.00001)fail('可用库存不足，不能调拨其他订单已预留的货');
        $dst=query_one('SELECT * FROM stock_lots WHERE product_id=? AND lot_code=? AND owner_party_id=? AND warehouse_id=? FOR UPDATE',[$src['product_id'],$src['lot_code'],$targetOwner,$targetWarehouse]);
        $fromWarehouse=query_one('SELECT name FROM warehouses WHERE id=?',[$src['warehouse_id']]);
        $toWarehouse=query_one('SELECT name FROM warehouses WHERE id=?',[$targetWarehouse]);
        $actor=user_required()['id'];$note=param($v,'remark');
        if(!$dst){
            execute_sql('INSERT INTO stock_lots(product_id,lot_code,owner_party_id,warehouse_id,grade,location,quantity,reserved,unit_cost_cny,photo_id,custom_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[
                $src['product_id'],$src['lot_code'],$targetOwner,$targetWarehouse,$src['grade'],param($v,'location'),0,0,$src['unit_cost_cny'],$src['photo_id'],$src['custom_json']]);
            $destId=(int)$pdo->lastInsertId();$dstBefore=0;$dstCost=(float)$src['unit_cost_cny'];
        }else{
            $destId=(int)$dst['id'];$dstBefore=(float)$dst['quantity'];
            if((int)$dst['active']!==1)fail('目标批次已归档，请先恢复该批次');
            if($src['grade']!==$dst['grade'])fail('目标同编号批次的质量等级不同，请先建立不同批次编号');
            $dstCost=($dstBefore*(float)$dst['unit_cost_cny']+$qty*(float)$src['unit_cost_cny'])/($dstBefore+$qty);
        }
        execute_sql('UPDATE stock_lots SET quantity=? WHERE id=?',[$before-$qty,$lotId]);
        execute_sql('UPDATE stock_lots SET quantity=?,unit_cost_cny=? WHERE id=?',[$dstBefore+$qty,round($dstCost,4),$destId]);
        $noteOut=mb_substr_safe('调拨至 '.$toWarehouse['name'].'；'.$note,490);
        $noteIn=mb_substr_safe('调拨自 '.$fromWarehouse['name'].'；'.$note,490);
        foreach([[$lotId,'transfer_out',$before,$before-$qty,$noteOut],[$destId,'transfer_in',$dstBefore,$dstBefore+$qty,$noteIn]] as $m){
            execute_sql('INSERT INTO stock_movements(lot_id,movement_type,qty,before_qty,after_qty,remark,created_by,custom_json) VALUES(?,?,?,?,?,?,?,?)',[$m[0],$m[1],$qty,$m[2],$m[3],$m[4],$actor,'{}']);
        }
        audit('transfer','lots',$lotId,['to_lot'=>$destId,'qty'=>$qty,'new_owner'=>$targetOwner,'new_warehouse'=>$targetWarehouse]);
        $pdo->commit();return ['ok'=>true,'destination_lot_id'=>$destId];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}

function reserve_stock(array $v): array {
    can('orders.edit');$lotId=intid($v['lot_id']??0);$orderId=intid($v['order_id']??0);
    $qty=positive($v['qty']??null,'预留数量');
    lookup_fk('sales_orders',$orderId);
    $pdo=db();$pdo->beginTransaction();
    try{
        $l=query_one('SELECT quantity,reserved,active FROM stock_lots WHERE id=? FOR UPDATE',[$lotId]);if(!$l||(int)$l['active']!==1)fail('批次不存在或已归档');
        $old=query_one('SELECT qty FROM stock_reservations WHERE lot_id=? AND order_id=? FOR UPDATE',[$lotId,$orderId]);
        $previous=(float)($old['qty']??0);$delta=$qty-$previous;
        if($delta>(float)$l['quantity']-(float)$l['reserved']+0.00001)fail('库存不足，不能预留');
        if($old)execute_sql('UPDATE stock_reservations SET qty=? WHERE lot_id=? AND order_id=?',[$qty,$lotId,$orderId]);
        else execute_sql('INSERT INTO stock_reservations(lot_id,order_id,qty) VALUES(?,?,?)',[$lotId,$orderId,$qty]);
        execute_sql('UPDATE stock_lots SET reserved=reserved+? WHERE id=?',[$delta,$lotId]);
        audit('reserve','lots',$lotId,['order'=>$orderId,'qty'=>$qty]);$pdo->commit();return ['ok'=>true];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function get_detail(string $entity,int $id): array {
    permission_for($entity);
    $v=list_rows_by_id($entity,$id);
    if(!$v)fail('记录不存在',404);
    if($entity==='orders'){
        $v['lines']=query_all('SELECT ol.*,p.name AS product_name,p.unit FROM order_lines ol JOIN products p ON p.id=ol.product_id WHERE ol.order_id=? ORDER BY ol.id',[$id]);
        $v['direct_costs']=query_all('SELECT id,cost_date,category,amount_cny,note FROM cost_entries WHERE order_id=? ORDER BY id',[$id]);
        $v['receipts']=query_all('SELECT * FROM receipts WHERE order_id=? ORDER BY receipt_date,id',[$id]);
        $v['reservations']=query_all('SELECT r.*,l.lot_code,p.name AS product_name FROM stock_reservations r JOIN stock_lots l ON l.id=r.lot_id JOIN products p ON p.id=l.product_id WHERE r.order_id=? AND r.qty>0',[$id]);
        if(!authorized('finance.view'))unset($v['direct_costs'],$v['receipts']);
    }
    return ['ok'=>true,'record'=>$v];
}
function list_rows_by_id(string $entity,int $id): ?array {
    $tables=['products'=>'products','lots'=>'stock_lots','customers'=>'customers','leads'=>'leads','orders'=>'sales_orders','parties'=>'parties','warehouses'=>'warehouses','quotes'=>'quotes','users'=>'users'];
    if(!isset($tables[$entity]))fail('不支持查看详情');
    $row=query_one('SELECT * FROM '.$tables[$entity].' WHERE id=?',[$id]);
    if(!$row)return null;
    foreach(['custom_json','tags_json','permissions_json','fees_json'] as $f)if(isset($row[$f]))$row[$f]=unj($row[$f]);
    if($entity==='lots'&&!authorized('finance.view'))unset($row['unit_cost_cny']);
    if($entity==='products'&&!authorized('finance.view'))unset($row['reference_price_cny']);
    if(in_array($entity,['orders','leads'],true)&&!authorized('finance.view')){
        unset($row['estimated_cost_cny']);
    }
    if($entity==='users')unset($row['password_hash']);
    return $row;
}
function add_order_line(array $v): array {
    can('orders.edit');$oid=intid($v['order_id']??0);lookup_fk('sales_orders',$oid);
    $pid=intid($v['product_id']??0);if(!query_one('SELECT id FROM products WHERE id=? AND active=1',[$pid]))fail('此产品已删除，无法新增订单成本明细');
    $lot=!empty($v['lot_id'])?intid($v['lot_id']):null;
    if($lot){$l=query_one('SELECT product_id,unit_cost_cny,active FROM stock_lots WHERE id=?',[$lot]);if(!$l||(int)$l['product_id']!==$pid||(int)$l['active']!==1)fail('批次与产品不一致或批次已归档');}
    $qty=positive($v['qty']??null,'产品数量',false);
    if(!authorized('finance.edit'))fail('订单成本明细需要财务编辑权限',403);
    $cost=positive($v['unit_cost_cny']??($l['unit_cost_cny']??0),'产品成本');
    execute_sql('INSERT INTO order_lines(order_id,product_id,lot_id,qty,unit_cost_cny) VALUES(?,?,?,?,?)',[$oid,$pid,$lot,$qty,$cost]);
    $id=(int)db()->lastInsertId();audit('create','order_lines',$id,['order_id'=>$oid]);return ['ok'=>true,'id'=>$id];
}
function remove_order_line(int $id): array {
    can('orders.edit');can('finance.edit');$v=query_one('SELECT order_id FROM order_lines WHERE id=?',[$id]);if(!$v)fail('订单行不存在');
    execute_sql('DELETE FROM order_lines WHERE id=?',[$id]);audit('delete','order_lines',$id,$v);return ['ok'=>true];
}
function save_cost(array $v): array {
    can('finance.edit');$oid=!empty($v['order_id'])?intid($v['order_id']):null;
    if($oid)lookup_fk('sales_orders',$oid);
    $category=param($v,'category');if(!$category)fail('请选择成本分类');
    $amount=positive($v['amount_cny']??null,'费用金额');$date=ymd($v['cost_date']??'');
    $custom=safe_custom('expenses',is_array($v['custom_json']??null)?$v['custom_json']:[]);check_custom_required('expenses',$custom);
    execute_sql('INSERT INTO cost_entries(order_id,cost_date,category,amount_cny,note,created_by,custom_json) VALUES(?,?,?,?,?,?,?)',[$oid,$date,$category,$amount,param($v,'note'),user_required()['id'],j($custom)]);
    $id=(int)db()->lastInsertId();audit('create',$oid?'order_direct_cost':'operating_expense',$id,['amount_cny'=>$amount,'order_id'=>$oid]);return ['ok'=>true,'id'=>$id];
}
function delete_cost(int $id): array {
    can('finance.edit');$v=query_one('SELECT order_id,amount_cny FROM cost_entries WHERE id=?',[$id]);if(!$v)fail('费用不存在');
    execute_sql('DELETE FROM cost_entries WHERE id=?',[$id]);audit('delete','cost_entries',$id,$v);return ['ok'=>true];
}
function add_receipt(array $v): array {
    can('finance.edit');$oid=intid($v['order_id']??0);
    lookup_fk('sales_orders',$oid);$c=currency(param($v,'currency','USD'));
    $amount=positive($v['amount']??null,'收款金额',false);$fx=$c==='CNY'?1:rate($v['fx_to_cny']??0);
    execute_sql('INSERT INTO receipts(order_id,receipt_date,amount,currency,fx_to_cny,amount_cny,note,created_by) VALUES (?,?,?,?,?,?,?,?)',[$oid,ymd($v['receipt_date']??''),$amount,$c,$fx,round($amount*$fx,2),param($v,'note'),user_required()['id']]);
    $id=(int)db()->lastInsertId();audit('create','receipts',$id,['order_id'=>$oid,'amount_cny'=>round($amount*$fx,2)]);return ['ok'=>true,'id'=>$id];
}
function remove_receipt(int $id): array {can('finance.edit');$r=query_one('SELECT order_id,amount_cny FROM receipts WHERE id=?',[$id]);if(!$r)fail('收款记录不存在');execute_sql('DELETE FROM receipts WHERE id=?',[$id]);audit('delete','receipts',$id,$r);return ['ok'=>true];}
function reports(array $i): array {
    can('dashboard.view');
    $start=ymd($i['start']??date('Y-m-01'));$end=ymd($i['end']??date('Y-m-t'));
    if($start>$end)fail('开始日期不能晚于结束日期');
    $config=settings('finance',[]);$cancel=$config['order_cancel_status']??'已取消';$lost=$config['lead_lost_status']??'已丢单';
    $basic=[
        'products'=>(int)query_one('SELECT COUNT(*) AS c FROM products WHERE active=1')['c'],
        'available_lots'=>(int)query_one('SELECT COUNT(*) AS c FROM stock_lots WHERE quantity>reserved')['c'],
        'low_stock_lots'=>(int)query_one('SELECT COUNT(*) AS c FROM products p WHERE p.active=1 AND p.safety_stock>0 AND (SELECT COALESCE(SUM(quantity-reserved),0) FROM stock_lots WHERE product_id=p.id)<p.safety_stock')['c'],
        'leads_count'=>(int)query_one('SELECT COUNT(*) AS c FROM leads WHERE DATE(created_at) BETWEEN ? AND ?',[$start,$end])['c'],
        'orders_count'=>(int)query_one("SELECT COUNT(*) AS c FROM sales_orders WHERE status<>? AND order_date BETWEEN ? AND ?",[$cancel,$start,$end])['c'],
    ];
    if(!authorized('finance.view'))return ['ok'=>true,'report'=>$basic,'start'=>$start,'end'=>$end];
    $sales=query_one("SELECT COALESCE(SUM(amount*fx_to_cny),0) AS revenue FROM sales_orders WHERE status<>? AND order_date BETWEEN ? AND ?",[$cancel,$start,$end]);
    $line=query_one("SELECT COALESCE(SUM(ol.qty*ol.unit_cost_cny),0) AS costs FROM order_lines ol JOIN sales_orders o ON o.id=ol.order_id WHERE o.status<>? AND o.order_date BETWEEN ? AND ?",[$cancel,$start,$end]);
    $direct=query_one("SELECT COALESCE(SUM(c.amount_cny),0) AS costs FROM cost_entries c JOIN sales_orders o ON o.id=c.order_id WHERE o.status<>? AND o.order_date BETWEEN ? AND ?",[$cancel,$start,$end]);
    $company=query_one('SELECT COALESCE(SUM(amount_cny),0) AS costs FROM cost_entries WHERE order_id IS NULL AND cost_date BETWEEN ? AND ?',[$start,$end]);
    $receipts=query_one('SELECT COALESCE(SUM(amount_cny),0) AS v FROM receipts WHERE receipt_date BETWEEN ? AND ?',[$start,$end]);
    $leads=query_all('SELECT l.id,l.status,l.expected_amount,l.fx_to_cny,l.estimated_cost_cny,COALESCE((SELECT SUM(o.amount*o.fx_to_cny) FROM sales_orders o WHERE o.lead_id=l.id AND o.status<>?),0) AS converted FROM leads l WHERE DATE(l.created_at) BETWEEN ? AND ?',[$cancel,$start,$end]);
    $funnel=['lead_value_cny'=>0,'converted_cny'=>0,'open_cny'=>0,'lost_cny'=>0,'potential_profit_cny'=>0];
    foreach($leads as $l){$expected=(float)$l['expected_amount']*(float)$l['fx_to_cny'];$converted=(float)$l['converted'];$remaining=max(0,$expected-$converted);
        $funnel['lead_value_cny']+=$expected;$funnel['converted_cny']+=$converted;$funnel['potential_profit_cny']+=($expected-(float)$l['estimated_cost_cny']);
        if($l['status']===$lost)$funnel['lost_cny']+=$remaining;else $funnel['open_cny']+=$remaining;
    }
    $p=company_profit((float)$sales['revenue'],(float)$line['costs']+(float)$direct['costs'],(float)$company['costs']);
    $trend=query_all("SELECT DATE_FORMAT(order_date,'%Y-%m') AS month,ROUND(SUM(amount*fx_to_cny),2) AS revenue FROM sales_orders WHERE status<>? AND order_date BETWEEN ? AND ? GROUP BY DATE_FORMAT(order_date,'%Y-%m') ORDER BY month",[$cancel,$start,$end]);
    $breakdown=query_all('SELECT category,ROUND(SUM(amount_cny),2) AS amount FROM cost_entries WHERE order_id IS NULL AND cost_date BETWEEN ? AND ? GROUP BY category ORDER BY amount DESC',[$start,$end]);
    return ['ok'=>true,'report'=>array_merge($basic,$p,$funnel,['receipts_cny'=>round((float)$receipts['v'],2)]),'trend'=>$trend,'expense_breakdown'=>$breakdown,'start'=>$start,'end'=>$end,
      'note'=>'按订单日期计入销售收入与订单直接成本，按费用日期计入公司经营费用；属于管理口径的经营盈亏估算，并非正式会计利润。'];
}
function quote_calculate(array $v): array {
    can('quotes.use');$c=currency(param($v,'currency','USD'));
    $fx=$c==='CNY'?1:rate($v['fx_to_cny']??0);$qty=positive($v['qty']??0,'报价数量',false);
    $unitCost=positive($v['unit_cost_cny']??0,'产品单位成本');
    $margin=positive($v['margin_pct']??0,'目标毛利率');$comm=positive($v['commission_pct']??0,'佣金率');
    if($margin+$comm>=95)fail('目标毛利率与佣金率合计必须小于95%');
    $fees=$v['fees']??[];if(!is_array($fees)||count($fees)>40)fail('费用项目数量超限');
    $direct=$qty*$unitCost;$feesClean=[];
    foreach($fees as $f){if(!is_array($f))continue;$label=mb_substr_safe(trim((string)($f['label']??'费用')),60);
        $amount=positive($f['amount_cny']??0,'报价费用');$direct+=$amount;$feesClean[]=['label'=>$label,'amount_cny'=>$amount];}
    $saleCny=$direct/(1-($margin+$comm)/100);
    $profitCny=$saleCny-$direct-$saleCny*$comm/100;
    $totalOriginal=$saleCny/$fx;
    return ['currency'=>$c,'fx_to_cny'=>$fx,'qty'=>$qty,'unit_cost_cny'=>$unitCost,'fees'=>$feesClean,'direct_cny'=>round($direct,2),
        'total_cny'=>round($saleCny,2),'quote_total'=>round($totalOriginal,2),'unit_price'=>round($totalOriginal/$qty,4),
        'commission_cny'=>round($saleCny*$comm/100,2),'expected_profit_cny'=>round($profitCny,2),'margin_pct'=>$margin,'commission_pct'=>$comm];
}
function save_quote(array $v): array {
    can('quotes.use');$result=quote_calculate($v);$pid=!empty($v['product_id'])?intid($v['product_id']):null;
    $lid=!empty($v['lot_id'])?intid($v['lot_id']):null;$cid=!empty($v['customer_id'])?intid($v['customer_id']):null;
    $term=param($v,'term','FOB');if(!in_array($term,['EXW','FOB','CFR','CIF','DAP','DDP'],true))fail('无效贸易术语');
    $custom=safe_custom('quotes',is_array($v['custom_json']??null)?$v['custom_json']:[]);check_custom_required('quotes',$custom);
    execute_sql('INSERT INTO quotes(quote_code,product_id,lot_id,customer_id,currency,fx_to_cny,term,qty,unit,unit_cost_cny,margin_pct,commission_pct,fees_json,custom_json,quote_total,expected_profit_cny,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[
        keycode('QT','quotes'),$pid,$lid,$cid,$result['currency'],$result['fx_to_cny'],$term,$result['qty'],param($v,'unit','件'),$result['unit_cost_cny'],$result['margin_pct'],$result['commission_pct'],j($result['fees']),j($custom),$result['quote_total'],$result['expected_profit_cny'],user_required()['id']]);
    $id=(int)db()->lastInsertId();audit('create','quotes',$id);return ['ok'=>true,'id'=>$id,'result'=>$result];
}
function save_user(array $v): array {
    can('users.manage');$id=!empty($v['id'])?intid($v['id']):null;
    $name=param($v,'name');$email=param($v,'email');$phone=param($v,'phone');
    if(!$name||(!$email&&!$phone))fail('姓名以及邮箱或手机号至少填写一项');
    if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL))fail('邮箱格式不正确');
    if($phone&&!preg_match('/^\+?[0-9\s-]{6,24}$/',$phone))fail('手机号格式不正确');
    $role=param($v,'role','employee');if(!in_array($role,['admin','warehouse','sales','employee'],true))fail('角色无效');
    $perms=$v['permissions']??[];if(!is_array($perms))fail('权限配置错误');
    $perms=array_values(array_intersect(ALL_PERMISSIONS,array_map('strval',$perms)));
    $custom=safe_custom('users',is_array($v['custom_json']??null)?$v['custom_json']:[]);check_custom_required('users',$custom);
    $active=empty($v['active'])?0:1;
    $password=(string)($v['password']??'');
    if($id){$old=query_one('SELECT role FROM users WHERE id=?',[$id]);if(!$old)fail('员工不存在');
        if($old['role']==='superadmin')fail('超级管理员权限不可通过此页面变更');
        if($id===(int)user_required()['id']&&$active===0)fail('不能禁用自己');
        $parts=['name=?','email=?','phone=?','role=?','permissions_json=?','active=?','custom_json=?'];$args=[$name,$email?:null,$phone?:null,$role,j($perms),$active,j(array_merge(unj(query_one('SELECT custom_json FROM users WHERE id=?',[$id])['custom_json']??null),$custom))];
        if($password!==''){if(strlen($password)<10)fail('密码至少10位');$parts[]='password_hash=?';$parts[]='must_change_password=1';$args[]=password_hash($password,PASSWORD_DEFAULT);}
        $args[]=$id;execute_sql('UPDATE users SET '.implode(',',$parts).' WHERE id=?',$args);
    }else{
        if(strlen($password)<10)fail('新员工的初始密码至少10位');
        execute_sql('INSERT INTO users(name,email,phone,password_hash,role,permissions_json,active,must_change_password,custom_json) VALUES(?,?,?,?,?,?,?,1,?)',[$name,$email?:null,$phone?:null,password_hash($password,PASSWORD_DEFAULT),$role,j($perms),$active,j($custom)]);$id=(int)db()->lastInsertId();
    }
    audit('save','users',$id,['role'=>$role,'active'=>$active,'permissions'=>$perms]);return ['ok'=>true,'id'=>$id];
}
function change_password(array $v): array {
    $u=user_required();$old=param($v,'old_password');$new=(string)($v['new_password']??'');
    $hash=query_one('SELECT password_hash FROM users WHERE id=?',[$u['id']]);
    if(!password_verify($old,$hash['password_hash']??''))fail('当前密码不正确',403);
    if(strlen($new)<12)fail('新密码至少12位');
    execute_sql('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$u['id']]);
    session_regenerate_id(true);audit('change_password','users',$u['id']);return ['ok'=>true];
}
function save_field(array $v): array {
    can('settings.manage');$module=param($v,'module');
    if(!in_array($module,array_keys(ENTITY_PERMS),true))fail('无效的字段模块');
    $key=param($v,'field_key');$old=$key?query_one('SELECT * FROM field_definitions WHERE module=? AND field_key=?',[$module,$key]):null;
    if(!$old){if($module==='audit')fail('审计流水只允许调整现有展示字段');$key='cf_'.bin2hex(random_bytes(6));}
    $label=param($v,'label');if(!$label)fail('字段标题不能为空');
    $type=param($v,'field_type','text');if(!in_array($type,['text','textarea','number','select','date','checkbox','email','image','relation','tags'],true))fail('字段类型无效');
    $opts=$v['options']??[];if(!is_array($opts))fail('选项格式错误');$opts=array_slice(array_values(array_filter(array_map('strval',$opts))),0,80);
    $visible=empty($v['visible'])?0:1;$required=empty($v['required_field'])?0:1;$listVisible=empty($v['list_visible'])?0:1;
    $position=(int)($v['position']??100);$placeholder=mb_substr_safe(param($v,'placeholder'),200);
    if($old){
        if($old['protected']){
            if(!$visible||!$required)fail('此字段关系到数据完整性，不能隐藏或取消必填，但可以改名、排序');
            if($type!==$old['field_type'])fail('核心字段不能变更数据类型');
        }
        if($old['builtin']&&$type!==$old['field_type'])fail('内置字段不能更改数据类型');
        execute_sql('UPDATE field_definitions SET label=?,visible=?,list_visible=?,required_field=?,position=?,options_json=?,placeholder=?,active=1 WHERE module=? AND field_key=?',[
            $label,$visible,$listVisible,$required,$position,j($opts),$placeholder,$module,$key]);
    }else{
        execute_sql('INSERT INTO field_definitions(module,field_key,label,field_type,builtin,protected,visible,list_visible,required_field,position,options_json,placeholder,active) VALUES(?,?,?,?,0,0,?,?,?,?,?,?,1)',[
            $module,$key,$label,$type,$visible,$listVisible,$required,$position,j($opts),$placeholder]);
    }
    audit('configure','field_definitions',$module.'.'.$key,['label'=>$label,'position'=>$position]);return ['ok'=>true,'field_key'=>$key];
}
function disable_field(array $v): array {
    can('settings.manage');$m=param($v,'module');$k=param($v,'field_key');
    $r=query_one('SELECT builtin,protected FROM field_definitions WHERE module=? AND field_key=?',[$m,$k]);if(!$r)fail('字段不存在');
    if((int)$r['protected'])fail('核心字段不能停用');
    execute_sql('UPDATE field_definitions SET active=0,visible=0,list_visible=0 WHERE module=? AND field_key=?',[$m,$k]);
    audit('disable','field_definitions',$m.'.'.$k);return ['ok'=>true];
}
function sort_settings(array $v): array {
    can('settings.manage');$type=param($v,'type');$items=$v['items']??[];
    if(!is_array($items)||count($items)>150)fail('排序内容格式不正确');
    if($type==='menu'){
        $q=db()->prepare('UPDATE menu_settings SET label=?,position=?,visible=? WHERE module=?');
        foreach($items as $x){$key=param($x,'module');if($key==='settings'&&empty($x['visible']))fail('不能隐藏设置中心');$q->execute([param($x,'label'),(int)($x['position']??0),empty($x['visible'])?0:1,$key]);}
    }elseif($type==='fields'){
        $q=db()->prepare('UPDATE field_definitions SET position=? WHERE module=? AND field_key=?');
        foreach($items as $x)$q->execute([(int)$x['position'],param($x,'module'),param($x,'field_key')]);
    }elseif($type==='dashboard'){
        setting_set('dashboard_cards',$items);
    }elseif($type==='sections'){
        $allowed=['brand','menu','fields','refs','finance','dashboard','security'];
        if(count($items)!==count($allowed)||array_diff($allowed,array_column($items,'id')))fail('设置栏目的项目不完整');
        $clean=[];foreach($items as $k=>$x){if(!in_array($x['id'],$allowed,true))fail('无效栏目');$clean[]=['id'=>$x['id'],'label'=>mb_substr_safe(trim((string)($x['label']??'')),80),'position'=>($k+1)*10];}
        setting_set('settings_sections',$clean);
    }else fail('不支持的排序类型');
    audit('reorder','settings',$type);return ['ok'=>true];
}
function save_brand(array $v): array {
    can('settings.manage');$brand=settings('brand',[]);
    $brand['name']=mb_substr_safe(param($v,'name','明驰 FRP 库存管理系统'),70);
    $brand['subtitle']=mb_substr_safe(param($v,'subtitle','INVENTORY OS'),70);
    $color=param($v,'accent','#245fc9');if(!preg_match('/^#[a-fA-F0-9]{6}$/',$color))fail('颜色格式错误');$brand['accent']=$color;
    if(array_key_exists('logo_id',$v))$brand['logo_id']=!empty($v['logo_id'])?intid($v['logo_id']):null;
    setting_set('brand',$brand);audit('configure','brand');return ['ok'=>true];
}
function save_finance_config(array $v): array {
    can('settings.manage');$old=settings('finance',[]);
    $old['default_usd_rate']=rate($v['default_usd_rate']??7.2);
    $old['default_margin']=positive($v['default_margin']??22,'默认毛利率');
    $old['default_commission']=positive($v['default_commission']??0,'默认佣金率');
    if($old['default_margin']+$old['default_commission']>=95)fail('默认利润率与佣金率总和过高');
    $old['base_currency']='CNY';setting_set('finance',$old);
    audit('configure','finance');return ['ok'=>true];
}
function save_ref(array $v): array {
    can('settings.manage');$group=param($v,'group_code');
    $groups=['product_categories','units','grades','lead_status','lead_sources','order_status','operating_expenses','direct_costs','customer_tags','party_kinds'];
    if(!in_array($group,$groups,true))fail('无效的选项分类');
    $id=!empty($v['id'])?intid($v['id']):null;$value=param($v,'value');if($value==='')fail('选项内容不能为空');
    // MySQL 5.7.44 utf8mb4 composite unique index (group_code + value) stays within 767 bytes.
    if(!preg_match('//u',$value)||preg_match_all('/./us',$value)>150)fail('选项内容最多150个字符');
    $old=$id?query_one('SELECT * FROM reference_values WHERE id=? AND group_code=?',[$id,$group]):null;
    if($id&&!$old)fail('选项不存在');
    $pdo=db();$pdo->beginTransaction();
    try{
      if($id)execute_sql('UPDATE reference_values SET value=?,position=?,active=? WHERE id=? AND group_code=?',[$value,(int)($v['position']??0),empty($v['active'])?0:1,$id,$group]);
      else execute_sql('INSERT INTO reference_values(group_code,value,position,active) VALUES(?,?,?,1)',[$group,$value,(int)($v['position']??100)]);
      // The option label can change without severing existing records.
      if($old&&$old['value']!==$value){
          $mapping=[
             'product_categories'=>['products','category'],'units'=>['products','unit'],
             'grades'=>['stock_lots','grade'],'lead_status'=>['leads','status'],
             'lead_sources'=>['leads','source'],'order_status'=>['sales_orders','status'],
             'operating_expenses'=>['cost_entries','category'],
             'direct_costs'=>['cost_entries','category'],'party_kinds'=>['parties','kind']
          ];
          if(isset($mapping[$group])){
              [$table,$column]=$mapping[$group];$extra=$group==='operating_expenses'?' AND order_id IS NULL':($group==='direct_costs'?' AND order_id IS NOT NULL':'');
              execute_sql("UPDATE $table SET $column=? WHERE $column=?$extra",[$value,$old['value']]);
          }
          if($group==='customer_tags'){
              foreach(query_all('SELECT id,tags_json FROM customers WHERE tags_json IS NOT NULL') as $customer){
                  $tags=unj($customer['tags_json']);$tags=array_map(fn($x)=>$x===$old['value']?$value:$x,$tags);
                  execute_sql('UPDATE customers SET tags_json=? WHERE id=?',[j(array_values(array_unique($tags))),$customer['id']]);
              }
          }
          if($group==='order_status'||$group==='lead_status'){
              $config=settings('finance',[]);$key=$group==='order_status'?'order_cancel_status':'lead_lost_status';
              if(($config[$key]??null)===$old['value']){$config[$key]=$value;setting_set('finance',$config);}
          }
      }
      audit('configure','reference_values',$id?:$group,['value'=>$value]);$pdo->commit();return ['ok'=>true];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}

function import_customers(array $v): array {
    can('customers.edit');$rows=$v['rows']??[];
    if(!is_array($rows)||count($rows)>1000)fail('单次最多导入1000条');
    $mapping=$v['mapping']??[];if(!is_array($mapping)||!isset($mapping['name']))fail('必须映射客户名称列');
    $added=0;$skipped=0;$pdo=db();$pdo->beginTransaction();
    try{
        foreach($rows as $r){if(!is_array($r)){$skipped++;continue;}
            $get=function($key)use($r,$mapping){$index=$mapping[$key]??null;return $index===null?'':trim((string)($r[$index]??''));};
            $name=mb_substr_safe($get('name'),190);if(!$name){$skipped++;continue;}
            $email=$get('email');if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)){$skipped++;continue;}
            $exists=query_one('SELECT id FROM customers WHERE name=? AND email=? LIMIT 1',[$name,$email]);if($exists){$skipped++;continue;}
            $tags=preg_split('/[|;；、]/u',$get('tags_json'))?:[];$tags=array_values(array_filter(array_map('trim',$tags)));
            execute_sql('INSERT INTO customers(customer_code,name,country,contact,email,phone,source,tags_json) VALUES(?,?,?,?,?,?,?,?)',[
                keycode('CUS','customers'),$name,$get('country'),$get('contact'),$email,$get('phone'),$get('source'),j($tags)]);$added++;
        }
        audit('import','customers',null,['added'=>$added,'skipped'=>$skipped]);$pdo->commit();return ['ok'=>true,'added'=>$added,'skipped'=>$skipped];
    }catch(Throwable $e){$pdo->rollBack();throw $e;}
}
function upload_media(): array {
    $u=user_required();
    if(!authorized('products.edit')&&!authorized('lots.edit')&&!authorized('settings.manage'))fail('没有上传图片权限',403);
    if(!isset($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))fail('请选择图片');
    $f=$_FILES['file'];if($f['error']!==UPLOAD_ERR_OK||$f['size']>5*1024*1024)fail('图片应小于5MB');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
    if(!$ext||!getimagesize($f['tmp_name']))fail('仅支持有效的 JPG、PNG、WEBP 图片');
    $stored=bin2hex(random_bytes(18)).'.'.$ext;$dest=APP_ROOT.'/storage/uploads/'.$stored;
    if(!move_uploaded_file($f['tmp_name'],$dest))fail('图片保存失败');@chmod($dest,0640);
    execute_sql('INSERT INTO uploads(stored_name,mime,original_name,uploaded_by) VALUES(?,?,?,?)',[$stored,$mime,mb_substr_safe((string)$f['name'],180),$u['id']]);
    $id=(int)db()->lastInsertId();audit('upload','media',$id);return ['ok'=>true,'id'=>$id,'url'=>'media.php?id='.$id];
}
function download_entity_export(string $entity,array $filters): never {
    can('export.data');permission_for($entity);
    $allowed=['products','lots','customers','leads','orders','expenses','parties','warehouses','movements','quotes','audit'];
    if(!in_array($entity,$allowed,true))fail('此模块不支持导出');
    $fields=query_all('SELECT field_key,label FROM field_definitions WHERE module=? AND active=1 AND list_visible=1 ORDER BY position,id',[$entity]);
    if(!authorized('finance.view'))$fields=array_values(array_filter($fields,fn($f)=>!in_array($f['field_key'],['unit_cost_cny','reference_price_cny','estimated_cost_cny','expected_profit_cny'],true)));
    if($entity==='orders'&&authorized('finance.view')){$fields[]=['field_key'=>'revenue_cny','label'=>'人民币订单收入'];$fields[]=['field_key'=>'profit_cny','label'=>'人民币订单贡献利润'];}
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mingchi-'.preg_replace('/[^a-z]/','',$entity).'-'.date('Ymd').'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');fputcsv($out,array_column($fields,'label'));
    for($page=1;$page<=10000;$page++){
        $result=list_rows($entity,array_merge($filters,['page'=>$page,'limit'=>100]));
        foreach($result['rows'] as $row){
            $line=[];
            foreach($fields as $f){
                $key=$f['field_key'];$value=$row[$key]??($row['custom_json'][$key]??'');
                if(is_array($value))$value=implode('|',$value);
                $value=(string)$value;
                // Prevent spreadsheet formula interpretation of user-entered text.
                if(preg_match('/^[\s]*[=+@\-]/u',$value)&&!is_numeric($value))$value="'".$value;
                $line[]=$value;
            }
            fputcsv($out,$line);
        }
        if($page*100 >= $result['total'])break;
    }
    fclose($out);exit;
}
