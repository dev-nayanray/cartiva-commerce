<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('HOUR_IN_SECONDS',3600); define('DAY_IN_SECONDS',86400); define('MINUTE_IN_SECONDS',60);
function add_filter(){} function add_action(){} $ACTIONS=[]; function do_action($h,...$a){global $ACTIONS;$ACTIONS[]=[$h,$a];} function apply_filters($h,$v){return $v;} function __($s){return $s;} function esc_html__($s){return $s;}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);} function esc_url($s){return str_replace('&','&#038;',$s);} function wpautop($s){return '<p>'.str_replace("\n\n",'</p><p>',$s).'</p>';}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_text_field($s){return trim(strip_tags($s));} function sanitize_email($s){return filter_var(trim($s),FILTER_SANITIZE_EMAIL);} function is_email($s){return (bool)filter_var($s,FILTER_VALIDATE_EMAIL);}
function wp_strip_all_tags($s){return strip_tags($s);} function wc_price($p,$a=[]){return '<span>&#2547;&nbsp;'.number_format($p,2).'</span>';} function get_bloginfo(){return 'Rasidul &amp; Co';} function wp_specialchars_decode($s){return htmlspecialchars_decode($s);}
function home_url($p){return 'https://shop.test'.$p;} function add_query_arg($a,$u){return $u.'?'.http_build_query($a);} function date_i18n($f,$t){return date('Y-m-d',$t);} function get_option($k,$d=false){return ['date_format'=>'Y-m-d'][$k]??$d;}
function wp_generate_password($n){return str_repeat('k',$n);} $SETTINGS=['recovery_after'=>30,'recovery_keep'=>30,'coupon_percent'=>15,'coupon_days'=>3,'emails'=>[],'whatsapp_text'=>'']; function cartiva_commerce_setting($k){global $SETTINGS;return $SETTINGS[$k]??null;} function cartiva_commerce_base_country(){return 'BD';}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';} function update_post_meta($id,$k,$v){global $META;$META[$id][$k]=$v;}
function get_posts($a){ global $META; if(isset($a['date_query'])) return []; $out=[]; foreach($META as $id=>$m){ $st=$m['_status']??''; $want=$a['meta_query'][0]['value']??null; if(is_array($want)? in_array($st,$want,true) : true) $out[]=$id; } return $out; }
function wp_delete_post(){}
class WC_Coupon{ public static $saved=[]; public $d=[]; function __call($n,$a){ $this->d[$n]=$a[0]; } function save(){ self::$saved[]=$this->d; } }
class Mailer{ public $sent=[]; function wrap_message($h,$b){return "<h1>$h</h1>$b";} function send($to,$s,$html,$h){ $this->sent[]=compact('to','s','html'); return true; } }
$MAILER=new Mailer; function WC(){ global $MAILER; return new class($MAILER){ public $m; function __construct($m){$this->m=$m;} function mailer(){return $this->m;} }; }
define('CARTIVA_COMMERCE_RECOVERY','cartiva_recovery');
// Only the helper functions from functions.php that recovery needs:
$src=file_get_contents(CARTIVA_COMMERCE_DIR.'includes/functions.php'); foreach(['cartiva_commerce_whatsapp_number','cartiva_commerce_fill'] as $fn){ preg_match('/function '.$fn.'\(.*?\n}\n/s',$src,$m); eval($m[0]); }
require CARTIVA_COMMERCE_DIR.'includes/class-recovery.php';
$pass=0;$fail=0; function to($w){global $MAILER; return array_values(array_filter($MAILER->sent,fn($m)=>$m['to']===$w));}
function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}

// 1. WhatsApp numbers
ok(cartiva_commerce_whatsapp_number('01712-345678','BD')==='8801712345678','BD local -> 880');
ok(cartiva_commerce_whatsapp_number('+880 1712 345678','BD')==='8801712345678','international kept');
ok(cartiva_commerce_whatsapp_number('008801712345678','BD')==='8801712345678','00 prefix');
ok(cartiva_commerce_whatsapp_number('8801712345678','BD')==='8801712345678','already with code');
ok(cartiva_commerce_whatsapp_number('12345','BD')==='','too short');
ok(cartiva_commerce_whatsapp_number('09876543210','IN')==='919876543210','India local');

// 2. Details cleaning and contactability
$d=Cartiva_Commerce_Recovery::clean_details(['billing_first_name'=>'Rahim','billing_last_name'=>'Uddin','billing_phone'=>'01712345678','billing_email'=>' bad<x>@ex.com ','evil'=>'x','billing_city'=>['array']]);
ok($d['billing_full_name']==='Rahim Uddin','full name built from first+last');
ok(!isset($d['evil']) && !isset($d['billing_city']),'unknown and non-text fields dropped');
ok(Cartiva_Commerce_Recovery::contactable(['billing_phone'=>'01712 345678']),'phone is enough');
ok(Cartiva_Commerce_Recovery::contactable(['billing_email'=>'a@b.co']),'email is enough');
ok(!Cartiva_Commerce_Recovery::contactable(['billing_full_name'=>'A','billing_phone'=>'0171']),'name alone is not');
ok(Cartiva_Commerce_Recovery::phone_key('+880 1712-345678')===Cartiva_Commerce_Recovery::phone_key('01712345678'),'same buyer matched across phone formats');

// 3. Background job: open -> abandoned after 30 min, then emails in order
$now=1800000000;
$META=[1=>['_status'=>'open','_updated'=>$now-31*60,'_details'=>['billing_full_name'=>'Rahim Uddin','billing_email'=>'rahim@example.com','billing_phone'=>'01712345678'],'_cart'=>['names'=>'Smart Watch × 2','total'=>3600,'currency'=>'BDT'],'_token'=>str_repeat('t',32)],
       2=>['_status'=>'open','_updated'=>$now-10*60,'_details'=>['billing_email'=>'x@example.com']],
       3=>['_status'=>'open','_updated'=>$now-60*60,'_details'=>['billing_phone'=>'01812345678']],
       4=>['_status'=>'recovered','_updated'=>$now-99999,'_details'=>['billing_email'=>'y@example.com']]];
Cartiva_Commerce_Recovery::run($now);
ok($META[1]['_status']==='abandoned' && $META[1]['_abandoned']===$now,'31 min without order -> abandoned');
ok($META[2]['_status']==='open','10 min -> still filling in');
ok($META[1]['_phone_key']==='1712345678' && $META[1]['_email_key']==='rahim@example.com','match keys stored');
ok(count(array_filter($ACTIONS,fn($a)=>$a[0]==='cartiva_commerce_checkout_abandoned'))===2,'abandoned hook fired for each (SMS plugins)');
ok(empty($MAILER->sent),'no email right away (first after 1 hour)');
Cartiva_Commerce_Recovery::run($now+HOUR_IN_SECONDS);
ok(count($MAILER->sent)===1 && $MAILER->sent[0]['to']==='rahim@example.com','email 1 after 1 hour');
ok(!isset($META[3]['_sent']),'phone-only shopper gets no email (call / WhatsApp instead)');
Cartiva_Commerce_Recovery::run($now+2*HOUR_IN_SECONDS); ok(count(to('rahim@example.com'))===1 && count(to('x@example.com'))===1,'email 2 not before 24 hours (and the later shopper got email 1 on her own clock)');
Cartiva_Commerce_Recovery::run($now+25*HOUR_IN_SECONDS); ok(count(to('rahim@example.com'))===2 && $META[1]['_sent_count']===2,'email 2 after 24 hours');
Cartiva_Commerce_Recovery::run($now+100*HOUR_IN_SECONDS); ok(count(to('rahim@example.com'))===2,'email 3 is off by default');
$SETTINGS['emails']=[2=>['enabled'=>true]]; Cartiva_Commerce_Recovery::run($now+100*HOUR_IN_SECONDS);
ok(count(to('rahim@example.com'))===3,'email 3 when switched on');
$m=to('rahim@example.com')[2];
ok(str_contains($m['html'],'CARTIVA-KKKKKKKK') && str_contains($m['html'],'15%'),'coupon code and discount in email 3');
ok(WC_Coupon::$saved[0]['set_amount']===15 && WC_Coupon::$saved[0]['set_usage_limit']===1 && WC_Coupon::$saved[0]['set_email_restrictions']===['rahim@example.com'],'one-time 15% coupon locked to the email');
$m=$MAILER->sent[0];
ok(str_contains($m['html'],'Hi Rahim,') && str_contains($m['html'],'Smart Watch × 2'),'name and products filled in');
ok(str_contains($m['html'],'cartiva_recover='.str_repeat('t',32)) && str_contains($m['html'],'stop=1'),'recovery link and stop link');
ok(str_contains($m['html'],'Rasidul &amp; Co') && !str_contains($m['html'],'&amp;amp;'),'site name escaped once');
$META[5]=['_status'=>'abandoned','_abandoned'=>$now,'_details'=>['billing_email'=>'z@example.com'],'_stop'=>1]; ok(Cartiva_Commerce_Recovery::due_email(5,$now+5*HOUR_IN_SECONDS)===null,'unsubscribed: no more emails');

// 4. XSS: shopper-typed name cannot inject HTML into the email
$META[6]=['_status'=>'abandoned','_abandoned'=>$now-2*HOUR_IN_SECONDS,'_details'=>['billing_full_name'=>'<script>x</script>','billing_email'=>'q@example.com'],'_cart'=>['names'=>'<img src=x onerror=1>','total'=>1],'_token'=>str_repeat('u',32)];
Cartiva_Commerce_Recovery::send(6,0); $h=end($MAILER->sent)['html'];
ok(!str_contains($h,'<script>') && !str_contains($h,'<img'),'typed HTML is escaped in emails');

// 5. WhatsApp link
$wa=Cartiva_Commerce_Recovery::whatsapp_link(1);
ok(str_starts_with($wa,'https://wa.me/8801712345678?text=') && str_contains(rawurldecode($wa),'Hello Rahim') && str_contains(rawurldecode($wa),'cartiva_recover='),'WhatsApp link with number, name and recovery link');

echo "\n$pass passed, $fail failed\n";
