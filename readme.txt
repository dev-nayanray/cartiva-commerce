=== Cartiva Commerce ===
Contributors: godevs
Tags: woocommerce, landing page, checkout, buy now, checkout fields
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One-product sales pages with their own short checkout, a Buy now button, and a checkout field editor for WooCommerce. Free, with no paid version.

== Description ==

Cartiva Commerce turns a single product into a focused sales page with the order form on the same page, the way funnel builders do, completely free.

It works with any theme. With the free [Cartiva](https://wordpress.org/themes/cartiva/) theme, landing pages start with ready-made sections (offer bar, benefits, steps, comparison, guarantee, FAQ) and the order form picks up your theme colors.

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
3. Go to **Landing pages > Add New**. Give the page a title, choose the product in the **Landing page: product and order form** box, choose the fields and payment methods, and publish.
4. Visit the page and place a test order.

To change your main checkout fields or the Buy now button, go to **WooCommerce > Cartiva Commerce**.

== Frequently Asked Questions ==

= Does changing a landing page's fields change my main checkout? =

No. Every landing page keeps its own fields, and the main checkout keeps its own. The plugin checks which form sent each request, so the live totals and the Place order step always use the right rules.

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

1. A landing page with the order form.
2. The landing page settings box: product, payment methods and fields.
3. The main checkout field editor.
4. Visits, orders, revenue and conversion per landing page.

== Changelog ==

= 1.0.0 =
* First release: landing pages with an order form block, per-form checkout fields, main checkout field editor, payment methods per landing page, thank-you page, Buy now button, and landing page results.

== Upgrade Notice ==

= 1.0.0 =
First release.
