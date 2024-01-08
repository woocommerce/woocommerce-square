<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 03 January 2022.
 */
?>

<?php $token_input_name = $input_name . '[' . $index . ']'; ?>

<tr class="token <?php echo ! $token->get_token() ? 'new-token' : ''; ?>">

	<?php foreach ( $fields as $field_id => $field ) : ?>

		<?php
		$field_value = '';

		switch ( $field_id ) {
			case 'id':
				$field_value = $token->get_token();
				break;

			case 'card_type':
				$field_value = $token->get_card_type();
				break;

			case 'last_four':
				$field_value = $token->get_last4();
				break;

			case 'expiry':
				$field_value = $token->get_expiry_month() . '/' . $token->get_expiry_year();
				break;

			default:
				break;
		}
		?>

		<?php $is_select = 'select' === $field['type'] && isset( $field['options'] ) && ! empty( $field['options'] ); ?>

		<td class="token-<?php echo esc_attr( $field_id ); ?>">

			<?php if ( ! $field['editable'] ) : ?>

				<?php $display_value = $is_select && ! empty( $field['options'][ $token[ $field_id ] ] ) ? $field['options'][ $token[ $field_id ] ] : $field_value; ?>

				<span class="token-<?php echo esc_attr( $field_id ); ?> token-attribute"><?php echo esc_attr( $display_value ); ?></span>

				<input name="<?php echo esc_attr( $token_input_name ); ?>[<?php echo esc_attr( $field_id ); ?>]" value="<?php echo esc_attr( $field_value ); ?>" type="hidden" />

			<?php elseif ( $is_select ) : ?>

				<select name="<?php echo esc_attr( $token_input_name ); ?>[<?php echo esc_attr( $field_id ); ?>]">

					<option value=""><?php esc_html_e( '-- Select an option --', 'woocommerce-square' ); ?></option>

					<?php foreach ( $field['options'] as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $field_value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>

				</select>

			<?php else : ?>

				<?php
				// Build the input attributes
				$attributes = array();

				foreach ( $field['attributes'] as $name => $value ) {
					$attributes[] = esc_attr( $name ) . '=' . esc_attr( $value );
				}
				?>

				<input
					name="<?php echo esc_attr( $token_input_name ); ?>[<?php echo esc_attr( $field_id ); ?>]"
					value="<?php echo esc_attr( $field_value ); ?>"
					type="text"
					<?php echo esc_attr( implode( ' ', $attributes ) ); ?>
					<?php echo $field['required'] ? 'required' : ''; ?>
				/>

			<?php endif; ?>

		</td>

	<?php endforeach; ?>

	<input name="<?php echo esc_attr( $token_input_name ); ?>[type]" value="<?php echo esc_attr( $type ); ?>" type="hidden" />

	<td class="token-default token-attribute">
		<input name="<?php echo esc_attr( $input_name ); ?>_default" value="<?php echo esc_attr( $token->get_token() ); ?>" type="radio" <?php checked( true, $token->is_default() ); ?>/>
	</td>

	<?php // Token actions ?>
	<td class="token-actions">

		<?php foreach ( $actions as $action_key => $label ) : ?>
				<button
					class="sv-wc-payment-gateway-token-action-button button"
					data-action="<?php echo esc_attr( $action_key ); ?>"
					data-token-id="<?php echo esc_attr( $token->get_token() ); ?>"
					data-payment-token-id="<?php echo esc_attr( $token->get_id() ); ?>"
					data-user-id="<?php echo esc_attr( $user_id ); ?>"
				>
					<?php echo esc_attr( $label ); ?>
				</button>
		<?php endforeach; ?>

	</td>

</tr>
