<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60); define('WEEK_IN_SECONDS',604800);
function add_filter(){} function add_action(){} $ACTIONS=[]; function do_action($h,...$a){global $ACTIONS;$ACTIONS[]=$h;} function apply_filters($h,$v){return $v;} function __($s){return $s;} function esc_html__($s){return $s;} function _n($a,$b,$n){return $n==1?$a:$b;}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return htmlspecialchars((string)$s);} function selected($a,$b,$e=true){return $a==$b?' selected':'';}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function wp_strip_all_tags($s){return strip_tags($s);} function wp_json_encode($d){return json_encode($d);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));}
function is_wp_error($x){return $x instanceof WP_Error;} class WP_Error{public $m; function __construct($c,$m){$this->m=$m;} function get_error_message(){return $this->m;}}
function add_query_arg($a,$b=null,$c=null){ if(is_array($a)) return $b.'?'.http_build_query($a); return $c.'?'.$a.'='.$b; } function current_user_can(){return true;} function get_current_user_id(){return 1;} function absint($n){return abs((int)$n);}
$TR=[]; function set_transient($k,$v,$t=0){global $TR;$TR[$k]=$v;} function get_transient($k){global $TR;return $TR[$k]??false;} function delete_transient($k){global $TR;unset($TR[$k]);}
$SET=[]; function cartiva_commerce_settings(){global $SET;return $SET;} function cartiva_commerce_setting($k){global $SET;return $SET[$k]??null;}
$CALLS=[]; $REPLY=[]; function wp_remote_request($url,$args){global $CALLS,$REPLY; $CALLS[]=[$url,$args]; foreach($REPLY as $pat=>$r){ if(str_contains($url,$pat)) return is_callable($r)?$r($url,$args):$r; } return ['code'=>404,'body'=>'{}'];}
function wp_remote_retrieve_response_code($r){return $r['code'];} function wp_remote_retrieve_body($r){return $r['body'];}
function rest_url($p){return "https://shop.test/wp-json/$p";}
class Countries{ function get_states($c){return ['BD-13'=>'Dhaka','BD-10'=>'Chattogram'];} } function WC(){ return new class{ public $countries; function __construct(){$this->countries=new Countries;} }; }
class Item{public $n,$q; function __construct($n,$q){$this->n=$n;$this->q=$q;} function get_name(){return $this->n;} function get_quantity(){return $this->q;}}
class WC_Order{ public $d=[]; public $meta=[]; public $notes=[]; public $saved=0; public $status='processing'; public $items=[];
 function __construct($d=[]){ $this->d=$d+['billing_first_name'=>'Rahim','billing_last_name'=>'Uddin','billing_phone'=>'+880 1712-345678','billing_address_1'=>'House 12, Road 5, Mirpur 10','billing_address_2'=>'','billing_city'=>'Dhaka','billing_state'=>'BD-13','billing_country'=>'BD','total'=>1250,'payment'=>'cod','number'=>'1001','note'=>'','id'=>55,'ship'=>false]; $this->items=[new Item('Panjabi <b>Blue</b>',2),new Item('Cap',1)]; }
 function __call($m,$a){ if(str_starts_with($m,'get_billing_')||str_starts_with($m,'get_shipping_')){ $k=substr($m,4); return $this->d[$k]??''; } throw new Exception("no $m"); }
 function has_shipping_address(){return $this->d['ship'];} function get_total(){return $this->d['total'];} function get_payment_method(){return $this->d['payment'];} function get_order_number(){return $this->d['number'];} function get_customer_note(){return $this->d['note'];} function get_id(){return $this->d['id'];}
 function get_items(){return $this->items;} function get_meta($k){return $this->meta[$k]??'';} function update_meta_data($k,$v){$this->meta[$k]=$v;} function add_order_note($n){$this->notes[]=$n;} function save(){$this->saved++;}
 function has_status($s){return in_array($this->status,(array)$s,true);} function set_status($s,$n=''){$this->status=$s; $this->notes[]=$n;} }
$ORDERS=[]; $LASTQ=null; function wc_get_orders($a){global $ORDERS,$LASTQ; $LASTQ=$a; if(isset($a['meta_key'])) return array_values(array_filter($ORDERS,fn($o)=>$o->get_meta($a['meta_key'])==$a['meta_value'])); return array_keys($ORDERS);} function wc_get_order($id){global $ORDERS;return $ORDERS[$id]??false;}
require CARTIVA_COMMERCE_DIR.'includes/class-courier.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$C='Cartiva_Commerce_Courier';
$base=['courier_default'=>'','courier_weight'=>0.5,'courier_note'=>'','courier_complete'=>false,'courier_cancel'=>false,'sf_key'=>'','sf_secret'=>'','sf_webhook_token'=>'','pa_client_id'=>'','pa_client_secret'=>'','pa_username'=>'','pa_password'=>'','pa_store_id'=>0,'pa_sandbox'=>false,'rx_token'=>'','rx_sandbox'=>false,'rx_store_id'=>0,'rx_area_id'=>0,'rx_area_name'=>''];
$SET=$base;
ok($C::ready()===[] && $C::default_provider()==='','nothing connected');
$SET['sf_key']='K'; ok(!$C::configured('steadfast'),'key without secret is not enough'); $SET['sf_secret']='S';
$SET['pa_client_id']='c';$SET['pa_client_secret']='s';$SET['pa_username']='u@x';$SET['pa_password']='p'; ok(!$C::configured('pathao'),'Pathao needs a store ID'); $SET['pa_store_id']=77;
$SET['rx_token']='Bearer tok';
ok(array_keys($C::ready())===['steadfast','pathao','redx'] && $C::default_provider()==='steadfast','all three ready, first is default');
$SET['courier_default']='redx'; ok($C::default_provider()==='redx','chosen default'); $SET['courier_default']='';

// Phones
ok($C::phone('+880 1712-345678')==='01712345678' && $C::phone('8801712345678')==='01712345678' && $C::phone('1712345678')==='01712345678' && $C::phone('01712345678')==='01712345678','phone formats become 01XXXXXXXXX');

// Recipient and payloads
$o=new WC_Order; $to=$C::recipient($o);
ok($to['name']==='Rahim Uddin' && $to['phone']==='01712345678' && $to['address']==='House 12, Road 5, Mirpur 10, Dhaka','recipient from billing; state code shown as name and not repeated');
$o2=new WC_Order(['ship'=>true,'shipping_first_name'=>'Karim','shipping_last_name'=>'','shipping_address_1'=>'Agrabad CDA','shipping_city'=>'Chattogram','shipping_state'=>'BD-10','shipping_country'=>'BD','shipping_phone'=>'01811111111']);
$t2=$C::recipient($o2); ok($t2['name']==='Karim' && $t2['phone']==='01811111111' && $t2['address']==='Agrabad CDA, Chattogram','shipping address and phone when given');
$d=$C::defaults($o); ok($d['cod']===1250.0 && $d['weight']===0.5,'COD order: collect the total');
$o3=new WC_Order(['payment'=>'bacs']); ok($C::defaults($o3)['cod']===0.0,'paid order: collect nothing');
$sf=$C::payload('steadfast',$o,$d);
ok($sf['invoice']==='1001' && $sf['cod_amount']==1250 && $sf['recipient_phone']==='01712345678' && $sf['item_description']==='Panjabi Blue x 2, Cap x 1','Steadfast parcel');
$pa=$C::payload('pathao',$o,$d);
ok($pa['store_id']===77 && $pa['merchant_order_id']==='1001' && $pa['delivery_type']===48 && $pa['item_type']===2 && $pa['item_quantity']===3 && $pa['amount_to_collect']===1250 && !isset($pa['special_instruction']) && !isset($pa['recipient_city']),'Pathao parcel (city and zone left for Pathao to detect)');
$rx=$C::payload('redx',$o,['cod'=>1250,'note'=>'Call first','weight'=>1.2,'area_id'=>9,'area_name'=>'Mirpur 10']);
ok($rx['delivery_area_id']===9 && $rx['parcel_weight']===1200 && $rx['cash_collection_amount']==='1250' && $rx['instruction']==='Call first' && !isset($rx['pickup_store_id']),'RedX parcel: grams, area, no store unless set');
$SET['rx_store_id']=5; ok($C::payload('redx',$o,$d+['area_id'=>1,'area_name'=>'x'])['pickup_store_id']===5,'RedX pickup store when set');

// Problems
ok($C::problem('steadfast',$o,$d)==='','valid order passes');
$bad=new WC_Order(['billing_phone'=>'12345']); ok(str_contains($C::problem('steadfast',$bad,$d),'not a valid'),'bad phone stopped');
$short=new WC_Order(['billing_address_1'=>'Dhk','billing_city'=>'','billing_state'=>'']); ok(str_contains($C::problem('steadfast',$short,$d),'too short'),'short address stopped');
ok(str_contains($C::problem('redx',$o,['area_id'=>0,'area_name'=>'']),'RedX delivery area'),'RedX without area stopped');
ok(str_contains($C::problem('ecourier',$o,$d),'API details'),'unknown courier stopped');

// Send: Steadfast success
$REPLY=['/create_order'=>['code'=>200,'body'=>json_encode(['status'=>200,'message'=>'Consignment has been created successfully.','consignment'=>['consignment_id'=>1424107,'invoice'=>'1001','tracking_code'=>'15BAEB8A','status'=>'in_review']])]];
$CALLS=[]; $r=$C::send($o,'steadfast');
ok($r['ok'] && $o->meta['_cartiva_consignment']==='1424107' && $o->meta['_cartiva_tracking']==='15BAEB8A' && $o->meta['_cartiva_courier_state']==='pending' && $o->saved===1,'Steadfast booking saved on the order');
$h=$CALLS[0][1]['headers']; ok($CALLS[0][0]==='https://portal.packzy.com/api/v1/create_order' && $h['Api-Key']==='K' && $h['Secret-Key']==='S' && $CALLS[0][1]['method']==='POST','Steadfast URL and keys');
ok(count($o->notes)===1 && str_contains($o->notes[0],'Sent to Steadfast. Parcel 15BAEB8A'),'one order note, no status noise');
$r=$C::send($o,'steadfast'); ok(!$r['ok'] && str_contains($r['message'],'already sent') && count($CALLS)===1,'cannot send twice');
// Send: error answer
$o4=new WC_Order(['id'=>56]); $REPLY=['/create_order'=>['code'=>422,'body'=>json_encode(['status'=>400,'message'=>'Validation failed','errors'=>['recipient_phone'=>['The recipient phone must be 11 digits.']]])]];
$r=$C::send($o4,'steadfast'); ok(!$r['ok'] && str_contains($r['message'],'Validation failed The recipient phone must be 11 digits.') && str_contains($o4->notes[0],'booking failed') && !isset($o4->meta['_cartiva_consignment']),'courier error shown and noted');
$REPLY=['/create_order'=>new WP_Error('x','timed out')]; $o5=new WC_Order(['id'=>57]); $r=$C::send($o5,'steadfast'); ok(!$r['ok'] && str_contains($r['message'],'timed out'),'network error shown');
// status 200 HTTP but status field error
$REPLY=['/create_order'=>['code'=>200,'body'=>json_encode(['status'=>401,'message'=>'Invalid API key'])]]; $o5=new WC_Order(['id'=>58]); $r=$C::send($o5,'steadfast'); ok(!$r['ok'] && $r['message']==='Invalid API key','error inside a 200 answer caught');

// Pathao: token then order, token reused
$CALLS=[]; $REPLY=['issue-token'=>['code'=>200,'body'=>json_encode(['token_type'=>'Bearer','expires_in'=>432000,'access_token'=>'AT1','refresh_token'=>'RT'])],'/aladdin/api/v1/orders'=>['code'=>200,'body'=>json_encode(['message'=>'Order Created Successfully','type'=>'success','code'=>200,'data'=>['consignment_id'=>'DL121224VS8TTJ','merchant_order_id'=>'1001','order_status'=>'Pending','delivery_fee'=>80]])]];
$p1=new WC_Order(['id'=>60]); $r=$C::send($p1,'pathao');
ok($r['ok'] && $p1->meta['_cartiva_consignment']==='DL121224VS8TTJ' && $p1->meta['_cartiva_courier']==='pathao','Pathao booking');
ok($CALLS[0][0]==='https://api-hermes.pathao.com/aladdin/api/v1/issue-token' && json_decode($CALLS[0][1]['body'],true)['grant_type']==='password' && $CALLS[1][1]['headers']['Authorization']==='Bearer AT1','Pathao token then order with Bearer');
$p2=new WC_Order(['id'=>61,'number'=>'1002']); $C::send($p2,'pathao'); ok(count($CALLS)===3,'token reused for the next order');
$SET['pa_sandbox']=true; ok($C::base('pathao')==='https://courier-api-sandbox.pathao.com','Pathao sandbox URL'); $SET['pa_sandbox']=false;
$TR=[]; $CALLS=[]; $REPLY=['issue-token'=>['code'=>401,'body'=>json_encode(['message'=>'Unauthorized'])]]; $p3=new WC_Order(['id'=>62]); $r=$C::send($p3,'pathao'); ok(!$r['ok'] && str_contains($r['message'],'did not accept'),'bad Pathao login explained');

// RedX
$CALLS=[]; $REPLY=['/parcel'=>['code'=>201,'body'=>json_encode(['tracking_id'=>'21A427TU4BN3R'])]]; $x1=new WC_Order(['id'=>63]);
$r=$C::send($x1,'redx',['area_id'=>3,'area_name'=>'Mirpur 10']); ok($r['ok'] && $x1->meta['_cartiva_tracking']==='21A427TU4BN3R' && $CALLS[0][1]['headers']['API-ACCESS-TOKEN']==='Bearer tok','RedX booking; Bearer not doubled');

ok(in_array('cartiva_commerce_parcel_sent',$ACTIONS,true),'booking tells the SMS feature');
// Status normalize and refresh
ok($C::normalize('in_review')==='pending' && $C::normalize('Pickup_Requested')==='pending' && $C::normalize('In_Transit')==='transit' && $C::normalize('delivery-in-progress')==='transit' && $C::normalize('Delivered')==='delivered' && $C::normalize('partial_delivered')==='partial' && $C::normalize('Partial_Delivery')==='partial' && $C::normalize('cancelled')==='returned' && $C::normalize('Return')==='returned' && $C::normalize('agent-returning')==='returned','courier statuses in plain words');
$REPLY=['status_by_cid/1424107'=>['code'=>200,'body'=>json_encode(['status'=>200,'delivery_status'=>'delivered'])]]; $SET['courier_complete']=true; $o->saved=0;
$r=$C::refresh($o); ok($r['ok'] && $o->meta['_cartiva_courier_state']==='delivered' && $o->status==='completed' && $o->saved===1,'delivered: order completed when turned on');
ok($C::finished($o),'delivered parcel is finished');
ok(in_array('cartiva_commerce_parcel_status',$ACTIONS,true),'status change tells the SMS feature');
$REPLY=['issue-token'=>['code'=>200,'body'=>json_encode(['access_token'=>'AT3','expires_in'=>9000])],'/orders/DL121224VS8TTJ/info'=>['code'=>200,'body'=>json_encode(['code'=>200,'type'=>'success','data'=>['consignment_id'=>'DL121224VS8TTJ','order_status'=>'Return','order_status_slug'=>'Return']])]]; $SET['courier_cancel']=false;
$C::refresh($p1); ok($p1->meta['_cartiva_courier_state']==='returned' && $p1->status==='processing','returned: order left alone unless turned on');
$REPLY=['/parcel/info/21A427TU4BN3R'=>['code'=>200,'body'=>json_encode(['parcel'=>['tracking_id'=>'21A427TU4BN3R','status'=>'returned']])]]; $SET['courier_cancel']=true;
$C::refresh($x1); ok($x1->status==='cancelled','returned: cancelled when turned on');
$n=count($x1->notes); $C::set_status($x1,'returned'); ok(count($x1->notes)===$n,'same status twice: no new note');
ok(!$C::refresh(new WC_Order)['ok'],'no parcel: nothing to refresh');

// Sync query
$ORDERS=[]; $C::sync(); ok($LASTQ['meta_query'][1]['compare']==='NOT IN' && $LASTQ['limit']===40,'hourly sync asks only for open parcels');

// History
$TR=[]; $REPLY=['fraud_check/01712345678'=>['code'=>200,'body'=>json_encode(['total_parcels'=>10,'total_delivered'=>9,'total_cancelled'=>1,'total_fraud_reports'=>[]])]];
$hi=$C::history('+8801712345678'); ok($hi['rate']===90 && $hi['reports']===0,'delivery history read'); ok($C::history_verdict($hi)[0]==='good' && str_contains($C::history_verdict($hi)[1],'9 of 10'),'good customer');
ok($C::history_verdict(['total'=>4,'delivered'=>1,'cancelled'=>3,'reports'=>2,'rate'=>25])[0]==='bad','risky customer');
ok($C::history_verdict(['total'=>0,'delivered'=>0,'cancelled'=>0,'reports'=>0,'rate'=>null])[0]==='new','new customer');
$REPLY=[]; ok($C::history('01712345678')['rate']===90,'history cached');
ok($C::history('123')===null,'bad phone: no check');

// RedX area guess
$areas=[['id'=>1,'name'=>'Mirpur','district'=>'Dhaka'],['id'=>2,'name'=>'Mirpur 10','district'=>'Dhaka'],['id'=>3,'name'=>'Dhaka','district'=>'Dhaka'],['id'=>4,'name'=>'Agrabad','district'=>'Chattogram']];
ok($C::guess_redx_area($o,$areas)['id']===2,'longest matching area wins (Mirpur 10 over Mirpur)');
ok($C::guess_redx_area($o2,$areas)['id']===4,'area found in shipping address');
$o6=new WC_Order(['billing_address_1'=>'Some village road','billing_city'=>'Dhaka']); ok($C::guess_redx_area($o6,[['id'=>3,'name'=>'Dhaka','district'=>'Dhaka'],['id'=>9,'name'=>'Banani','district'=>'Dhaka']])['id']===3,'falls back to the city');
ok($C::guess_redx_area($o6,[['id'=>9,'name'=>'Banani','district'=>'Dhaka']])===null,'no guess when nothing matches');

// Tracking links and customer view
ok($C::tracking_url($o)==='https://steadfast.com.bd/t/15BAEB8A','Steadfast tracking link');
ok(str_starts_with($C::tracking_url($p1),'https://merchant.pathao.com/tracking?') && str_contains($C::tracking_url($p1),'phone=01712345678'),'Pathao tracking link');
ok($C::tracking_url($x1)==='https://redx.com.bd/track-parcel/?trackingId=21A427TU4BN3R','RedX tracking link');
ob_start(); $C::customer_tracking($o); $h=ob_get_clean(); ok(str_contains($h,'Track your parcel') && str_contains($h,'Steadfast'),'customer sees the tracking link');
ob_start(); $C::customer_tracking(new WC_Order); ok(ob_get_clean()==='','no link before sending');

// Columns and bulk
ok(array_keys($C::columns(['order_status'=>1,'cartiva_source'=>1,'order_total'=>1]))===['order_status','cartiva_source','cartiva_courier','order_total'],'Courier column after Source');
ok(str_contains($C::cell(new WC_Order),'Not sent') && str_contains($C::cell($o),'Delivered'),'courier cell');
$acts=$C::bulk_actions(['trash'=>'Trash']); ok(isset($acts['cartiva_send_steadfast'],$acts['cartiva_send_pathao'],$acts['cartiva_send_redx'],$acts['cartiva_courier_refresh']),'bulk actions for each courier');
$ORDERS=[70=>new WC_Order(['id'=>70,'number'=>'2001']),71=>new WC_Order(['id'=>71,'number'=>'2002','billing_phone'=>'999'])];
$REPLY=['/create_order'=>['code'=>200,'body'=>json_encode(['status'=>200,'consignment'=>['consignment_id'=>5,'tracking_code'=>'T5','status'=>'in_review']])]];
$u=$C::handle_bulk('back','cartiva_send_steadfast',[70,71]); $res=$TR['cartiva_courier_bulk_1'];
ok($u==='back' && $res['done']===1 && count($res['errors'])===1 && str_contains($res['errors'][0],'#2002'),'bulk send: one sent, one explained');
ok($C::handle_bulk('back','mark_completed',[70])==='back','other bulk actions untouched');

// Webhook
class Req{ public $h; public $b; function __construct($h,$b){$this->h=$h;$this->b=$b;} function get_header($k){return $this->h;} function get_json_params(){return $this->b;} }
$SET['sf_webhook_token']='tok123';
ok($C::webhook_allowed(new Req('Bearer tok123',[])) && !$C::webhook_allowed(new Req('Bearer nope',[])) && !$C::webhook_allowed(new Req('',[])),'webhook needs the token');
$SET['sf_webhook_token']=''; ok(!$C::webhook_allowed(new Req('Bearer ',[])),'no token saved: webhook closed'); $SET['sf_webhook_token']='tok123';
$ORDERS[70]->status='processing'; $SET['courier_cancel']=true;
$res=$C::steadfast_webhook(new Req('Bearer tok123',['consignment_id'=>5,'invoice'=>'2001','status'=>'cancelled','cod_amount'=>0,'updated_at'=>'x']));
ok($res['status']==='success' && $ORDERS[70]->meta['_cartiva_courier_state']==='returned' && $ORDERS[70]->status==='cancelled','webhook updates the order');
ok($C::steadfast_webhook(new Req('Bearer tok123',['consignment_id'=>999,'status'=>'delivered']))['status']==='error','unknown parcel ignored');

// Test connection
$REPLY=['get_balance'=>['code'=>200,'body'=>json_encode(['status'=>200,'current_balance'=>1500])]]; $t=$C::test('steadfast'); ok($t['ok'] && str_contains($t['message'],'1500'),'Steadfast test shows the balance');
$TR=[]; $REPLY=['issue-token'=>['code'=>200,'body'=>json_encode(['access_token'=>'AT2','expires_in'=>1000])],'/stores'=>['code'=>200,'body'=>json_encode(['code'=>200,'data'=>['data'=>[['store_id'=>77,'store_name'=>'Main']]]])]];
$t=$C::test('pathao'); ok($t['ok'] && str_contains($t['message'],'77 = Main'),'Pathao test lists store IDs');
$REPLY=['/areas'=>['code'=>200,'body'=>json_encode(['areas'=>[['id'=>1,'name'=>'Mirpur','district_name'=>'Dhaka']]])]]; $t=$C::test('redx'); ok($t['ok'] && str_contains($t['message'],'1 delivery areas'),'RedX test loads areas');

echo "\n$pass passed, $fail failed\n";
if(getenv('DUMP')){ if(!class_exists('Cartiva_Commerce_Settings')){ eval('class Cartiva_Commerce_Settings{ const PAGE="x"; static function url($t=""){return "#";} }'); } function human_time_diff($t){return '5 mins';}
 $TR['cartiva_redx_areas']=$areas; $SET['rx_token']='t'; $fresh=new WC_Order(['id'=>90]); ob_start(); echo '<div style="display:flex;gap:20px;align-items:flex-start"><div class="postbox" style="width:280px;background:#fff;border:1px solid #c3c4c7;padding:0 12px">'; $C::render_box($fresh); echo '</div><div class="postbox" style="width:280px;background:#fff;border:1px solid #c3c4c7;padding:0 12px">'; $TR['cartiva_history_'.md5('01712345678')]=['total'=>4,'delivered'=>1,'cancelled'=>3,'reports'=>2,'rate'=>25]; $C::render_box($o); echo '</div></div>'; file_put_contents('/tmp/box.html',ob_get_clean()); }
