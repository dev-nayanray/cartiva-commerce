<?php
// Minimal WordPress/WooCommerce stubs to test Cartiva Commerce logic.
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR', dirname(__DIR__).'/'); define('CARTIVA_COMMERCE_URL','x/'); define('CARTIVA_COMMERCE_VERSION','1'); define('CARTIVA_COMMERCE_FILE','x');
$GLOBALS['F']=[]; function add_filter($h,$c,$p=10,$a=1){$GLOBALS['F'][$h][]=$c;} function add_action($h,$c,$p=10,$a=1){add_filter($h,$c);} function apply_filters($h,$v){return $v;}
function __($s){return $s;} function esc_html__($s){return $s;} function esc_attr($s){return htmlspecialchars($s);} function esc_html($s){return htmlspecialchars($s);}
function sanitize_text_field($s){return trim(strip_tags($s));} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));} function absint($n){return abs((int)$n);} function wp_unslash($v){return $v;}
function wp_parse_args($a,$d){return array_merge($d,$a);} function wp_parse_str($s,&$o){parse_str($s,$o);}
$OPT=[]; function get_option($k,$d=false){global $OPT;return $OPT[$k]??$d;}
$META=[]; function get_post_meta($id,$k,$s){global $META;return $META[$id][$k]??'';}
$POSTS=[]; function get_post_type($id){global $POSTS;return $POSTS[$id]['type']??false;} function get_post_status($id){global $POSTS;return $POSTS[$id]['status']??false;}
function current_user_can(){return false;} $DIDWP=false; function did_action($a){global $DIDWP;return $DIDWP;} $SINGULAR=0; function is_singular($t){global $SINGULAR;return $SINGULAR>0;} function get_queried_object_id(){global $SINGULAR;return $SINGULAR;}
class Countries{function get_base_country(){return 'BD';}} class WCX{public $countries; function __construct(){$this->countries=new Countries;}} function WC(){return new WCX;}
require CARTIVA_COMMERCE_DIR.'includes/functions.php';
require CARTIVA_COMMERCE_DIR.'includes/class-fields.php';
require CARTIVA_COMMERCE_DIR.'includes/class-checkout.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c){$pass++;} else {$fail++; echo "FAIL: $m\n";}}

// Realistic subset of WooCommerce classic checkout fields.
function wc_fields(){ $b=[]; foreach(['billing_first_name'=>10,'billing_last_name'=>20,'billing_company'=>30,'billing_country'=>40,'billing_address_1'=>50,'billing_address_2'=>60,'billing_city'=>70,'billing_state'=>80,'billing_postcode'=>90,'billing_phone'=>100,'billing_email'=>110] as $k=>$p) $b[$k]=['label'=>$k,'required'=>true,'class'=>['form-row-wide','address-field'],'priority'=>$p];
 return ['billing'=>$b,'shipping'=>['shipping_first_name'=>['label'=>'s']],'account'=>[],'order'=>['order_comments'=>['type'=>'textarea','label'=>'Notes','class'=>['notes']]]]; }

// Landing page 7 (BD COD preset), landing 8 (custom), post 9 is a normal page.
$POSTS=[7=>['type'=>'cartiva_landing','status'=>'publish'],8=>['type'=>'cartiva_landing','status'=>'publish'],9=>['type'=>'page','status'=>'publish'],10=>['type'=>'cartiva_landing','status'=>'draft']];
$META[7]['_cartiva_landing']=['preset'=>'bd_cod','gateways'=>['cod'],'bd_phone'=>true];
$custom=Cartiva_Commerce_Fields::config_for('bd_cod'); $custom['billing_email']['enabled']=true; $custom['billing_email']['required']=false; $custom['billing_full_name']['label']='Name';
$META[8]['_cartiva_landing']=['preset'=>'custom','fields'=>$custom];
$OPT['cartiva_commerce_settings']=['main_preset'=>'minimal'];

// 1. Presets
$bd=Cartiva_Commerce_Fields::config_for('bd_cod');
$on=array_keys(array_filter($bd,fn($r)=>$r['enabled']));
ok($on===['billing_full_name','billing_address_1','billing_phone'] || count(array_diff($on,['billing_full_name','billing_address_1','billing_phone']))===0 && count($on)===3, 'bd_cod shows exactly name, address, phone: '.implode(',',$on));
ok(Cartiva_Commerce_Fields::config_for('default')===null,'default preset leaves fields untouched');

// 2. Apply BD COD to WooCommerce fields
$f=Cartiva_Commerce_Fields::apply(wc_fields(),$bd);
ok(array_keys($f['billing'])===['billing_full_name','billing_phone','billing_address_1'],'bd_cod billing fields + order: '.implode(',',array_keys($f['billing'])));
ok($f['billing']['billing_phone']['label']==='Mobile number' && $f['billing']['billing_phone']['type']==='tel','phone relabelled and tel type');
ok(empty($f['order']),'order notes hidden in bd_cod');
ok($f['billing']['billing_full_name']['required']===true && $f['billing']['billing_full_name']['class'][0]==='form-row-wide','full name required, full width');
ok(!in_array('form-row-wide',array_slice($f['billing']['billing_address_1']['class'],1)),'no duplicate width class');

// 3. Posted data completion: full name split, country default, shipping copy
$d=Cartiva_Commerce_Fields::complete_posted_data(['billing_full_name'=>'  Md  Rahim   Uddin ','billing_phone'=>'01712345678','billing_address_1'=>'Road 5, Dhanmondi','ship_to_different_address'=>false],$bd);
ok($d['billing_first_name']==='Md Rahim' && $d['billing_last_name']==='Uddin','full name split: '.$d['billing_first_name'].' | '.$d['billing_last_name']);
ok($d['billing_country']==='BD','hidden country filled with store country');
ok($d['shipping_address_1']==='Road 5, Dhanmondi' && $d['shipping_first_name']==='Md Rahim','shipping copied from billing');
ok(isset($d['billing_full_name']),'full name kept for required-field validation');
$d1=Cartiva_Commerce_Fields::complete_posted_data(['billing_full_name'=>'Rahim'],$bd); ok($d1['billing_first_name']==='Rahim' && $d1['billing_last_name']==='','single-word name');

// 4. Sanitizing: unknown keys dropped, bad width, name safety net, full name disables first/last
$s=Cartiva_Commerce_Fields::sanitize_config(['evil'=>['enabled'=>1],'billing_phone'=>['enabled'=>1,'width'=>'huge','label'=>'<b>Mobile</b>','priority'=>'5000']]);
ok(!isset($s['evil']) && $s['billing_phone']['width']==='full' && $s['billing_phone']['label']==='Mobile' && $s['billing_phone']['priority']===999,'sanitize drops unknown, clamps width/priority, strips tags');
ok($s['billing_full_name']['enabled']===true,'a name field is always kept');
$s2=Cartiva_Commerce_Fields::sanitize_config(['billing_full_name'=>['enabled'=>1],'billing_first_name'=>['enabled'=>1],'billing_last_name'=>['enabled'=>1]]);
ok(!$s2['billing_first_name']['enabled'] && !$s2['billing_last_name']['enabled'],'full name replaces first/last');

// 5. Context: which rules apply to which request (THE isolation guarantee)
function ctx_fields(){ return array_keys(Cartiva_Commerce_Checkout::fields(wc_fields())['billing']); }
$_POST=[]; $SINGULAR=0; $DIDWP=false;
ok(ctx_fields()===['billing_full_name','billing_email','billing_phone'],'main checkout uses MAIN preset (minimal): '.implode(',',ctx_fields()));
$_POST=['_cartiva_landing_id'=>'7'];
ok(ctx_fields()===['billing_full_name','billing_phone','billing_address_1'],'Place order from landing 7 uses ITS fields: '.implode(',',ctx_fields()));
$_POST=['security'=>'x','post_data'=>'billing_full_name=A&_cartiva_landing_id=8&billing_phone=1'];
$k=ctx_fields(); ok(in_array('billing_email',$k) && count($k)===4,'live totals from landing 8 use ITS custom fields: '.implode(',',$k));
$_POST=['_cartiva_landing_id'=>'9']; ok(ctx_fields()===['billing_full_name','billing_email','billing_phone'],'a non-landing ID falls back to main rules');
$_POST=['_cartiva_landing_id'=>'10']; ok(ctx_fields()===['billing_full_name','billing_email','billing_phone'],'a draft landing (visitor) falls back to main rules');
$_POST=[]; $DIDWP=true; $SINGULAR=7; ok(ctx_fields()===['billing_full_name','billing_phone','billing_address_1'],'landing page render uses its fields');
$SINGULAR=0; $DIDWP=false;
$OPT['cartiva_commerce_settings']=['main_preset'=>'default']; $_POST=[];
ok(count(ctx_fields())===11,'main preset "default" leaves all 11 WooCommerce fields');
$_POST=['_cartiva_landing_id'=>'7']; ok(count(ctx_fields())===3,'...while landing 7 still shows 3 fields');

// 6. Gateways
$_POST=['_cartiva_landing_id'=>'7'];
$g=Cartiva_Commerce_Checkout::gateways(['cod'=>1,'bacs'=>1,'bkash'=>1]); ok(array_keys($g)===['cod'],'landing 7 offers only COD');
$META[7]['_cartiva_landing']['gateways']=['stripe'];
$g=Cartiva_Commerce_Checkout::gateways(['cod'=>1,'bacs'=>1]); ok(count($g)===2,'never left without a payment method');
$_POST=[]; $g=Cartiva_Commerce_Checkout::gateways(['cod'=>1,'bacs'=>1]); ok(count($g)===2,'main checkout gateways untouched');

// 7. Phone validation
foreach(['01712345678','+8801712345678','8801912-345678','017 1234 5678'] as $p) ok(cartiva_commerce_is_bd_mobile($p),"valid BD mobile $p");
foreach(['0171234567','02123456789','01212345678','1712345678','hello'] as $p) ok(!cartiva_commerce_is_bd_mobile($p),"invalid BD mobile $p");
class Err{public $e=[]; function add($c,$m){$this->e[]=$m;}}
$_POST=['_cartiva_landing_id'=>'7']; $e=new Err; Cartiva_Commerce_Checkout::validate(['billing_phone'=>'12345'],$e); ok(count($e->e)===1,'landing rejects bad mobile');
$_POST=[]; $e=new Err; Cartiva_Commerce_Checkout::validate(['billing_phone'=>'12345'],$e); ok(count($e->e)===0,'main checkout does not check BD format unless enabled');

// 8. Shipping address not required on landing forms with custom fields
$_POST=['_cartiva_landing_id'=>'7']; ok(Cartiva_Commerce_Checkout::needs_shipping_address(true)===false,'landing ships to billing address');
$_POST=[]; ok(Cartiva_Commerce_Checkout::needs_shipping_address(true)===true,'main checkout shipping unchanged');

echo "\n$pass passed, $fail failed\n";
