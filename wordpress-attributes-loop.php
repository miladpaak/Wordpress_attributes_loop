<?php
/**
 * Plugin Name: Woo Attribute Shortcodes for Elementor Loops
 * Description: List WooCommerce product attributes in admin, select attributes, and use shortcodes to output attribute terms in Elementor loop items and archives.
 * Version: 1.0.0
 * Author: OpenAI
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WAL_OPTION_KEY = 'wal_selected_attributes';

/**
 * Register admin menu.
 */
function wal_register_admin_menu() {
	if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
		return;
	}

	add_submenu_page(
		'woocommerce',
		__( 'Attribute Shortcodes', 'wal' ),
		__( 'Attribute Shortcodes', 'wal' ),
		'manage_woocommerce',
		'wal-attribute-shortcodes',
		'wal_render_admin_page'
	);
}
add_action( 'admin_menu', 'wal_register_admin_menu' );

/**
 * Render the admin settings page.
 */
function wal_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$attributes = function_exists( 'wc_get_attribute_taxonomies' ) ? wc_get_attribute_taxonomies() : array();
	$selected   = get_option( WAL_OPTION_KEY, array() );

	if ( isset( $_POST['wal_save'] ) ) {
		check_admin_referer( 'wal_save_attributes' );
		$selected = array();
		if ( isset( $_POST['wal_attributes'] ) && is_array( $_POST['wal_attributes'] ) ) {
			$selected = array_map( 'sanitize_text_field', wp_unslash( $_POST['wal_attributes'] ) );
		}
		update_option( WAL_OPTION_KEY, array_values( array_unique( $selected ) ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Selections saved.', 'wal' ) . '</p></div>';
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Attribute Shortcodes', 'wal' ); ?></h1>
		<p><?php esc_html_e( 'Check the attributes you want to use and copy the shortcode shown for each.', 'wal' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'wal_save_attributes' ); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Use', 'wal' ); ?></th>
						<th><?php esc_html_e( 'Attribute', 'wal' ); ?></th>
						<th><?php esc_html_e( 'Shortcode', 'wal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $attributes ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No WooCommerce attributes found.', 'wal' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $attributes as $attribute ) : ?>
							<?php
							$taxonomy  = wc_attribute_taxonomy_name( $attribute->attribute_name );
							$is_checked = in_array( $taxonomy, $selected, true );
							$shortcode  = sprintf( '[wc_attribute_items attribute="%s"]', esc_attr( $taxonomy ) );
							?>
							<tr>
								<td>
									<label>
										<input type="checkbox" name="wal_attributes[]" value="<?php echo esc_attr( $taxonomy ); ?>" <?php checked( $is_checked ); ?> />
									</label>
								</td>
								<td><?php echo esc_html( $attribute->attribute_label ); ?></td>
								<td><code><?php echo esc_html( $shortcode ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save Selections', 'wal' ), 'primary', 'wal_save' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Shortcode to output attribute items for a product.
 *
 * Usage: [wc_attribute_items attribute="pa_color" post_id="123"]
 */
function wal_attribute_items_shortcode( $atts ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'attribute' => '',
			'post_id'   => '',
			'separator' => ', ',
		),
		$atts,
		'wc_attribute_items'
	);

	$attribute = sanitize_text_field( $atts['attribute'] );
	if ( '' === $attribute ) {
		return '';
	}

	if ( 0 !== strpos( $attribute, 'pa_' ) ) {
		$attribute = wc_attribute_taxonomy_name( $attribute );
	}

	$post_id = $atts['post_id'] ? absint( $atts['post_id'] ) : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	$product = wc_get_product( $post_id );
	if ( ! $product ) {
		return '';
	}

	$terms = wc_get_product_terms( $post_id, $attribute, array( 'fields' => 'names' ) );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	$separator = sanitize_text_field( $atts['separator'] );
	return esc_html( implode( $separator, $terms ) );
}
add_shortcode( 'wc_attribute_items', 'wal_attribute_items_shortcode' );
