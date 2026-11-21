=== Cartiva Commerce ===
Contributors: godevs
Tags: woocommerce, sales funnel, upsell, abandoned cart, order bump
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sales funnels, one-click upsells, order bumps, abandoned checkout recovery, a slide-out cart, BOGO deals, A/B tests and analytics for WooCommerce. All free.

== Description ==

Cartiva Commerce turns a single product into a focused sales page with the order form on the same page, the way funnel builders do, completely free.

It works with any theme. With the free [Cartiva](https://wordpress.org/themes/cartiva/) theme, landing pages start with ready-made sections (offer bar, benefits, steps, comparison, guarantee, FAQ) and the order form picks up your theme colors.

= Sales funnels =

* A funnel leads shoppers through steps: a sales page, a checkout and a thank-you page.
* **Create a funnel from the template** in one click: three draft steps with ready-made content (the Cartiva theme's landing sections when you use it).
* Buttons on a sales page that link to `#order` open the next step automatically.
* Buyers land on the funnel's thank-you step with their order details.
* Visits, orders, revenue and conversion for each funnel.

= One-click upsells and downsells =

* Right after the order, show an **Upsell** step: one click on "Yes" adds the product to the same order, at your offer price. Nothing to fill in again.
* "No thanks" shows the **Downsell** step placed after it, with a smaller or cheaper offer.
* Several upsells in a row, each with its own product, quantity, discount and button texts.
* Works with Cash on delivery, bank transfer and check. Orders paid another way skip the offers and go straight to the thank-you step.
* Stock, order totals and order notes are updated, and the offer product is marked on the order.
* **Order details** block for thank-you steps: order number, items, total, payment and delivery details, plus the payment method's instructions (for example your bank details).

= Abandoned checkout recovery =

* When shoppers enter their phone or email on an order form but do not order, they appear under **Cartiva Funnels > Abandoned checkouts** with their cart, a **Call** button and a **WhatsApp** button with a ready message.
* Up to three **reminder emails** (after 1 hour, 24 hours and optionally 72 hours), with a "Finish my order" link that puts the cart back and fills in their details.
* Optional **one-time coupon** in a reminder, locked to the shopper's email and applied automatically from the link.
* An order from the same shopper (same session, phone or email) marks the checkout **Recovered**; see abandoned, recovered, recovery rate and recovered revenue for the last 30 days.
* Privacy: a notice on the form, a "Stop these reminders" link in every email, automatic deletion after 30 days, and WordPress personal data export and erase.
* Developers can send an SMS with the `cartiva_commerce_checkout_abandoned` action.

= Slide-out cart, free shipping bar and deals =

* **Slide-out cart** that opens when a product is added: change quantities, remove products and check out without leaving the page. Optional floating cart button, or open it from your header's cart link.
* **"You may also like"** in the cart, from each product's cross-sells, with one-click Add.
* **Free shipping bar**: "Add ৳250 more for free shipping", using your Free shipping method's minimum amount (or your own amount), in the cart drawer and on the cart and checkout pages.
* **Buy X, get Y deals** (BOGO): the gift is added by itself when the cart qualifies, free or at a discount, and removed if the shopper buys less. Works for "Buy 1, get 1 free" on the same product, and repeats for every set.
* **Coupons that apply themselves**: tick "Apply automatically" on any WooCommerce coupon, and it is added as soon as the cart meets its conditions (such as a minimum spend).

= Analytics and A/B tests =

* **Analytics** page: revenue, orders, average order, conversion, order bump and upsell acceptance for every funnel and landing page, for the last 7, 30 or 90 days or 12 months, with revenue per day.
* **A/B tests** on any landing page or funnel step: create variant B from a copy in one click, choose the share of visitors who see it, and compare visits, orders, conversion and revenue. Each visitor always sees the same version.
* When B wins, one click moves it onto the original page, so its address, funnel position and history stay.
* Bots and your own visits while logged in are not counted.

= Facebook, Google Analytics 4 and TikTok tracking =

* Add your Meta Pixel ID, GA4 Measurement ID and TikTok Pixel ID in **WooCommerce > Cartiva Commerce > Tracking**.
* Events: PageView, ViewContent / view_item, InitiateCheckout / begin_checkout, AddToCart when an order bump is added, and Purchase.
* Purchase is sent once per order, at the end of the funnel, with the final value after upsells, and with an event ID so it is not counted twice.
* Optional tracking for the rest of the store. Respects consent plugins that support the WP Consent API.

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

= Languages =

* Bangla (bn_BD) translation included, for the shop front and the admin.
* Right-to-left languages are supported (the cart slides in from the left).

= Good to know =

* Order forms use WooCommerce's classic checkout, because the block checkout does not allow core fields to be changed or hidden. Your main checkout page can stay on the block checkout; the main-checkout field settings apply when it uses the classic checkout, and the settings page can switch it for you in one click.
* Works with High-Performance Order Storage (HPOS).
* No external services, no tracking, no ads, no upsell screens.

== Installation ==

1. Install and activate WooCommerce.
2. Go to **Plugins > Add New**, search for **Cartiva Commerce**, install and activate it.
3. Go to **Cartiva Funnels** and click **Create a funnel from the template** (or **Template with upsell and downsell**).
4. Open the **Checkout** step, add the product in the **Landing page: product and order form** box, choose the fields and payment methods.
5. Check the text on the three steps and publish them. Visit the first step and place a test order.

For a single page that sells on its own, go to **Cartiva Funnels > Landing pages > Add landing page** instead.

To change your main checkout fields or the Buy now button, go to **WooCommerce > Cartiva Commerce**.

== Frequently Asked Questions ==

= Does changing a landing page's fields change my main checkout? =

No. Every landing page keeps its own fields, and the main checkout keeps its own. The plugin checks which form sent each request, so the live totals and the Place order step always use the right rules.

= How do I make a "Buy 1, 2 or 3" offer? =

In the landing page box, set **How shoppers choose** to "Shoppers pick one option". Add the same product three times with quantity 1, 2 and 3, give the bigger packs a discount and a badge, and tick **Selected first** on the one to preselect.

= Why don't upsells show for card payments? =

A card payment is charged when the order is placed. Adding a product afterwards would need a second charge on the saved card, which depends on the payment plugin. This version adds offers to orders that are paid later (Cash on delivery, bank transfer, check). Developers can allow other methods with the `cartiva_commerce_upsell_gateways` filter, but only for methods that charge later.

= Does the order email include the upsell? =

WooCommerce sends the order email when the order is placed, before the buyer sees the offer, so that first email lists the original items. The order in your dashboard, the thank-you step and later emails (such as Completed) include the upsell.

= How long can buyers accept an offer? =

Two hours after the order, while it is pending, on hold or processing. Developers can change this with the `cartiva_commerce_offer_window` filter.

= My customers rarely give an email. Does recovery still help? =

Yes. Most order forms ask for a mobile number, and every abandoned checkout with a phone number shows Call and WhatsApp buttons. The WhatsApp message includes the shopper's name, products and the link that restores their order. Reminder emails go only to shoppers who entered an email.

= Do reminder emails need a cron job? =

They use WordPress's scheduled tasks, which run when your site has visitors. On low-traffic sites, set up a real cron job for wp-cron.php (most hosts have this in their control panel) so reminders go out on time.

= My theme already has a mini cart. Will there be two? =

The Cartiva Commerce drawer opens after adding to cart, or from its own floating button. It only replaces your header cart when you tick "Open it from the cart links and mini cart in your header". You can switch the drawer off in WooCommerce > Cartiva Commerce > Cart & deals.

= Do BOGO gifts work on landing pages? =

Yes. Gifts are added to landing page order forms too, and keep their own price.

= Is the A/B test result reliable? =

Wait until each version has about 30 orders or more. With fewer orders, differences are often luck. The page shows visits, orders, conversion and revenue for both versions since the test started.

= Does tracking work with my cookie banner? =

If your consent plugin supports the WP Consent API (for example Complianz or CookieYes), pixels load only after the visitor accepts marketing cookies. Developers can also use the `cartiva_commerce_tracking_allowed` filter.

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
5. Funnel analytics with revenue per day.
6. An A/B test with results for both versions.
7. Abandoned checkouts with Call and WhatsApp buttons.
8. The slide-out cart with the free shipping bar and suggestions.

== Changelog ==

= 1.6.0 =
* New: Bangla (bn_BD) translation.
* New: Right-to-left language support for the order form and slide-out cart.
* New: With the Cartiva theme, funnel templates use its step designs, landing pages use its distraction-free header and footer, and the theme's header and mobile bar carts open the slide-out cart.

= 1.5.0 =
* New: Slide-out cart with quantity changes, remove, cross-sell suggestions, optional floating button and header link takeover.
* New: Free shipping progress bar (drawer, cart and checkout).
* New: Buy X, get Y deals with free or discounted gifts that add and remove themselves.
* New: "Apply automatically" option on WooCommerce coupons.

= 1.4.0 =
* New: Abandoned checkout recovery: saved details with a notice on the form, list with Call and WhatsApp buttons, up to three reminder emails with a cart-restoring link, optional one-time coupon, recovered revenue.
* New: Personal data export and erase for abandoned checkouts; uninstalling removes them.

= 1.3.0 =
* New: Analytics page with date ranges, per funnel and landing page results, bump and upsell acceptance, and revenue per day.
* New: A/B tests with a one-click variant copy, traffic split, results and "B wins" switch.
* New: Facebook Pixel, Google Analytics 4 and TikTok Pixel events, Purchase sent once with the final value.
* Improved: Bots and logged-in editors are no longer counted as visits; visits are recorded per day.

= 1.2.0 =
* New: One-click upsell and downsell steps that add the offer to the same order (Cash on delivery, bank transfer, check).
* New: Upsell offer block and Order details block for thank-you steps.
* New: Funnel template with upsell and downsell.
* Improved: Thank-you steps show the order details where you place the block.

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

= 1.6.0 =
Adds the Bangla translation, right-to-left support and better integration with the Cartiva theme.

= 1.5.0 =
Adds a slide-out cart, free shipping bar, BOGO deals and coupons that apply themselves.

= 1.4.0 =
Adds abandoned checkout recovery with reminder emails, coupons and WhatsApp follow-up.

= 1.3.0 =
Adds analytics, A/B tests and Facebook, GA4 and TikTok tracking.

= 1.2.0 =
Adds one-click upsells and downsells, and an order details block for thank-you pages.

= 1.1.0 =
Adds funnels, product packs, order bumps and checkout layouts. Existing landing pages keep working unchanged.

= 1.0.0 =
First release.
