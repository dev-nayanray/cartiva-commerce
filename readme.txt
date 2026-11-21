=== Cartiva Commerce ===
Contributors: godevs
Tags: woocommerce, sales funnel, landing page, order bump, checkout fields
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sales funnels, one-product landing pages with a short checkout, order bumps, product packs and a checkout field editor for WooCommerce. All free.

== Description ==

Cartiva Commerce turns a single product into a focused sales page with the order form on the same page, the way funnel builders do, completely free.

It works with any theme. With the free [Cartiva](https://wordpress.org/themes/cartiva/) theme, landing pages start with ready-made sections (offer bar, benefits, steps, comparison, guarantee, FAQ) and the order form picks up your theme colors.

= Sales funnels =

* A funnel leads shoppers through steps: a sales page, a checkout and a thank-you page.
* **Create a funnel from the template** in one click: three draft steps with ready-made content (the Cartiva theme's landing sections when you use it).
* Buttons on a sales page that link to `#order` open the next step automatically.
* Buyers land on the funnel's thank-you step with their order details.
* Visits, orders, revenue and conversion for each funnel.

= Product options and order bumps =

* Sell one product with a quantity changer, let shoppers **pick one package** (for example 1, 2 or 3 pieces, each with its own discount and a badge like "Most popular"), or **pick several products**.
* **Order bumps**: a tick box on the order form that adds an extra product, with its own discount, text and a minimum order amount. Up to five per page, above the payment methods or below the address.
* Discounts always come from your settings, so shoppers cannot change them.

= Checkout layouts and coupons =

* One column, two columns (details left, order summary right) or two steps (details, then payment).
* An inline coupon field on landing pages, and a switch to hide the coupon link on the main checkout.

= Landing pages with a built-in order form =

* Create a page under **Landing pages**, pick the product (or one variation) and the quantity.
* The **Order form** block shows the product, a quantity changer and a short checkout, all on the page. No cart page, no extra steps.
* Choose which payment methods the page offers, for example Cash on delivery only.
* Send buyers to your own thank-you page after the order.
* See visits, orders, revenue and conversion for each landing page in the list.

= Checkout fields you control, separately for every form =

* Each landing page has its own fields. Show only **Full name, Mobile number and Address** on one page without touching your main checkout.
* Your main checkout has its own field settings too.
* Rename, require, reorder, resize or hide fields. Presets get you started: WooCommerce default, Cash on delivery (Bangladesh), Minimal, or Custom.
* A single **Full name** field can replace First and Last name. Orders still save first and last name, so emails and reports work as usual.
* Optional check that the mobile number is a valid Bangladeshi number (01XXXXXXXXX).

= Buy now button =

* Adds a Buy now button next to Add to cart on product pages. It adds the product and goes straight to checkout.
* Turn it on or off and change its label in **WooCommerce > Cartiva Commerce**.

= Good to know =

* Order forms use WooCommerce's classic checkout, because the block checkout does not allow core fields to be changed or hidden. Your main checkout page can stay on the block checkout; the main-checkout field settings apply when it uses the classic checkout, and the settings page can switch it for you in one click.
* Works with High-Performance Order Storage (HPOS).
* No external services, no tracking, no ads, no upsell screens.

== Installation ==

1. Install and activate WooCommerce.
2. Go to **Plugins > Add New**, search for **Cartiva Commerce**, install and activate it.
3. Go to **Cartiva Funnels** and click **Create a funnel from the template**.
4. Open the **Checkout** step, add the product in the **Landing page: product and order form** box, choose the fields and payment methods.
5. Check the text on the three steps and publish them. Visit the first step and place a test order.

For a single page that sells on its own, go to **Cartiva Funnels > Landing pages > Add landing page** instead.

To change your main checkout fields or the Buy now button, go to **WooCommerce > Cartiva Commerce**.

== Frequently Asked Questions ==

= Does changing a landing page's fields change my main checkout? =

No. Every landing page keeps its own fields, and the main checkout keeps its own. The plugin checks which form sent each request, so the live totals and the Place order step always use the right rules.

= How do I make a "Buy 1, 2 or 3" offer? =

In the landing page box, set **How shoppers choose** to "Shoppers pick one option". Add the same product three times with quantity 1, 2 and 3, give the bigger packs a discount and a badge, and tick **Selected first** on the one to preselect.

= Do order bumps work on the main checkout? =

Not yet. In this version order bumps are shown on landing page order forms.

= Do I need the Cartiva theme? =

No. Any theme that supports the block editor works. With Cartiva you get ready-made landing sections and matching styles.

= Where is the order form? Can I move it? =

New landing pages already contain the Order form block. You can move it anywhere on the page, or add it again from the block inserter. Buttons that link to `#order` scroll to it.

= What happens to the visitor's cart? =

Opening a landing page replaces the cart with the landing page's product, so the order contains only that product.

= Can I hide the country field? =

Yes. When the country field is hidden, orders use your store's country.

= Is it really free? =

Yes. Every feature is in this plugin. There is no pro version.

== Screenshots ==

1. A landing page order form with package options and an order bump.
2. The landing page settings box: products, bumps, layout, payment methods and fields.
3. A funnel with its steps and results.
4. The main checkout field editor.

== Changelog ==

= 1.1.0 =
* New: Funnels with steps (sales page, checkout, thank you), a one-click funnel template and funnel results.
* New: Product options: pick one package or several products, each with its own quantity, discount and badge.
* New: Order bumps with discount, text, minimum order amount and two positions.
* New: Checkout layouts: one column, two columns, two steps.
* New: Inline coupon field on landing pages; option to hide the coupon link on the main checkout.
* Landing pages without a product work as information or thank-you steps.

= 1.0.0 =
* First release: landing pages with an order form block, per-form checkout fields, main checkout field editor, payment methods per landing page, thank-you page, Buy now button, and landing page results.

== Upgrade Notice ==

= 1.1.0 =
Adds funnels, product packs, order bumps and checkout layouts. Existing landing pages keep working unchanged.

= 1.0.0 =
First release.
