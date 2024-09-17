#!/bin/bash

# Set permalink structure.
wp-env run tests-wordpress chmod -c ugo+w /var/www/html
wp-env run tests-cli wp option update woocommerce_block_product_tour_shown yes
wp-env run tests-cli wp post create --post_type=page --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' --post_title="Checkout Old" --post_status=publish
wp-env run tests-cli wp rewrite structure '/%postname%/' --hard

# Activate the storefront theme.
wp-env run tests-cli wp theme activate storefront

# Enable the Square gateway.
wp-env run tests-cli wp wc payment_gateway update square_credit_card --user=1 --enabled=1
wp-env run tests-cli wp transient delete --all

# Keep using old product editor.
wp-env run tests-cli wp option update woocommerce_feature_product_block_editor_enabled no --user=1
