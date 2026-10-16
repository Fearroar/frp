<?php
declare(strict_types=1);
function seed_defaults(PDO $db): void {
    $brand=['name'=>'明驰 FRP 库存管理系统','subtitle'=>'MINGCHI INVENTORY OS','accent'=>'#245fc9','logo_id'=>null];
    $options=['base_currency'=>'CNY','default_usd_rate'=>7.20,'default_margin'=>22,'default_commission'=>0,'timezone'=>'Asia/Shanghai','order_cancel_status'=>'已取消','lead_lost_status'=>'已丢单'];
    foreach(['brand'=>$brand,'finance'=>$options,'settings_sections'=>[['id'=>'brand','label'=>'品牌与外观','position'=>10],['id'=>'menu','label'=>'模块与导航','position'=>20],['id'=>'fields','label'=>'字段与列表','position'=>30],['id'=>'refs','label'=>'选项与标签','position'=>40],['id'=>'finance','label'=>'币种与利润','position'=>50],['id'=>'dashboard','label'=>'看板卡片','position'=>60],['id'=>'security','label'=>'权限与数据安全','position'=>70]],'dashboard_cards'=>[
        ['key'=>'company_profit_cny','label'=>'本期公司经营盈亏','visible'=>true],
        ['key'=>'order_profit_cny','label'=>'本期订单贡献利润','visible'=>true],
        ['key'=>'revenue_cny','label'=>'本期订单销售额','visible'=>true],
        ['key'=>'operating_expense_cny','label'=>'本期公司经营费用','visible'=>true],
        ['key'=>'lead_value_cny','label'=>'商机预计总额','visible'=>true],
        ['key'=>'converted_cny','label'=>'商机已成交金额','visible'=>true],
        ['key'=>'open_cny','label'=>'待转化商机金额','visible'=>true],
        ['key'=>'available_lots','label'=>'可用库存批次','visible'=>true]
    ]] as $k=>$v){$s=$db->prepare('INSERT IGNORE INTO app_settings(`key`,value_json) VALUES (?,?)');$s->execute([$k,j($v)]);}
    $mods=[['dashboard','经营看板'],['products','产品档案'],['lots','库存批次'],['movements','出入库流水'],['customers','客户管理'],['leads','询盘与商机'],['orders','订单与盈亏'],['expenses','经营费用'],['quotes','即时报价计算器'],['parties','工厂与货权'],['warehouses','仓库管理'],['users','员工与权限'],['settings','系统设置'],['audit','系统操作日志']];
    $stmt=$db->prepare('INSERT IGNORE INTO menu_settings(module,label,position) VALUES (?,?,?)');
    foreach($mods as $i=>$v)$stmt->execute([$v[0],$v[1],($i+1)*10]);
    $groups=[
        'product_categories'=>['FRP模塑格栅','FRP拉挤格栅','FRP拉挤型材','FRP罐体管道','FRP结构件','其他FRP产品'],
        'units'=>['张','件','米','平方米','公斤','根','套','卷','吨'],
        'grades'=>['A级','B级','C级','其他'],
        'lead_status'=>['新询盘','已联系','已报价','谈判中','已成交','已丢单'],
        'lead_sources'=>['Google Ads','Alibaba国际站','独立站自然搜索','Facebook','LinkedIn','展会','客户推荐','主动开发','其他'],
        'order_status'=>['待处理','生产中','待发货','部分发货','已发货','已完成','已取消'],
        'operating_expenses'=>['广告及平台推广费','人员工资','房租及办公费','差旅招待','软件服务费','水电管理费','其他经营费用'],
        'direct_costs'=>['包装费','国内运输费','报关港杂费','国际运费','保险费','银行手续费','佣金','其他直接费用'],
        'customer_tags'=>['重点客户','经销商','承包商','工程项目','长期合作','待跟进'],
        'party_kinds'=>['自有工厂','合作工厂','第三方货主']
    ];
    $stmt=$db->prepare('INSERT IGNORE INTO reference_values(group_code,value,position) VALUES (?,?,?)');
    foreach($groups as $group=>$values)foreach($values as $i=>$v)$stmt->execute([$group,$v,($i+1)*10]);
    // Stable field_key is the data identity. Labels and positions are purely presentation.
    $defs=[
        'products'=>[
          ['name','产品名称','text',1,1],['sku','产品编号','text',1,1],['spec','规格','text',0,1],
          ['color','颜色','text',0,1],['material','材质/树脂','text',0,1],['category','产品分类','select',0,1],
          ['unit','库存单位','select',1,1],['safety_stock','安全库存提醒阈值','number',0,0],['reference_price_cny','参考售价（人民币）','number',0,0],
          ['description','备注说明','textarea',0,0],['photo_id','产品主图','image',0,0]
        ],
        'lots'=>[
          ['product_id','产品','relation',1,1],['lot_code','批次编号','text',1,1],
          ['owner_party_id','货权归属','relation',1,1],['warehouse_id','实际仓库','relation',1,1],
          ['grade','质量等级','select',0,1],['location','货架/库位','text',0,0],
          ['quantity','实存数量','number',1,1],['reserved','已预留','number',1,1],
          ['unit_cost_cny','成本单价（人民币）','number',0,0],['photo_id','批次实拍图','image',0,0]
        ],
        'customers'=>[
          ['name','客户名称','text',1,1],['customer_code','客户编号','text',1,1],
          ['country','国家/地区','text',0,1],['contact','联系人','text',0,1],
          ['email','邮箱','email',0,1],['phone','联系电话','text',0,0],
          ['source','客户来源','text',0,0],['owner_user_id','业务负责人','relation',0,1],
          ['tags_json','客户标签','tags',0,1]
        ],
        'leads'=>[
          ['title','商机名称','text',1,1],['lead_code','商机编号','text',1,1],
          ['customer_id','客户','relation',1,1],['source','商机来源','select',0,1],
          ['status','商机状态','select',1,1],['currency','商机币种','select',1,1],
          ['expected_amount','预计成交金额','number',0,1],['fx_to_cny','估算汇率','number',1,0],
          ['estimated_cost_cny','预计直接成本（人民币）','number',0,1],
          ['probability','成交概率（%）','number',0,0],['note','备注','textarea',0,0]
        ],
        'orders'=>[
          ['order_code','订单编号','text',1,1],['order_date','订单日期','date',1,1],
          ['customer_id','客户','relation',1,1],['lead_id','关联商机','relation',0,1],
          ['status','订单状态','select',1,1],['currency','订单币种','select',1,1],
          ['amount','原币销售金额','number',1,1],['fx_to_cny','订单参考汇率','number',1,0],
          ['notes','备注','textarea',0,0]
        ],
        'expenses'=>[['cost_date','费用日期','date',1,1],['category','费用类别','select',1,1],['amount_cny','费用金额（人民币）','number',1,1],['note','备注','textarea',0,0]],
        'parties'=>[['name','主体名称','text',1,1],['kind','主体类型','select',1,1],['note','备注','textarea',0,0]],
        'warehouses'=>[['name','仓库名称','text',1,1],['party_id','所在工厂/单位','relation',0,1],['location','位置','text',0,0]],
        'users'=>[['name','姓名','text',1,1],['email','邮箱登录名','email',0,1],['phone','手机号登录名','text',0,1],['role','角色','select',1,1]],
        'movements'=>[['created_at','操作时间','date',1,1],['movement_type','操作类型','select',1,1],['lot_id','库存批次','relation',1,1],['qty','数量','number',1,1],['remark','备注','text',0,0]],
        'audit'=>[['created_at','操作时间','date',1,1],['actor_name','操作人','text',0,1],['action','操作类型','text',1,1],['entity','数据模块','text',0,1],['entity_id','记录编号','text',0,1],['ip','操作IP','text',0,0]],
        'quotes'=>[['quote_code','报价编号','text',1,1],['term','贸易条款','select',1,1],['quote_total','报价总额','number',1,1],['expected_profit_cny','预计贡献利润（人民币）','number',1,1]]
    ];
    $q=$db->prepare('INSERT IGNORE INTO field_definitions(module,field_key,label,field_type,builtin,protected,visible,list_visible,required_field,position,options_json,placeholder,active) VALUES (?,?,?,?,1,?,?,?,?,?,?,?,1)');
    foreach($defs as $module=>$fields)foreach($fields as $i=>$f){
        $protected=in_array($f[0],['name','sku','unit','product_id','lot_code','owner_party_id','warehouse_id','quantity','reserved','customer_id','order_code','order_date','amount','currency','fx_to_cny','cost_date','amount_cny','role','movement_type','qty'],true)?1:0;
        $q->execute([$module,$f[0],$f[1],$f[2],$protected,1,$f[4],$f[3],($i+1)*10,'[]','']);
    }
    $c=$db->query('SELECT COUNT(*) FROM parties')->fetchColumn();
    if(!$c){$db->exec("INSERT INTO parties(name,kind,note) VALUES ('明驰FRP','自有工厂','初始主体')");$pid=$db->lastInsertId();$s=$db->prepare('INSERT INTO warehouses(name,party_id,location) VALUES(?,?,?)');$s->execute(['默认仓库',$pid,'']);}
}
