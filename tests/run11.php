<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/');
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;} function __($s){return $s;} function wp_hash($d){return md5($d);} function wp_timezone(){return new DateTimeZone('UTC');} function _n($a,$b,$n){return $n==1?$a:$b;}
function wp_parse_args($a,$d){return array_merge($d,$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));}
function sanitize_text_field($s){return trim(strip_tags($s));} function sanitize_textarea_field($s){return trim(strip_tags($s));} function wc_get_price_decimals(){return 2;}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';} function update_post_meta($id,$k,$v){global $META;$META[$id][$k]=$v;} function delete_post_meta($id,$k){global $META;unset($META[$id][$k]);} function delete_transient(){}
$POSTS=[]; function get_post_type($id){global $POSTS;return $POSTS[$id]['type']??false;} function get_post_status($id){global $POSTS;return $POSTS[$id]['status']??false;}
function get_option($k,$d=false){return $d;} function current_user_can(){return true;} function wp_doing_ajax(){return true;} function is_admin(){return false;}
class P{ public $id,$type,$parent,$price; function __construct($id,$type,$price,$parent=0){$this->id=$id;$this->type=$type;$this->price=$price;$this->parent=$parent;}
 function is_type($t){return $this->type===$t;} function get_children(){return [];} function is_purchasable(){return true;} function is_in_stock(){return true;} function get_id(){return $this->id;} function get_parent_id(){return $this->parent;} function get_price(){return $this->price;} function set_price($p){$this->price=$p;} }
$PRODUCTS=[10=>new P(10,'simple',1000),20=>new P(20,'variable',0),21=>new P(21,'variation',500,20),30=>new P(30,'simple',200)];
function wc_get_product($id){global $PRODUCTS; return isset($PRODUCTS[$id]) ? clone $PRODUCTS[$id] : false;}
require CARTIVA_COMMERCE_DIR.'includes/functions.php'; require CARTIVA_COMMERCE_DIR.'includes/class-countdown.php'; require CARTIVA_COMMERCE_DIR.'includes/class-cod.php';
foreach(['class-fields','class-landing','class-offers','class-funnels'] as $c) require CARTIVA_COMMERCE_DIR."includes/$c.php";
class Cartiva_Commerce_Settings{} 
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}

// 1. A 1.0 landing page (single product keys only) still sells its product.
$c=cartiva_commerce_landing_config(1); $c['product_id']=10; $c['quantity']=2;
$o=cartiva_commerce_landing_offers($c); ok(count($o)===1 && $o[0]['product_id']===10 && $o[0]['qty']===2,'1.0 settings become one option');
ok(cartiva_commerce_default_offer_indexes($c)===[0],'1.0 default selection');
$empty=cartiva_commerce_landing_config(2); ok(cartiva_commerce_landing_offers($empty)===[],'no product = info-only step');

// 2. Modes and defaults
$c=cartiva_commerce_landing_config(3); $c['offers']=[['product_id'=>10,'qty'=>1],['product_id'=>10,'qty'=>2,'discount'=>10,'default'=>true],['product_id'=>10,'qty'=>3,'discount'=>15,'default'=>true]];
$c['offer_mode']='one'; ok(cartiva_commerce_default_offer_indexes($c)===[1],'pick-one: first marked option');
$c['offer_mode']='many'; ok(cartiva_commerce_default_offer_indexes($c)===[1,2],'pick-many: all marked options');
$c['offer_mode']='single'; ok(count(cartiva_commerce_landing_offers($c))===1,'single mode keeps only the first option');
$c['offer_mode']='one'; $c['offers'][1]['default']=false; $c['offers'][2]['default']=false; ok(cartiva_commerce_default_offer_indexes($c)===[0],'nothing marked: first option');

// 3. Discount math
ok(cartiva_commerce_discounted(1000,10)===900.0,'10% of 1000');
ok(cartiva_commerce_discounted(333.33,15)===283.33,'rounding to store decimals');
ok(cartiva_commerce_discounted(100,95)===10.0,'discount capped at 90%');
ok(cartiva_commerce_discounted(100,-5)===100.0,'negative discount ignored');

// 4. Saving: variable parent rejected, variation resolved, clamps, legacy keys mirrored, max 5 bumps
class Fake_GW{} function WC(){ return new class { function payment_gateways(){return null;} public $cart=null; }; }
$s=Cartiva_Commerce_Landing::sanitize(['offer_mode'=>'one','layout'=>'steps','offers'=>[['item'=>20],['item'=>21,'qty'=>500,'discount'=>99,'label'=>'<b>Pack</b>','default'=>1],['item'=>10]],'bumps'=>array_fill(0,7,['item'=>30,'discount'=>20,'position'=>'evil'])]);
ok(count($s['offers'])===2 && Cartiva_Commerce_Landing::$notices[0][0]==='error','variable product with no buyable variation is skipped with an error notice');
ok($s['offers'][0]['product_id']===20 && $s['offers'][0]['variation_id']===21,'variation resolves to parent + variation');
ok($s['offers'][0]['qty']===99 && $s['offers'][0]['discount']===90.0 && $s['offers'][0]['label']==='Pack','qty/discount clamped, label cleaned');
ok($s['product_id']===20 && $s['variation_id']===21,'first option mirrored in 1.0 keys');
ok(count($s['bumps'])===5 && $s['bumps'][0]['position']==='before_payment','bumps capped at 5, bad position fixed');
ok($s['layout']==='steps' && $s['offer_mode']==='one','layout and mode saved');
$s=Cartiva_Commerce_Landing::sanitize(['offer_mode'=>'hack','layout'=>'x']); ok($s['offer_mode']==='single' && $s['layout']==='one','bad mode/layout fall back');

// 5. Prices: discount applied from settings, never doubled
$META[5]['_cartiva_landing']=['offer_mode'=>'one','offers'=>[['product_id'=>10,'variation_id'=>0,'qty'=>2,'discount'=>10]],'bumps'=>[['product_id'=>30,'variation_id'=>0,'qty'=>1,'discount'=>50]]];
class Cart{ public $items; function __construct($i){$this->items=$i;} function get_cart(){return $this->items;} }
$offer=['product_id'=>10,'variation_id'=>0,'data'=>new P(10,'simple',1000),'cartiva_offer'=>['landing'=>5,'index'=>0]];
$bump=['product_id'=>30,'variation_id'=>0,'data'=>new P(30,'simple',200),'cartiva_bump'=>['landing'=>5,'index'=>0]];
$forged=['product_id'=>30,'variation_id'=>0,'data'=>new P(30,'simple',200),'cartiva_offer'=>['landing'=>5,'index'=>0]];
$plain=['product_id'=>10,'variation_id'=>0,'data'=>new P(10,'simple',1000)];
$cart=new Cart([$offer,$bump,$forged,$plain]);
Cartiva_Commerce_Offers::prices($cart); Cartiva_Commerce_Offers::prices($cart);
ok($offer['data']->get_price()===900.0,'option discounted once (900) even after two runs: '.$offer['data']->get_price());
ok($bump['data']->get_price()===100.0,'bump 50% off');
ok($forged['data']->get_price()===200,'a line pointing at an option of another product gets no discount');
ok($plain['data']->get_price()===1000,'normal cart lines untouched');

// 6. Funnels: steps, next step, thank-you step, one funnel per page
$POSTS=[100=>['type'=>'cartiva_funnel'],101=>['type'=>'cartiva_landing','status'=>'publish'],102=>['type'=>'cartiva_landing','status'=>'publish'],103=>['type'=>'cartiva_landing','status'=>'publish'],104=>['type'=>'page','status'=>'publish'],105=>['type'=>'cartiva_landing','status'=>'draft'],200=>['type'=>'cartiva_funnel']];
$steps=Cartiva_Commerce_Funnels::sanitize_steps([['id'=>101,'type'=>'landing'],['id'=>104,'type'=>'checkout'],['id'=>102,'type'=>'checkout'],['id'=>101,'type'=>'thankyou'],['id'=>105,'type'=>'bad'],['id'=>103,'type'=>'thankyou']]);
ok(array_column($steps,'id')===[101,102,105,103],'normal pages and duplicates dropped, order kept');
ok($steps[2]['type']==='landing','unknown type falls back to sales page');
Cartiva_Commerce_Funnels::set_steps(100,$steps);
ok(cartiva_commerce_funnel_of(102)===100,'steps know their funnel');
ok(cartiva_commerce_next_step(101)===102,'sales page -> checkout');
ok(cartiva_commerce_next_step(102)===103,'checkout -> next published step (draft skipped)');
ok(cartiva_commerce_next_step(102,'thankyou')===103,'checkout -> thank-you step');
ok(cartiva_commerce_next_step(103)===0,'last step has no next');
Cartiva_Commerce_Funnels::set_steps(200,[['id'=>103,'type'=>'thankyou']]);
ok(cartiva_commerce_funnel_of(103)===200 && !in_array(103,array_column(cartiva_commerce_funnel_steps(100),'id')),'moving a page to another funnel removes it from the first');
Cartiva_Commerce_Funnels::set_steps(100,[['id'=>101,'type'=>'landing']]);
ok(cartiva_commerce_funnel_of(102)===0,'removed step is released');

echo "\n$pass passed, $fail failed\n";
