<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('HOUR_IN_SECONDS',3600); define('DAY_IN_SECONDS',86400);
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;} function __($s){return $s;} function esc_html__($s){return $s;} function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);} function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);} function wp_kses_post($s){return $s;}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));}
function sanitize_text_field($s){return trim(strip_tags($s));} function wp_unslash($v){return $v;} function wc_get_price_decimals(){return 2;} function wp_strip_all_tags($s){return strip_tags($s);}
function wc_price($p){return '<span>&#2547;&nbsp;'.number_format($p,2).'</span>';} function get_woocommerce_currency(){return 'BDT';} function number_format_i18n($n,$d=0){return number_format($n,$d);}
function get_option($k,$d=false){ return ['gmt_offset'=>6,'date_format'=>'Y-m-d'][$k] ?? $d; } function date_i18n($f,$t){return date($f,$t);} function wp_rand($a,$b){return mt_rand($a,$b);}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';} function update_post_meta($id,$k,$v){global $META;$META[$id][$k]=$v;} function delete_post_meta($id,$k){global $META;unset($META[$id][$k]);}
$POSTS=[]; function get_post_type($id){global $POSTS;return $POSTS[$id]['type']??false;} function get_post_status($id){global $POSTS;return $POSTS[$id]['status']??false;} function delete_transient(){}
require CARTIVA_COMMERCE_DIR.'includes/functions.php';
foreach(['class-funnels','class-analytics','class-ab-test','class-tracking'] as $c) require CARTIVA_COMMERCE_DIR."includes/$c.php";
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}

// 1. A/B variant stands for its step in the funnel
$POSTS=[1=>['type'=>'cartiva_landing','status'=>'publish'],2=>['type'=>'cartiva_landing','status'=>'publish'],3=>['type'=>'cartiva_landing','status'=>'publish'],9=>['type'=>'cartiva_landing','status'=>'publish'],10=>['type'=>'cartiva_funnel']];
Cartiva_Commerce_Funnels::set_steps(10,[['id'=>1,'type'=>'landing'],['id'=>2,'type'=>'checkout'],['id'=>3,'type'=>'thankyou']]);
Cartiva_Commerce_AB_Test::set(2,['variant'=>9,'split'=>30,'active'=>1]);
ok(cartiva_commerce_canonical_step(9)===2,'variant maps to its step');
ok(cartiva_commerce_funnel_of(9)===10,'variant joins the funnel');
ok(cartiva_commerce_step_type(9)==='checkout','variant has the step type');
ok(cartiva_commerce_next_step(9,'thankyou')===3,'variant leads to the same thank-you step');
ok(cartiva_commerce_next_step(1)===2,'previous step still points at the original');
$t=Cartiva_Commerce_AB_Test::test(2); ok($t['split']===30 && $t['active']===true && $t['started']>0,'test saved and started');
$started=$t['started']; Cartiva_Commerce_AB_Test::set(2,['variant'=>9,'split'=>40,'active'=>1]); ok(Cartiva_Commerce_AB_Test::test(2)['started']===$started,'changing the split keeps the start time');
ok(Cartiva_Commerce_AB_Test::running(Cartiva_Commerce_AB_Test::test(2)),'test runs with a published variant');
$POSTS[9]['status']='draft'; ok(!Cartiva_Commerce_AB_Test::running(Cartiva_Commerce_AB_Test::test(2)),'draft variant = test paused'); $POSTS[9]['status']='publish';
Cartiva_Commerce_AB_Test::set(2,['variant'=>9,'split'=>500,'active'=>1]); ok(Cartiva_Commerce_AB_Test::test(2)['split']===99,'split capped at 99%');
Cartiva_Commerce_AB_Test::release(9,2);
ok(cartiva_commerce_canonical_step(9)===9 && cartiva_commerce_funnel_of(9)===0,'ending the test releases the variant');
ok(cartiva_commerce_funnel_of(2)===10,'tested step stays in the funnel');

// 2. Group split is close to the setting
mt_srand(7); $b=0; for($i=0;$i<20000;$i++){ if('b'===Cartiva_Commerce_AB_Test::pick(30)) $b++; }
ok(abs($b/20000-0.30)<0.015,'30% split gives ~30% B: '.round($b/200,1).'%');
ok(Cartiva_Commerce_AB_Test::pick(0)==='a','0% never B');

// 3. Daily visits
$_SERVER['HTTP_USER_AGENT']='Mozilla/5.0 (Linux; Android 14) Chrome/130 Mobile';
ok(!cartiva_commerce_is_bot(),'phone browser is not a bot');
foreach(['Googlebot/2.1','facebookexternalhit/1.1','HeadlessChrome/120','curl/8.0',''] as $ua){ $_SERVER['HTTP_USER_AGENT']=$ua; ok(cartiva_commerce_is_bot(),"bot: '$ua'"); }
$META[1]['_cartiva_visits_log']=['2026-11-01'=>5,'2026-11-10'=>7,'2026-11-20'=>3]; $META[1]['_cartiva_visits']=15;
ok(cartiva_commerce_visits_between(1,'2026-11-05','2026-11-20')===10,'visits in a range');
ok(cartiva_commerce_visits_between(1)===15,'all-time visits');
$META[50]=[]; cartiva_commerce_count_visit(50); cartiva_commerce_count_visit(50);
$today=gmdate('Y-m-d',time()+6*3600); ok($META[50]['_cartiva_visits']===2 && $META[50]['_cartiva_visits_log'][$today]===2,'visit counted in total and today (store time zone)');
$log=[]; for($d=0;$d<410;$d++){ $log[gmdate('Y-m-d',strtotime("2025-01-01 +$d days"))]=1; } $META[51]['_cartiva_visits_log']=$log; cartiva_commerce_count_visit(51); ok(count($META[51]['_cartiva_visits_log'])===400 && isset($META[51]['_cartiva_visits_log'][$today]),'log keeps 400 days, newest kept');

// 4. Summary math
class Item{ public $m,$t,$q,$pid,$vid,$n; function __construct($t,$m=[],$q=1,$pid=5){$this->t=$t;$this->m=$m;$this->q=$q;$this->pid=$pid;$this->vid=0;$this->n="Item $pid";} function get_meta($k){return $this->m[$k]??'';} function get_total(){return $this->t;} function get_quantity(){return $this->q;} function get_product_id(){return $this->pid;} function get_variation_id(){return $this->vid;} function get_name(){return $this->n;} }
class DT{ function date_i18n($f){return '2026-11-20';} }
class WC_Order{ public $items,$meta,$total; function __construct($items,$meta=[]){$this->items=$items;$this->meta=$meta;$this->total=array_sum(array_map(fn($i)=>$i->t,$items));} function get_total(){return $this->total;} function get_items(){return $this->items;} function get_meta($k){return $this->meta[$k]??'';} function get_date_created(){return new DT;} function get_id(){return 77;} function get_currency(){return 'BDT';} }
$orders=[ new WC_Order([new Item(1000),new Item(150,['_cartiva_bump'=>0])],['_cartiva_offers'=>[3=>'yes']]), new WC_Order([new Item(1000),new Item(400,['_cartiva_upsell'=>3])],['_cartiva_offers'=>[3=>'yes',4=>'no']]), new WC_Order([new Item(800)],['_cartiva_offers'=>[3=>'no']]) ];
$s=Cartiva_Commerce_Analytics::summarize($orders);
ok($s['orders']===3 && $s['revenue']===3350.0,'orders and revenue');
ok($s['bump_orders']===1 && $s['bump_revenue']===150.0,'bump orders and revenue');
ok($s['upsell_yes']===2 && $s['upsell_no']===2 && $s['upsell_revenue']===400.0,'upsell answers and revenue');
ok($s['days']===['2026-11-20'=>3350.0],'revenue per day');
ok(Cartiva_Commerce_Analytics::percent(1,3)==='33.3%' && Cartiva_Commerce_Analytics::percent(1,0)==='—','percent and divide by zero');
ok(Cartiva_Commerce_Analytics::money(1500)==="৳\u{00A0}1,500.00",'plain price shows the ৳ sign, not &#2547;: '.Cartiva_Commerce_Analytics::money(1500));

// 5. Chart is valid SVG with one bar group per day and a table
$daily=['2026-11-18'=>0.0,'2026-11-19'=>1200.0,'2026-11-20'=>3350.0];
$html=Cartiva_Commerce_Analytics::chart($daily);
$svg=substr($html,0,strpos($html,'</svg>')+6); libxml_use_internal_errors(true); $x=simplexml_load_string($svg);
ok($x!==false,'chart SVG parses');
ok(substr_count($svg,'class="cartiva-chart__day"')===3 && substr_count($svg,'class="cartiva-chart__bar"')===2,'3 days, 2 bars (empty day has no bar)');
ok(str_contains($html,'<details') && substr_count($html,'<tr>')===4,'table view with 3 rows + header');

// 6. Purchase payload
$o=new WC_Order([new Item(1800,[],2,11),new Item(400,['_cartiva_upsell'=>3],1,12)]);
$p=Cartiva_Commerce_Tracking::order_payload($o);
ok($p['value']===2200.0 && $p['order_id']==='77' && $p['currency']==='BDT','purchase value is the final total after upsells');
ok($p['items'][0]===['id'=>'11','name'=>'Item 11','price'=>900.0,'quantity'=>2],'item price per piece');

echo "\n$pass passed, $fail failed\n";
