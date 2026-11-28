<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('CARTIVA_COMMERCE_VERSION','1.8.0'); define('MINUTE_IN_SECONDS',60); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600);
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;} function __($s){return $s;} function esc_html__($s){return $s;} function esc_attr__($s){return $s;} function esc_html_e($s){echo $s;} function esc_attr_e($s){echo $s;}
function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function esc_url($s){return htmlspecialchars((string)$s);} function is_network_admin(){return false;} function _n($a,$b,$n){return $n==1?$a:$b;} function wp_hash($d){return md5($d);} function wp_timezone(){return new DateTimeZone('UTC');}
function checked($a,$b=true,$e=true){$r=$a==$b?' checked="checked"':''; if($e) echo $r; return $r;} function selected($a,$b=true,$e=true){$r=$a==$b?' selected':''; if($e) echo $r; return $r;}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));} function wp_unslash($v){return $v;}
function sanitize_text_field($s){return trim(strip_tags((string)$s));} function sanitize_textarea_field($s){return trim(strip_tags((string)$s));} function wp_strip_all_tags($s){return strip_tags($s);} function wc_get_price_decimals(){return 2;}
$OPT=['permalink_structure'=>'/%postname%/']; function get_option($k,$d=false){global $OPT;return $OPT[$k]??$d;} function update_option($k,$v){global $OPT;$OPT[$k]=$v;} function delete_option($k){global $OPT;unset($OPT[$k]);}
$TR=[]; function set_transient($k,$v){global $TR;$TR[$k]=$v;} function get_transient($k){global $TR;return $TR[$k]??false;} function delete_transient($k){global $TR;unset($TR[$k]);}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';} function update_post_meta($id,$k,$v){global $META;$META[$id][$k]=$v;} function delete_post_meta($id,$k){global $META;unset($META[$id][$k]);}
$POSTS=[]; $NEXT=100; function wp_insert_post($a,$e=false){global $POSTS,$NEXT; $id=$NEXT++; $POSTS[$id]=$a+['ID'=>$id]; return $id;}
function get_post_type($id){global $POSTS;return $POSTS[$id]['post_type']??false;} function get_post_status($id){global $POSTS;return $POSTS[$id]['post_status']??false;} function get_the_title($id){global $POSTS;return $POSTS[$id]['post_title']??'';}
function get_post_status_object($s){return (object)['label'=>ucfirst($s)];} function get_posts($a){return [];} function get_edit_post_link($id){return "post.php?post=$id&action=edit";} function get_permalink($p){return 'https://shop.test/p/';}
function admin_url($p=''){return 'https://shop.test/wp-admin/'.$p;} function add_query_arg($a,$b=null,$c=null){ if(is_array($a)){ return $b.(str_contains($b,'?')?'&':'?').http_build_query($a);} return $c.(str_contains($c,'?')?'&':'?')."$a=$b"; }
function wp_nonce_url($u,$a){return $u.'&_wpnonce=x';} function wp_nonce_field(){echo '<input type="hidden" name="_wpnonce" value="x">';} function check_admin_referer(){return true;} function current_user_can(){return true;} function get_current_user_id(){return 1;}
function wp_count_posts($t){return (object)['publish'=>3];} function get_template(){return 'cartiva';} function has_block(){return false;} function wc_get_page_id(){return 7;} function is_wp_error($x){return false;} function wp_doing_ajax(){return false;} function is_admin(){return true;}
function has_post_thumbnail(){return false;} function set_post_thumbnail(){} function wp_kses_post($s){return $s;} function wc_price($a){return "৳$a";}
class Redirect extends Exception{} function wp_safe_redirect($u){throw new Redirect($u);} function wp_die($m){throw new Exception("die: $m");}
class GW{public $enabled='yes'; public $title='Cash on delivery'; function is_available(){return true;} function get_title(){return $this->title;}}
class PGs{ function payment_gateways(){return ['cod'=>new GW];} function get_available_payment_gateways(){return ['cod'=>new GW];} }
function WC(){ return new class { public $session=null; function payment_gateways(){return new PGs;} }; }
class P{ public $id; function __construct($id){$this->id=$id;} function is_type($t){return $t==='simple';} function get_children(){return [];} function is_purchasable(){return true;} function is_in_stock(){return true;} function get_id(){return $this->id;} function get_parent_id(){return 0;} function get_name(){return "Panjabi {$this->id}";} function get_image_id(){return 0;} function get_formatted_name(){return $this->get_name();} }
function wc_get_product($id){return in_array((int)$id,[10,11],true)?new P((int)$id):false;}
function wc_get_product_types(){return ['simple'=>1,'variable'=>1];} function wc_get_products($a){ return [new class(10) extends P{ function get_sku(){return 'PJ';} }]; }
class Cartiva_Commerce_Fields{ static function presets(){return ['bd_cod'=>1];} static function sanitize_config($c){return [];} }
class Cartiva_Commerce_AB_Test{ static function test($id){return ['variant'=>0];} }
class Cartiva_Commerce_Settings{ const PAGE='cartiva-commerce'; static function url($t='general'){return "settings&tab=$t";} static function main_checkout_is_block(){return false;} static function header($t){echo "<header>$t</header>";} }
require CARTIVA_COMMERCE_DIR.'includes/functions.php'; foreach(['class-countdown','class-cod','class-landing','class-funnels','class-orders','class-courier','class-setup'] as $c) require CARTIVA_COMMERCE_DIR."includes/$c.php";
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$S='Cartiva_Commerce_Setup';

// Store checks
$c=$S::checks(); ok(count($c)===5 && count(array_filter($c,fn($x)=>$x[0]==='warn'))===0,'healthy store: no warnings');
$OPT['permalink_structure']=''; $c=$S::checks(); ok(count(array_filter($c,fn($x)=>$x[0]==='warn'))===1 && str_contains(implode(' ',array_column($c,2)),'options-permalink.php'),'plain permalinks flagged with a fix link'); $OPT['permalink_structure']='/%postname%/';

// Settings of a quick page
$s=$S::landing_settings(10,['countdown'=>5,'delivery'=>true,'cod_only'=>true]);
ok($s['offers'][0]['product_id']===10 && $s['countdown']['enabled'] && $s['countdown']['days']===5 && $s['delivery']['enabled'] && count($s['delivery']['options'])===2 && $s['gateways']===['cod'],'landing page settings: product, 5-day timer, delivery, COD only');
$s=$S::landing_settings(0,['countdown'=>0,'delivery'=>false,'cod_only'=>false]); ok($s['offers']===[] && !$s['countdown']['enabled'] && !$s['delivery']['enabled'] && $s['gateways']===[],'no product, no timer, no delivery');

// Quick create: landing
$_POST=['product'=>10,'kind'=>'landing','countdown'=>7,'delivery'=>1,'cod_only'=>1];
try{ $S::quick_create(); }catch(Redirect $r){ $url=$r->getMessage(); }
$id=100; ok(str_contains($url,"post=$id&action=edit") && $POSTS[$id]['post_type']==='cartiva_landing' && $POSTS[$id]['post_status']==='draft' && $POSTS[$id]['post_title']==='Panjabi 10','landing page created as a draft with the product name');
ok($META[$id]['_cartiva_landing']['offers'][0]['product_id']===10 && $META[$id]['_cartiva_landing']['countdown']['days']===7,'product and 7-day timer saved');
// missing product
$_POST=['product'=>0,'kind'=>'landing']; try{ $S::quick_create(); }catch(Redirect $r){ $url=$r->getMessage(); }
ok(str_contains($url,'cartiva-commerce-setup') && $TR['cartiva_setup_error_1']==='Please choose the product to sell.','no product: back to Start here with a message');
// Quick create: funnel with upsell, published
$_POST=['product'=>10,'upsell'=>11,'kind'=>'funnel','countdown'=>3,'delivery'=>1,'publish'=>1];
try{ $S::quick_create(); }catch(Redirect $r){ $url=$r->getMessage(); }
$f=101; $steps=cartiva_commerce_funnel_steps($f);
ok($POSTS[$f]['post_type']==='cartiva_funnel' && str_contains($url,'cartiva_created=1'),'funnel created');
ok(array_column($steps,'type')===['landing','checkout','upsell','thankyou'],'steps: sales, checkout, upsell, thank you');
$ck=$steps[1]['id']; $up=$steps[2]['id']; $sp=$steps[0]['id'];
ok($META[$ck]['_cartiva_landing']['offers'][0]['product_id']===10 && $META[$ck]['_cartiva_landing']['delivery']['enabled'],'checkout step has the product and delivery choice');
ok($META[$up]['_cartiva_landing']['offer']['product_id']===11 && $META[$up]['_cartiva_landing']['offer']['discount']===10.0 && !$META[$up]['_cartiva_landing']['countdown']['enabled'],'upsell step offers the extra product at 10% off');
ok($META[$sp]['_cartiva_landing']['offers']===[] && $META[$sp]['_cartiva_landing']['countdown']['enabled'],'sales page: no form, has the timer');
ok($POSTS[$ck]['post_status']==='publish' && cartiva_commerce_funnel_of($ck)===$f,'published and linked to the funnel');
// funnel without upsell product
$_POST=['product'=>10,'kind'=>'funnel']; try{ $S::quick_create(); }catch(Redirect $r){} $f2=106; ok(array_column(cartiva_commerce_funnel_steps($f2),'type')===['landing','checkout','thankyou'],'no upsell product: three steps');

// Add step to a funnel
$_GET=['funnel'=>$f,'type'=>'downsell']; try{ Cartiva_Commerce_Funnels::add_step(); }catch(Redirect $r){ $url=$r->getMessage(); }
$steps=cartiva_commerce_funnel_steps($f); $last=end($steps); ok($last['type']==='downsell' && $POSTS[$last['id']]['post_status']==='draft' && str_contains($url,'post='.$last['id']),'new downsell step added at the end and opened');
$_GET=['funnel'=>$f,'type'=>'bogus']; try{ Cartiva_Commerce_Funnels::add_step(); ok(false,'bad type'); }catch(Redirect $r){ ok(false,'bad type redirected'); }catch(Exception $e){ ok(str_starts_with($e->getMessage(),'die'),'unknown step type refused'); }

// Step badges
$b=Cartiva_Commerce_Funnels::step_badges($ck); ok(str_contains($b,'Live') && str_contains($b,'Panjabi 10'),'checkout badge: live + product');
$b=Cartiva_Commerce_Funnels::step_badges($last['id']); ok(str_contains($b,'Draft'),'new step shows Draft');

// Landing status summary
$post=(object)['ID'=>100,'post_status'=>'draft']; ob_start(); Cartiva_Commerce_Landing::render_status($post,cartiva_commerce_landing_config(100),cartiva_commerce_landing_offers(cartiva_commerce_landing_config(100))); $h=ob_get_clean();
ok(str_contains($h,'Not published yet') && str_contains($h,'1 product option') && str_contains($h,'Cash on delivery') && str_contains($h,'Countdown on'),'summary: draft, product, COD, countdown');
$post=(object)['ID'=>$sp,'post_status'=>'publish']; ob_start(); Cartiva_Commerce_Landing::render_status($post,cartiva_commerce_landing_config($sp),[]); $h=ob_get_clean();
ok(str_contains($h,'>Live<') && str_contains($h,'checkout step takes the order') && !str_contains($h,'No product yet'),'sales step: no false "no product" warning');
$META[300]['_cartiva_landing']=[]; $post=(object)['ID'=>300,'post_status'=>'publish']; ob_start(); Cartiva_Commerce_Landing::render_status($post,cartiva_commerce_landing_config(300),[]); $h=ob_get_clean();
ok(str_contains($h,'Live, but no product') && str_contains($h,'is-todo'),'stand-alone page without product: clear warning');

// Start here page renders
ob_start(); $S::render(); $h=ob_get_clean();
ok(str_contains($h,'Create it') && str_contains($h,'name="kind" value="funnel"') && substr_count($h,'name="countdown"')===4 && str_contains($h,'All set'),'Start here: form, 4 timer choices, store check');
ok(str_contains($h,'id="cartiva-quick-product"') && str_contains($h,'Fake order protection'),'product label linked; extras listed');
libxml_use_internal_errors(true); $d=new DOMDocument; $d->loadHTML('<?xml encoding="utf-8"?><div>'.$h.'</div>'); $errs=array_filter(libxml_get_errors(),fn($e)=>!str_contains($e->message,'Tag ') ); ok(count($errs)===0,'Start here HTML well formed: '.implode('; ',array_map(fn($e)=>trim($e->message),$errs)));

// First run redirect
$OPT['cartiva_commerce_show_setup']=1; try{ $S::first_run_redirect(); ok(false,'no redirect'); }catch(Redirect $r){ ok(str_contains($r->getMessage(),'cartiva-commerce-setup') && !isset($OPT['cartiva_commerce_show_setup']),'first visit after activation opens Start here once'); }
try{ $S::first_run_redirect(); ok(true,''); }catch(Redirect $r){ ok(false,'redirected twice'); }

echo "\n$pass passed, $fail failed\n";
if(getenv('DUMP')){ ob_start(); $S::render(); file_put_contents('/tmp/setup.html',ob_get_clean()); ob_start(); Cartiva_Commerce_Landing::render_status((object)['ID'=>100,'post_status'=>'draft'],cartiva_commerce_landing_config(100),cartiva_commerce_landing_offers(cartiva_commerce_landing_config(100))); echo '<br>'; Cartiva_Commerce_Landing::render_status((object)['ID'=>300,'post_status'=>'publish'],[ 'gateways'=>[],'bumps'=>[]],[]); file_put_contents('/tmp/status.html',ob_get_clean()); }
