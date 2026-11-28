<?php
// SMS gateways, order messages, phone codes and the SMS reminder.
define('ABSPATH','/'); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60); define('WEEK_IN_SECONDS',604800);
function add_action(){} function add_filter(){} function apply_filters($h,$v){return $v;} function do_action(){} function __($s){return $s;} function esc_html__($s){return $s;} function esc_html_e($s){echo $s;} function _n($a,$b,$n){return $n==1?$a:$b;}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function wp_json_encode($d){return json_encode($d);} function wp_strip_all_tags($s){return strip_tags($s);}
function wp_specialchars_decode($s){return htmlspecialchars_decode($s);} function get_bloginfo(){return 'Rong Shop';} function wc_price($p){return '<span>৳&nbsp;'.number_format($p,2).'</span>';} function sanitize_text_field($s){return trim(strip_tags($s));} function wp_unslash($v){return $v;} function add_query_arg($a,$b=null,$c=null){return is_array($a)?$b.'?'.http_build_query($a):$c.'?'.$a.'='.$b;}
function is_wp_error($x){return $x instanceof WP_Error;} class WP_Error{public $m; function __construct($c,$m){$this->m=$m;} function get_error_message(){return $this->m;}}
$TR=[]; function set_transient($k,$v,$t=0){global $TR;$TR[$k]=$v;} function get_transient($k){global $TR;return $TR[$k]??false;} function delete_transient($k){global $TR;unset($TR[$k]);}
function wp_hash($d){return hash('sha256','salt'.$d);} $RAND=4821; function wp_rand($a,$b){global $RAND;return $RAND;}
$SET=[]; function cartiva_commerce_settings(){global $SET;return $SET;} function cartiva_commerce_setting($k){global $SET;return $SET[$k]??null;} $LAND=0; function cartiva_commerce_current_landing_id(){global $LAND;return $LAND;}
$SENT=[]; $ANSWER=null; function wp_remote_post($u,$a){global $SENT,$ANSWER;$SENT[]=['post',$u,$a]; return $ANSWER;} function wp_remote_get($u,$a){global $SENT,$ANSWER;$SENT[]=['get',$u,$a]; return $ANSWER;}
function wp_remote_retrieve_response_code($r){return $r['code'];} function wp_remote_retrieve_body($r){return $r['body'];}
class Sess{public $d=[]; function get($k){return $this->d[$k]??null;} function set($k,$v){$this->d[$k]=$v;}} $SESS=new Sess; function WC(){global $SESS; return new class($SESS){public $session; function __construct($s){$this->session=$s;}};}
$PM=[]; function get_post_meta($id,$k,$s=true){global $PM;return $PM[$id][$k]??'';} function update_post_meta($id,$k,$v){global $PM;$PM[$id][$k]=$v;}
class Cartiva_Commerce_Courier{ static function phone($p){$d=preg_replace('/\D/','',(string)$p); if(strlen($d)===13&&str_starts_with($d,'880'))$d=substr($d,2); elseif(strlen($d)===10&&$d[0]==='1')$d='0'.$d; return $d;} static function providers(){return ['steadfast'=>'Steadfast'];} static function tracking_url($o){return 'https://steadfast.com.bd/t/'.$o->get_meta('_cartiva_tracking');} }
class Cartiva_Commerce_Recovery{ static function phone_key($p){$d=preg_replace('/\D/','',(string)$p);return strlen($d)>=10?substr($d,-10):'';} static function meta_array($id,$k){$v=get_post_meta($id,$k);return is_array($v)?$v:[];} static function link($id){return "https://shop.test/?cartiva_recover=tok$id";} }
class WC_Order{ public $meta=[]; public $notes=[]; public $phone='01712345678'; function get_billing_first_name(){return 'Rahim';} function get_billing_last_name(){return 'Uddin';} function get_order_number(){return '1001';} function get_total(){return 1250;} function get_currency(){return 'BDT';} function get_billing_phone(){return $this->phone;} function get_meta($k){return $this->meta[$k]??'';} function update_meta_data($k,$v){$this->meta[$k]=$v;} function add_order_note($n){$this->notes[]=$n;} function save(){} function get_id(){return 9;} }
class Err{public $e=[]; public $d=[]; function add($c,$m,$d=null){$this->e[]=$m;$this->d[]=$d;} function has_errors(){return (bool)$this->e;}}
require CARTIVA_COMMERCE_DIR.'includes/class-sms.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$S='Cartiva_Commerce_SMS';
$base=['sms_gateway'=>'','sms_key'=>'','sms_sender'=>'','sms_ssl_sid'=>'','sms_custom_url'=>'','sms_shop_phone'=>'','sms_on_order'=>true,'sms_on_courier'=>true,'sms_on_delivered'=>false,'sms_on_admin'=>false,'sms_on_recovery'=>false,'sms_recovery_after'=>30,'sms_tpl_order'=>'','sms_tpl_courier'=>'','sms_tpl_delivered'=>'','sms_tpl_admin'=>'','sms_tpl_otp'=>'','sms_tpl_recovery'=>'','otp_landing'=>false,'otp_main'=>false,'otp_all_methods'=>false,'otp_remember'=>30];
$SET=$base;
ok(!$S::ready(),'no gateway: not ready');
$SET['sms_gateway']='bulksmsbd'; $SET['sms_key']='K'; ok(!$S::ready(),'BulkSMSBD needs a sender ID'); $SET['sms_sender']='8809617';
ok($S::ready(),'BulkSMSBD ready');
ok($S::msisdn('01712-345678')==='8801712345678' && $S::msisdn('+8801812345678')==='8801812345678' && $S::msisdn('0161')==='' && $S::msisdn('01212345678')==='','numbers as 8801XXXXXXXXX; bad ones refused');
// BulkSMSBD
$ANSWER=['code'=>200,'body'=>json_encode(['response_code'=>202,'message_id'=>1,'success_message'=>'SMS Submitted Successfully','error_message'=>''])];
$r=$S::send('01712345678','Hello'); $c=end($SENT);
ok($r['ok'] && $c[1]==='https://bulksmsbd.net/api/smsapi' && $c[2]['body']['number']==='8801712345678' && $c[2]['body']['senderid']==='8809617' && $c[2]['body']['api_key']==='K','BulkSMSBD request');
$ANSWER=['code'=>200,'body'=>json_encode(['response_code'=>1007,'error_message'=>'Balance Insufficient'])]; $r=$S::send('01712345678','Hello'); ok(!$r['ok'] && $r['error']==='Balance Insufficient','BulkSMSBD error explained');
ok(!$S::send('12345','Hello')['ok'],'bad number not sent');
// Alpha
$SET['sms_gateway']='alpha'; $SET['sms_sender']=''; $ANSWER=['code'=>200,'body'=>json_encode(['error'=>0,'msg'=>'Request successfully submitted','data'=>['request_id'=>5]])];
$r=$S::send('01712345678','Hi'); $c=end($SENT); ok($r['ok'] && $c[1]==='https://api.sms.net.bd/sendsms' && $c[2]['body']['to']==='8801712345678' && !isset($c[2]['body']['sender_id']),'Alpha SMS request');
$ANSWER=['code'=>200,'body'=>json_encode(['error'=>405,'msg'=>'Authorization Required'])]; ok($S::send('01712345678','Hi')['error']==='Authorization Required','Alpha error explained');
// SSL
$SET['sms_gateway']='ssl'; ok(!$S::ready(),'SSL needs the SID'); $SET['sms_ssl_sid']='SHOPBRAND';
$ANSWER=['code'=>200,'body'=>json_encode(['status'=>'SUCCESS','status_code'=>200,'error_message'=>'','smsinfo'=>[]])]; $r=$S::send('01712345678','Hi'); $c=end($SENT); $b=json_decode($c[2]['body'],true);
ok($r['ok'] && $c[1]==='https://smsplus.sslwireless.com/api/v3/send-sms' && $b['sid']==='SHOPBRAND' && $b['msisdn']==='8801712345678' && strlen($b['csms_id'])<=20,'SSL Wireless request');
$ANSWER=['code'=>200,'body'=>json_encode(['status'=>'FAILED','status_code'=>4003,'error_message'=>'IP Blacklisted'])]; ok($S::send('01712345678','Hi')['error']==='IP Blacklisted','SSL error explained');
// Custom
$SET['sms_gateway']='custom'; $SET['sms_custom_url']='https://gw.test/send?key=A&to={phone}'; ok(!$S::ready(),'custom link needs {phone} and {message}');
$SET['sms_custom_url']='https://gw.test/send?key=A&to={phone}&text={message}'; $ANSWER=['code'=>200,'body'=>'OK']; $r=$S::send('01712345678','অর্ডার #1'); $c=end($SENT);
ok($r['ok'] && $c[0]==='get' && $c[1]==='https://gw.test/send?key=A&to=8801712345678&text='.rawurlencode('অর্ডার #1'),'custom gateway link, Bangla text encoded');
$ANSWER=new WP_Error('x','timed out'); ok($S::send('01712345678','x')['error']==='timed out','network error shown');
$ANSWER=['code'=>200,'body'=>'OK'];
// Messages
$o=new WC_Order; $o->meta['_cartiva_tracking']='T1';
ok($S::fill($S::text('order'),$S::order_values($o))==='Dear Rahim Uddin, we got your order #1001 of ৳ 1,250.00. We will call you to confirm. - Rong Shop','order message filled (price without HTML)');
$SET['sms_tpl_order']='প্রিয় {name}, অর্ডার #{order} পেয়েছি।'; ok($S::fill($S::text('order'),$S::order_values($o))==='প্রিয় Rahim Uddin, অর্ডার #1001 পেয়েছি।','custom Bangla text');
$SENT=[]; $S::order_placed(9,[],$o); ok(count($SENT)===1 && $o->meta['_cartiva_sms_order'] && str_contains($o->notes[0],'SMS sent (Order received) to 8801712345678'),'order SMS sent and noted');
$S::order_placed(9,[],$o); ok(count($SENT)===1,'never twice');
$SET['sms_on_admin']=true; $SET['sms_shop_phone']='01899999999'; $o2=new WC_Order; $SENT=[]; $S::order_placed(9,[],$o2); ok(count($SENT)===2 && str_contains($SENT[1][1],'to=8801899999999'),'shop gets a new-order alert');
$SET['sms_on_order']=false; $o3=new WC_Order; $SENT=[]; $S::order_placed(9,[],$o3); ok(count($SENT)===1,'customer SMS off: only the alert');
$S::parcel_sent($o); ok(str_contains(urldecode(end($SENT)[1]),'Track: https://steadfast.com.bd/t/T1'),'courier SMS with tracking link');
$n=count($SENT); $S::parcel_status($o,'delivered'); ok(count($SENT)===$n,'delivered SMS off by default'); $SET['sms_on_delivered']=true; $S::parcel_status($o,'transit'); ok(count($SENT)===$n,'only on delivered'); $S::parcel_status($o,'delivered'); ok(count($SENT)===$n+1,'delivered SMS');
$ANSWER=['code'=>500,'body'=>'']; $o4=new WC_Order; $SET['sms_on_order']=true; $S::order_message($o4,'order'); ok(empty($o4->meta['_cartiva_sms_order']) && str_contains($o4->notes[0],'SMS not sent'),'failure noted, can be retried'); $ANSWER=['code'=>200,'body'=>'OK'];
// Recovery reminder
$now=1764000000; $PM[50]=['_abandoned'=>$now-600,'_details'=>['billing_phone'=>'01712345678','billing_full_name'=>'Karim']];
ok(!$S::recovery_reminder(50,$now),'reminder off by default'); $SET['sms_on_recovery']=true;
ok(!$S::recovery_reminder(50,$now),'too early (30 minutes)');
$SENT=[]; ok($S::recovery_reminder(50,$now+1800) && str_contains(urldecode($SENT[0][1]),'Hi Karim') && str_contains(urldecode($SENT[0][1]),'cartiva_recover=tok50'),'reminder with link');
ok(!$S::recovery_reminder(50,$now+9999) && count($SENT)===1,'reminder only once');
$PM[51]=['_abandoned'=>$now-9999,'_details'=>['billing_email'=>'a@b.c']]; ok(!$S::recovery_reminder(51,$now) && $PM[51]['_sms_sent']===-1,'no phone: skipped for good');
// Phone codes
$SET['otp_landing']=true; $LAND=5; ok($S::otp_here(),'codes on landing forms'); $LAND=0; ok(!$S::otp_here(),'main checkout off'); $LAND=5;
$e=new Err; $SENT=[]; $S::otp_check(['payment_method'=>'cod','billing_phone'=>'01712345678'],$e);
ok(count($e->e)===1 && $e->d[0]['id']==='cartiva_otp' && str_contains($e->e[0],'we sent a code by SMS to 01712345678') && str_contains(urldecode($SENT[0][1]),'code is 4821'),'first try: code sent, order held');
$e=new Err; $S::otp_check(['payment_method'=>'cod','billing_phone'=>'01712345678'],$e); ok(str_contains($e->e[0],'Please wait') && count($SENT)===1,'no new code within a minute');
$_POST['cartiva_otp']='1111'; $e=new Err; $S::otp_check(['payment_method'=>'cod','billing_phone'=>'01712345678'],$e); ok(str_contains($e->e[0],'not right'),'wrong code refused');
$_POST['cartiva_otp']='48 21'; $e=new Err; $S::otp_check(['payment_method'=>'cod','billing_phone'=>'01712345678'],$e); ok($e->e===[] && $S::verified('1712345678'),'right code: order goes through');
unset($_POST['cartiva_otp']); $e=new Err; $S::otp_check(['payment_method'=>'cod','billing_phone'=>'+880 1712-345678'],$e); ok($e->e===[],'same number later: not asked again');
$SESS->d=[]; ok($S::verified('1712345678'),'remembered after the visit (30 days)');
$SET['otp_remember']=0; $TR=[]; $SESS->d=[];
$e=new Err; $S::otp_check(['payment_method'=>'bacs','billing_phone'=>'01812345678'],$e); ok($e->e===[],'paid orders not asked');
$SET['otp_all_methods']=true; $e=new Err; $S::otp_check(['payment_method'=>'bacs','billing_phone'=>'01812345678'],$e); ok(count($e->e)===1,'unless turned on for all methods'); $SET['otp_all_methods']=false;
$e=new Err; $e->add('x','Phone is required'); $S::otp_check(['payment_method'=>'cod','billing_phone'=>''],$e); ok(count($e->e)===1,'other errors first: no code sent');
// tries and expiry
$SESS->d=[]; $TR=[]; $RAND=7777; $S::send_code('01912345678'); for($i=0;$i<5;$i++){ $S::check_code('01912345678','0000'); } ok($S::check_code('01912345678','7777')==='expired','five wrong tries: code dead');
$SESS->d=[]; $S::send_code('01612345678'); $SESS->d['cartiva_otp']['sent']-=601; ok($S::check_code('01612345678','7777')==='expired','code expires after 10 minutes');
$SESS->d=[]; $S::send_code('01512345678'); ok($S::check_code('01712345678','7777')==='expired','code is for its own number only');
$TR=[]; $SESS->d=[]; $cnt=0; for($i=0;$i<6;$i++){ if($S::send_code('01312345678')['ok']) $cnt++; $SESS->d['cartiva_otp']['sent']-=61; } ok($cnt===4,'at most 4 codes an hour per number');
$ANSWER=['code'=>500,'body'=>'']; $SESS->d=[]; $TR=[]; ok(str_contains($S::send_code('01412345678')['error'],'could not send'),'gateway down: clear message');
ob_start(); $S::otp_field(); $h=ob_get_clean(); ok(str_contains($h,'name="cartiva_otp"') && str_contains($h,'autocomplete="one-time-code"') && str_contains($h,' hidden'),'hidden code box on the form');
echo "\n$pass passed, $fail failed\n";
