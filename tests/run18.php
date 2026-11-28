<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60);
define('CARTIVA_COMMERCE_LANDING','cartiva_landing'); define('CARTIVA_COMMERCE_FUNNEL','cartiva_funnel');
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;}
function __($s){return $s;} function esc_html__($s){return $s;} function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return $s;} function esc_attr_e($s){echo $s;} function esc_html_e($s){echo $s;}
function _n($a,$b,$n){return $n==1?$a:$b;} function wp_kses_post($s){return $s;} function wc_price($a){return '৳'.$a;} function checked($a,$b,$e=true){return $a==$b?' checked="checked"':'';} function selected($a,$b,$e=true){return $a===$b?' selected="selected"':'';}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));} function sanitize_text_field($s){return trim(strip_tags((string)$s));} function wp_unslash($v){return $v;}
function get_the_title($id){return "Title $id";} function get_edit_post_link($id){return "edit:$id";} function is_admin(){return true;}
$CONF=[]; function cartiva_commerce_landing_config($id){global $CONF;return $CONF[$id]??[];}
$CUR=0; function cartiva_commerce_current_landing_id(){global $CUR;return $CUR;} function cartiva_commerce_is_landing($id){global $CONF;return isset($CONF[$id]);}
$SET=['protect'=>true,'protect_main'=>false,'protect_mode'=>'flag','protect_hours'=>24,'protect_max'=>0,'protect_block'=>'','protect_message'=>''];
function cartiva_commerce_setting($k){global $SET;return $SET[$k]??null;}
$FUN=[]; function cartiva_commerce_funnel_of($id){global $FUN;return $FUN[$id]??0;} function cartiva_commerce_step_type($id){return 'checkout';}
class Cartiva_Commerce_Recovery{ static function phone_key($p){$d=preg_replace('/\D/','',(string)$p);return strlen($d)>=10?substr($d,-10):'';} }
class Cartiva_Commerce_Funnels{ static function step_types(){return ['checkout'=>'Checkout'];} }
class Cartiva_Commerce_Analytics{ static function funnel_pages($f){return ['all'=>[21,22],'entry'=>[21]];} }
class Sess{public $d=[]; function get($k){return $this->d[$k]??null;} function set($k,$v){$this->d[$k]=$v;}}
class WCx{public $session; function __construct(){$this->session=new Sess;}} $WC=new WCx; function WC(){global $WC;return $WC;}
$ORDERS=[]; function wc_get_orders($a){global $ORDERS,$LASTQ; $LASTQ=$a; $since=(int)substr($a['date_created'],1); return array_keys(array_filter($ORDERS,fn($o)=>$o['key']===$a['meta_value'] && $o['t']>$since));}
class Cart{public $fees=[]; public $empty=false; function is_empty(){return $this->empty;} function add_fee($n,$a,$t){$this->fees[]=[$n,$a];}}
class Item{public $m; public $n; function __construct($n,$m=[]){$this->n=$n;$this->m=$m;} function get_meta($k){return $this->m[$k]??'';} function get_name(){return $this->n;}}
class Order{public $meta=[]; public $items=[]; public $notes=[]; public $phone=''; function get_meta($k){return $this->meta[$k]??'';} function update_meta_data($k,$v){$this->meta[$k]=$v;} function add_order_note($n){$this->notes[]=$n;} function get_billing_phone(){return $this->phone;} function get_items(){return $this->items;}}
class WC_Order extends Order{}
class Err{public $e=[]; function add($c,$m){$this->e[]=$m;} function has_errors(){return (bool)$this->e;}}
require CARTIVA_COMMERCE_DIR.'includes/class-cod.php';
require CARTIVA_COMMERCE_DIR.'includes/class-orders.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$D='Cartiva_Commerce_COD'; $O='Cartiva_Commerce_Orders';

// Delivery settings
$s=$D::sanitize(['enabled'=>'1','options'=>[['label'=>'Inside Dhaka','amount'=>'60'],['label'=>'','amount'=>'99'],['label'=>'Outside <b>Dhaka</b>','amount'=>'-5'],['label'=>'Sub area','amount'=>'80.555']]]);
ok($s['enabled'] && count($s['options'])===3 && $s['options'][1]['label']==='Outside Dhaka' && $s['options'][1]['amount']==0 && $s['options'][2]['amount']==80.56,'delivery options cleaned: empty rows dropped, no negative charge');
ok(!$D::sanitize([])['enabled'],'off by default');
$CONF[5]=['delivery'=>['enabled'=>true,'options'=>[['label'=>'Inside Dhaka','amount'=>60],['label'=>'Outside Dhaka','amount'=>120]]]];
$CONF[6]=['delivery'=>[]]; $CONF[7]=['delivery'=>['enabled'=>true,'options'=>[]]];
ok(!$D::delivery(6)['enabled'] && !$D::delivery(7)['enabled'],'not enabled, or enabled with no areas: no choice shown');
$CUR=5; ob_start(); $D::delivery_choices(); $h=ob_get_clean();
ok(substr_count($h,'type="radio"')===2 && str_contains($h,'৳120') && str_contains($h,'value="0" checked'),'form shows both areas with prices, first chosen');
$c=new Cart; $D::delivery_fee($c); ok($c->fees===[['Delivery: Inside Dhaka',60.0]],'Inside Dhaka charge added to the order');
$WC->session->set('cartiva_delivery',[5=>1]); $c=new Cart; $D::delivery_fee($c); ok($c->fees===[['Delivery: Outside Dhaka',120.0]],'Outside Dhaka chosen: 120 added');
$WC->session->set('cartiva_delivery',[5=>9]); ok($D::chosen(5)===0,'unknown choice falls back to the first area');
ok($D::needs_shipping(true)===false,'landing page with delivery choice: WooCommerce shipping not needed');
$CUR=6; ok($D::needs_shipping(true)===true,'other landing page: shipping unchanged'); $c=new Cart; $D::delivery_fee($c); ok($c->fees===[],'no fee when the page has no delivery choice');
$CUR=0; ok($D::needs_shipping(true)===true,'main checkout: shipping unchanged'); ob_start(); $D::delivery_choices(); ok(ob_get_clean()==='','main checkout: no delivery choice');
$CUR=5; $WC->session->set('cartiva_delivery',[5=>1]); $o=new Order; $o->phone='+880 1712-345678'; $D::tag_order($o);
ok($o->meta['_cartiva_phone_key']==='1712345678' && $o->meta['_cartiva_delivery']==='Outside Dhaka','order keeps phone key and delivery area');

// Protection
$now=time(); $ORDERS=[1=>['key'=>'1712345678','t'=>$now-3600],2=>['key'=>'1812345678','t'=>$now-30*3600]];
ok($D::check('01712345678')['verdict']==='flag','same phone 1 hour ago: flagged');
ok($D::check('01812345678')['verdict']==='ok','same phone 30 hours ago, 24-hour window: fine');
ok($D::check('01912345678')['verdict']==='ok','new number: fine');
$SET['protect_mode']='block'; ok($D::check('01712345678')['verdict']==='block','block mode refuses repeats');
$SET['protect_mode']='flag'; $SET['protect_hours']=0; $SET['protect_max']=1; ok($D::check('01712345678')['verdict']==='flag' && str_contains($D::check('01712345678')['reason'],'1 order today'),'daily limit reached');
$SET['protect_max']=2; ok($D::check('01712345678')['verdict']==='ok','under the daily limit');
$SET['protect_block']="01999-888777\n+8801555444333"; ok($D::check('8801999888777')['verdict']==='block' && $D::check('01555444333')['verdict']==='block','blocked list matches any phone format');
$e=new Err; $CUR=5; $D::protect(['billing_phone'=>'01999888777'],$e); ok(count($e->e)===1 && str_contains($e->e[0],'already have an order'),'blocked number refused with default message');
$SET['protect_message']='Call 0170000'; $e=new Err; $D::protect(['billing_phone'=>'01999888777'],$e); ok($e->e===['Call 0170000'],'custom message');
$e=new Err; $CUR=0; $D::protect(['billing_phone'=>'01999888777'],$e); ok($e->e===[],'main checkout not checked unless turned on');
$SET['protect_main']=true; $e=new Err; $D::protect(['billing_phone'=>'01999888777'],$e); ok(count($e->e)===1,'main checkout checked when turned on');
$SET['protect']=false; $e=new Err; $D::protect(['billing_phone'=>'01999888777'],$e); ok($e->e===[],'protection off: nothing checked');
$SET['protect']=true; $SET['protect_block']=''; $SET['protect_hours']=24; $SET['protect_max']=0; $CUR=5;
$e=new Err; $D::protect(['billing_phone'=>'01712345678'],$e); ok($e->e===[] && $WC->session->get('cartiva_risk'),'flag mode: order goes through, risk remembered');
$o=new Order; $o->phone='01712345678'; $D::tag_order($o); ok(str_starts_with($o->meta['_cartiva_risk'],'Check before shipping') && count($o->notes)===1 && !$WC->session->get('cartiva_risk'),'flagged order gets a note and the flag is cleared');
$e=new Err; $e->add('x','other'); $D::protect(['billing_phone'=>'01999888777'],$e); ok(count($e->e)===1,'skips when the form already has errors');

// Order source
$o=new WC_Order; ok($O::source($o)['type']==='main' && str_contains($O::badge($o),'Main site'),'order without landing ID: Main site');
$o=new WC_Order; $o->meta['_cartiva_landing_id']=5; ok($O::source($o)['type']==='landing' && str_contains($O::badge($o),'Landing page') && str_contains($O::badge($o),'Title 5'),'landing order shows page name');
$FUN[22]=30; $o=new WC_Order; $o->meta=['_cartiva_landing_id'=>22,'_cartiva_offers'=>[40=>'yes'],'_cartiva_risk'=>'Check before shipping: x']; $o->items=[new Item('Cap',['_cartiva_bump'=>'1'])];
$b=$O::badge($o); ok($O::source($o)['funnel']===30 && str_contains($b,'Funnel') && str_contains($b,'Title 30') && str_contains($b,'Upsell, Bump, Check'),'funnel order: funnel name, upsell, bump and check flags');
ok(array_keys($O::columns(['cb'=>1,'order_number'=>1,'order_status'=>1,'order_total'=>1]))===['cb','order_number','order_status','cartiva_source','order_total'],'Source column after Status');
ok(isset($O::columns(['a'=>1])['cartiva_source']),'Source column added even without Status');
ok($O::meta_query_for('main')===['key'=>'_cartiva_landing_id','compare'=>'NOT EXISTS'],'main site filter');
ok($O::meta_query_for('cartiva')['compare']==='EXISTS','landing + funnel filter');
ok($O::meta_query_for('l5')['value']===['5'] && $O::meta_query_for('f30')['value']===['21','22'],'one page / one funnel filters');
ok($O::meta_query_for('xyz')===null && $O::meta_query_for('')===null,'unknown filter ignored');
$_GET['cartiva_source']='main'; $a=$O::filter_hpos(['limit'=>20]); ok($a['meta_query'][0]['compare']==='NOT EXISTS' && $a['limit']===20,'HPOS list query gets the filter');
$_GET['cartiva_source']=''; ok($O::filter_hpos(['x'=>1])===['x'=>1],'no filter: query untouched');
ob_start(); $O::render_box($o); $h=ob_get_clean(); ok(str_contains($h,'accepted') && str_contains($h,'Order bump') && str_contains($h,'cartiva-order-risk'),'order box lists upsell answer, bump and check note');

echo "\n$pass passed, $fail failed\n";
