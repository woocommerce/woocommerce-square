#!/bin/bash

# Set permalink structure.
wp-env run tests-cli wp post create --post_type=page --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' --post_title="Checkout Old" --post_status=publish

# Activate the storefront theme.
wp-env run tests-cli wp theme activate storefront

# Enable the Square gateway.
wp-env run tests-cli wp wc payment_gateway update square_credit_card --user=1 --enabled=1
wp-env run tests-cli wp transient delete --all

# Create subscription product.
wp-env run tests-cli wp wc product create -- --name="Simple Subscription Product" --slug="simple-subscription-product" --user=1 --regular_price=10 --type=subscription --meta_data='[{"key":"_subscription_price","value":"10"},{"key":"_subscription_period","value":"month"},{"key":"_subscription_period_interval","value":"1"}]'

wp-env run tests-wordpress chmod -c ugo+w /var/www/html
wp-env run tests-cli wp rewrite structure '/%postname%/' --hard