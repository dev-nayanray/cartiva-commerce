<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('HOUR_IN_SECONDS',3600);
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;} function __($s){return $s;}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));}
function sanitize_text_field($s){return trim(strip_tags($s));} function wc_get_price_decimals(){return 2;} function wp_strip_all_tags($s){return strip_tags($s);} function wc_price($p){return "৳$p";}
function get_the_title($id){return "Step $id";} function delete_transient(){} function get_option($k,$d=false){return $d;}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';} function update_post_meta($id,$k,$v){global $META;$META[$id][$k]=$v;} function delete_post_meta($id,$k){global $META;unset($META[$id][$k]);}
$POSTS=[]; function get_post_type($id){global $POSTS;return $POSTS[$id]['type']??false;} function get_post_status($id){global $POSTS;return $POSTS[$id]['status']??false;}
function get_permalink($id){return "https://shop.test/offer/step-$id/";} function add_query_arg($a,$b,$c=null){ if(is_array($a)) return $b."?".http_build_query($a); return $c."?".http_build_query([$a=>$b]); }
function wc_get_endpoint_url($e,$v,$u){return "https://shop.test/checkout/$e/$v/";} function wc_get_checkout_url(){return 'https://shop.test/checkout/';}
class P{ public $id,$type,$price,$stock_managed=true,$ok=true; function __construct($id,$type,$price){$this->id=$id;$this->type=$type;$this->price=$price;}
 function is_type($t){return $this->type===$t;} function get_id(){return $this->id;} function get_parent_id(){return 0;} function get_price(){return $this->price;} function get_name(){return "Product {$this->id}";}
 function is_purchasable(){return $this->ok;} function has_enough_stock($q){return $this->ok;} function managing_stock(){return $this->stock_managed;} function get_variation_attributes(){return [];} }
$PRODUCTS=[50=>new P(50,'simple',600),60=>new P(60,'simple',300),70=>new P(70,'simple',100)];
function wc_get_product($id){global $PRODUCTS; return $PRODUCTS[$id]??false;}
$STOCK=[]; function wc_update_product_stock($p,$q,$op){global $STOCK; $STOCK[]=[$p->id,$q,$op];}
class Item{ public $meta=[]; function add_meta_data($k,$v,$u){$this->meta[$k]=$v;} function save(){} }
class DS{ public $reduced=true; function get_stock_reduced($id){return $this->reduced;} }
class DT{ public $t; function __construct($t){$this->t=$t;} function getTimestamp(){return $this->t;} }
class WC_Order{ public $meta=[],$status='processing',$pm='cod',$created,$items=[],$notes=[],$total=0,$ds;
 function __construct(){$this->created=new DT(time()-60);$this->ds=new DS;}
 function get_meta($k){return $this->meta[$k]??'';} function update_meta_data($k,$v){$this->meta[$k]=$v;} function get_status(){return $this->status;} function get_payment_method(){return $this->pm;}
 function get_date_created(){return $this->created;} function get_id(){return 900;} function get_order_key(){return 'wc_order_abc';} function get_currency(){return 'BDT';}
 function add_product($p,$q,$a){$this->items[]=['p'=>$p->id,'q'=>$q,'a'=>$a,'item'=>new Item]; return count($this->items);} function get_item($i){return $this->items[$i-1]['item'];}
 function get_data_store(){return $this->ds;} function calculate_totals($t){$this->total=array_sum(array_map(fn($i)=>$i['a']['total'],$this->items));} function add_order_note($n){$this->notes[]=$n;} function save(){} }
require CARTIVA_COMMERCE_DIR.'includes/functions.php';
foreach(['class-fields','class-checkout','class-upsells','class-funnels'] as $c) require CARTIVA_COMMERCE_DIR."includes/$c.php";
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}

// Funnel 1: sales 1 -> checkout 2 -> upsell 3 -> downsell 4 -> upsell 5 -> thank you 6
$POSTS=[1=>['type'=>'cartiva_landing','status'=>'publish'],2=>['type'=>'cartiva_landing','status'=>'publish'],3=>['type'=>'cartiva_landing','status'=>'publish'],4=>['type'=>'cartiva_landing','status'=>'publish'],5=>['type'=>'cartiva_landing','status'=>'publish'],6=>['type'=>'cartiva_landing','status'=>'publish'],10=>['type'=>'cartiva_funnel'],20=>['type'=>'cartiva_funnel'],21=>['type'=>'cartiva_landing','status'=>'publish']];
Cartiva_Commerce_Funnels::set_steps(10,[['id'=>1,'type'=>'landing'],['id'=>2,'type'=>'checkout'],['id'=>3,'type'=>'upsell'],['id'=>4,'type'=>'downsell'],['id'=>5,'type'=>'upsell'],['id'=>6,'type'=>'thankyou']]);
Cartiva_Commerce_Funnels::set_steps(20,[['id'=>21,'type'=>'upsell']]);
$META[3]['_cartiva_landing']=['offer'=>['product_id'=>50,'variation_id'=>0,'qty'=>2,'discount'=>25]];
$META[4]['_cartiva_landing']=['offer'=>['product_id'=>60,'variation_id'=>0,'qty'=>1,'discount'=>0]];
$META[5]['_cartiva_landing']=['offer'=>['product_id'=>70,'variation_id'=>0,'qty'=>1,'discount'=>10]];
function order(){ $o=new WC_Order; $o->meta['_cartiva_landing_id']=2; return $o; }
$thank='https://shop.test/offer/step-6/?cartiva_order=900&key=wc_order_abc';

// 1. After checkout
$o=order(); ok(Cartiva_Commerce_Upsells::redirect_to_offer('WC',$o)==='https://shop.test/offer/step-3/?cartiva_order=900&key=wc_order_abc','COD order goes to the first upsell');
$o=order(); $o->pm='stripe'; ok(Cartiva_Commerce_Upsells::redirect_to_offer('WC',$o)==='WC','card payment skips offers');
$o=order(); $o->status='completed'; ok(Cartiva_Commerce_Upsells::redirect_to_offer('WC',$o)==='WC','completed order gets no offers');
$o=order(); $o->created=new DT(time()-3*3600); ok(Cartiva_Commerce_Upsells::redirect_to_offer('WC',$o)==='WC','offer window (2 hours) expired');
$o=order(); $o->meta['_cartiva_offers']=[3=>'no']; ok(Cartiva_Commerce_Upsells::redirect_to_offer('WC',$o)==='WC','offers only start once');
$o=new WC_Order; ok(Cartiva_Commerce_Upsells::redirect_to_offer('WC',$o)==='WC','orders from the main checkout untouched');
ok(Cartiva_Commerce_Checkout::final_url(order())===$thank,'final URL = funnel thank-you step');

// 2. Paths
$o=order();
ok(Cartiva_Commerce_Upsells::next_url(3,$o,true)==='https://shop.test/offer/step-5/?cartiva_order=900&key=wc_order_abc','upsell YES skips its downsell -> next upsell');
ok(Cartiva_Commerce_Upsells::next_url(3,$o,false)==='https://shop.test/offer/step-4/?cartiva_order=900&key=wc_order_abc','upsell NO -> downsell');
ok(Cartiva_Commerce_Upsells::next_url(4,$o,true)==='https://shop.test/offer/step-5/?cartiva_order=900&key=wc_order_abc','downsell -> next upsell');
ok(Cartiva_Commerce_Upsells::next_url(5,$o,true)===$thank,'last upsell -> thank-you step');
$POSTS[4]['status']='draft'; ok(Cartiva_Commerce_Upsells::next_url(3,$o,false)==='https://shop.test/offer/step-5/?cartiva_order=900&key=wc_order_abc','unpublished downsell is skipped'); $POSTS[4]['status']='publish';
$POSTS[6]['status']='draft'; ok(Cartiva_Commerce_Upsells::next_url(5,$o,true)==='https://shop.test/checkout/order-received/900/?key=wc_order_abc','no thank-you step -> WooCommerce order received page'); $POSTS[6]['status']='publish';

// 3. Security: step of another funnel, non-offer step
ok(Cartiva_Commerce_Upsells::is_offer_step_of(3,order()),'upsell of the same funnel');
ok(!Cartiva_Commerce_Upsells::is_offer_step_of(21,order()),'upsell of another funnel rejected');
ok(!Cartiva_Commerce_Upsells::is_offer_step_of(6,order()),'thank-you step is not an offer');

// 4. Accepting adds to the same order at the offer price; stock taken
$o=order(); $STOCK=[];
ok(Cartiva_Commerce_Upsells::record($o,3,true)===true,'YES adds the product');
ok($o->items[0]['p']===50 && $o->items[0]['q']===2 && $o->items[0]['a']['total']===900.0,'2 x 600 at 25% off = 900: '.json_encode($o->items[0]['a']));
ok($o->items[0]['item']->meta['_cartiva_upsell']===3 && $o->items[0]['item']->meta['_reduced_stock']===2,'line tagged and stock recorded');
ok($STOCK===[[50,2,'decrease']],'stock reduced once');
ok($o->get_meta('_cartiva_offers')===[3=>'yes'],'decision saved');
ok($o->total===900.0,'order totals recalculated');
$o2=order(); $o2->ds->reduced=false; $STOCK=[]; Cartiva_Commerce_Upsells::record($o2,3,true); ok($STOCK===[] && !isset($o2->items[0]['item']->meta['_reduced_stock']),'stock left to WooCommerce when the order has not reduced it yet');
$o=order(); Cartiva_Commerce_Upsells::record($o,3,false); ok(empty($o->items) && $o->get_meta('_cartiva_offers')===[3=>'no'] && str_contains($o->notes[0],'declined'),'NO adds nothing, notes it');
$PRODUCTS[50]->ok=false; $o=order(); ok(Cartiva_Commerce_Upsells::record($o,3,true)===false && empty($o->items) && str_contains($o->notes[0],'not available'),'out-of-stock offer: nothing added, admin note'); $PRODUCTS[50]->ok=true;
$o=order(); Cartiva_Commerce_Upsells::record($o,3,true); Cartiva_Commerce_Upsells::record($o,5,true); ok(count($o->items)===2 && $o->total===990.0,'two upsells on one order: 900 + 90');

echo "\n$pass passed, $fail failed\n";
