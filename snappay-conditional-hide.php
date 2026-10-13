<?php
/**
 * Plugin Name: SnappPay Conditional Gateway Hide
 * Plugin URI: https://github.com/nimashadman/snappay-conditional-hide
 * Description: مخفی کردن درگاه اسنپ‌پی ووکامرس بر اساس دسته‌بندی، برند یا محصولات مشخص.
 * Version: 1.0.0
 * Author: Developer Nima Shadman
 * Author URI: https://nimashadman.ir
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: snappay-conditional-hide
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
	exit;
}

final class SCH_SnappPay_Conditional_Hide {
	private const OPTION_KEY = 'sch_snappay_conditional_hide_options';
	private const DEFAULT_GATEWAY_ID = 'snappay';

	private static ?self $instance = null;

	public static function instance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
		add_action('admin_menu', [$this, 'add_settings_page']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_filter('woocommerce_available_payment_gateways', [$this, 'maybe_hide_gateway']);
	}

	public function declare_hpos_compatibility(): void {
		if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	}

	public function add_settings_page(): void {
		add_submenu_page(
			'woocommerce',
			__('نمایش درگاه اسنپ‌پی', 'snappay-conditional-hide'),
			__('نمایش درگاه اسنپ‌پی', 'snappay-conditional-hide'),
			'manage_woocommerce',
			'sch-snappay-conditional-hide',
			[$this, 'render_settings_page']
		);
	}

	public function register_settings(): void {
		register_setting(
			'sch_snappay_conditional_hide',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [$this, 'sanitize_options'],
				'default'           => $this->default_options(),
			]
		);
	}

	public function enqueue_admin_assets(string $hook): void {
		if ($hook !== 'woocommerce_page_sch-snappay-conditional-hide') {
			return;
		}

		if (function_exists('wc_enqueue_js')) {
			wp_enqueue_script('selectWoo');
			wp_enqueue_style('select2');
			wp_enqueue_script('wc-enhanced-select');
		}
	}

	public function sanitize_options($raw): array {
		$raw = is_array($raw) ? $raw : [];

		$options = [
			'enabled'        => !empty($raw['enabled']) ? 'yes' : 'no',
			'gateway_id'     => sanitize_key($raw['gateway_id'] ?? self::DEFAULT_GATEWAY_ID),
			'category_ids'   => $this->sanitize_int_list($raw['category_ids'] ?? []),
			'brand_taxonomy' => sanitize_key($raw['brand_taxonomy'] ?? ''),
			'brand_term_ids' => $this->sanitize_int_list($raw['brand_term_ids'] ?? []),
			'product_ids'    => $this->sanitize_int_list($raw['product_ids'] ?? []),
		];

		if ($options['gateway_id'] === '') {
			$options['gateway_id'] = self::DEFAULT_GATEWAY_ID;
		}

		if ($options['brand_taxonomy'] !== '' && !taxonomy_exists($options['brand_taxonomy'])) {
			$options['brand_taxonomy'] = '';
			$options['brand_term_ids'] = [];
		}

		return $options;
	}

	public function render_settings_page(): void {
		if (!current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'snappay-conditional-hide'));
		}

		$options = $this->get_options();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('نمایش درگاه اسنپ‌پی', 'snappay-conditional-hide'); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields('sch_snappay_conditional_hide'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__('وضعیت', 'snappay-conditional-hide'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($options['enabled'], 'yes'); ?>>
								<?php echo esc_html__('مخفی کردن اسنپ‌پی برای موارد انتخاب‌شده فعال باشد', 'snappay-conditional-hide'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sch_gateway_id"><?php echo esc_html__('شناسه درگاه', 'snappay-conditional-hide'); ?></label>
						</th>
						<td>
							<input id="sch_gateway_id" class="regular-text" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[gateway_id]" value="<?php echo esc_attr($options['gateway_id']); ?>">
							<p class="description"><?php echo esc_html__('معمولاً مقدار آن snappay است. اگر افزونه اسنپ‌پی شما شناسه دیگری برای درگاه ووکامرس دارد، همان را وارد کنید.', 'snappay-conditional-hide'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('دسته‌بندی‌ها', 'snappay-conditional-hide'); ?></th>
						<td>
							<?php $this->render_term_multiselect('category_ids', 'product_cat', $options['category_ids']); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sch_brand_taxonomy"><?php echo esc_html__('نوع برند', 'snappay-conditional-hide'); ?></label>
						</th>
						<td>
							<select id="sch_brand_taxonomy" name="<?php echo esc_attr(self::OPTION_KEY); ?>[brand_taxonomy]">
								<option value=""><?php echo esc_html__('بدون انتخاب برند', 'snappay-conditional-hide'); ?></option>
								<?php foreach ($this->get_product_taxonomies() as $taxonomy => $label) : ?>
									<option value="<?php echo esc_attr($taxonomy); ?>" <?php selected($options['brand_taxonomy'], $taxonomy); ?>>
										<?php echo esc_html($label . ' (' . $taxonomy . ')'); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html__('نوع برندِ افزونه فروشگاه را انتخاب کنید و یک بار ذخیره کنید تا لیست برندها نمایش داده شود.', 'snappay-conditional-hide'); ?></p>
						</td>
					</tr>
					<?php if ($options['brand_taxonomy'] !== '') : ?>
						<tr>
							<th scope="row"><?php echo esc_html__('برندها', 'snappay-conditional-hide'); ?></th>
							<td>
								<?php $this->render_term_multiselect('brand_term_ids', $options['brand_taxonomy'], $options['brand_term_ids']); ?>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php echo esc_html__('محصولات', 'snappay-conditional-hide'); ?></th>
						<td>
							<select
								class="wc-product-search"
								multiple="multiple"
								style="width: 50%;"
								name="<?php echo esc_attr(self::OPTION_KEY); ?>[product_ids][]"
								data-placeholder="<?php echo esc_attr__('جستجوی محصول...', 'snappay-conditional-hide'); ?>"
								data-action="woocommerce_json_search_products_and_variations">
								<?php foreach ($this->get_selected_products($options['product_ids']) as $product_id => $product_name) : ?>
									<option value="<?php echo esc_attr($product_id); ?>" selected="selected"><?php echo esc_html($product_name); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function maybe_hide_gateway(array $gateways): array {
		$options = $this->get_options();
		$gateway_id = $options['gateway_id'];

		if ($options['enabled'] !== 'yes' || $gateway_id === '' || !isset($gateways[$gateway_id]) || !function_exists('WC')) {
			return $gateways;
		}

		$cart = WC()->cart;
		if (!$cart || $cart->is_empty()) {
			return $gateways;
		}

		foreach ($cart->get_cart() as $cart_item) {
			$product_id = (int) ($cart_item['product_id'] ?? 0);
			$variation_id = (int) ($cart_item['variation_id'] ?? 0);

			if ($this->cart_item_matches_rules($product_id, $variation_id, $options)) {
				unset($gateways[$gateway_id]);
				break;
			}
		}

		return $gateways;
	}

	private function cart_item_matches_rules(int $product_id, int $variation_id, array $options): bool {
		$ids_to_check = array_filter([$product_id, $variation_id]);

		if (!empty(array_intersect($ids_to_check, $options['product_ids']))) {
			return true;
		}

		if ($product_id > 0 && !empty($options['category_ids']) && has_term($options['category_ids'], 'product_cat', $product_id)) {
			return true;
		}

		if (
			$product_id > 0
			&& $options['brand_taxonomy'] !== ''
			&& taxonomy_exists($options['brand_taxonomy'])
			&& !empty($options['brand_term_ids'])
			&& has_term($options['brand_term_ids'], $options['brand_taxonomy'], $product_id)
		) {
			return true;
		}

		return false;
	}

	private function render_term_multiselect(string $field, string $taxonomy, array $selected_ids): void {
		if (!taxonomy_exists($taxonomy)) {
			echo '<p>' . esc_html__('این نوع دسته‌بندی در سایت فعال نیست.', 'snappay-conditional-hide') . '</p>';
			return;
		}

		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]);

		if (is_wp_error($terms) || empty($terms)) {
			echo '<p>' . esc_html__('موردی پیدا نشد.', 'snappay-conditional-hide') . '</p>';
			return;
		}

		printf(
			'<select class="wc-enhanced-select" multiple="multiple" style="width: 50%%;" name="%s[%s][]" data-placeholder="%s">',
			esc_attr(self::OPTION_KEY),
			esc_attr($field),
			esc_attr__('انتخاب کنید...', 'snappay-conditional-hide')
		);

		foreach ($terms as $term) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $term->term_id,
				selected(in_array((int) $term->term_id, $selected_ids, true), true, false),
				esc_html($term->name)
			);
		}

		echo '</select>';
	}

	private function get_selected_products(array $product_ids): array {
		$products = [];

		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);
			if ($product) {
				$products[$product_id] = $product->get_formatted_name();
			}
		}

		return $products;
	}

	private function get_product_taxonomies(): array {
		$taxonomies = get_object_taxonomies('product', 'objects');
		$result = [];
		$preferred = ['pa_brand', 'product_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand'];

		foreach ($preferred as $taxonomy) {
			if (isset($taxonomies[$taxonomy])) {
				$result[$taxonomy] = $taxonomies[$taxonomy]->labels->singular_name ?: $taxonomies[$taxonomy]->label;
			}
		}

		foreach ($taxonomies as $taxonomy => $object) {
			if ($taxonomy === 'product_cat' || $taxonomy === 'product_tag' || isset($result[$taxonomy])) {
				continue;
			}

			$label = $object->labels->singular_name ?: $object->label;
			$result[$taxonomy] = $label ?: $taxonomy;
		}

		return $result;
	}

	private function sanitize_int_list($value): array {
		$value = is_array($value) ? $value : [];
		$value = array_map('absint', $value);
		$value = array_filter($value);

		return array_values(array_unique($value));
	}

	private function get_options(): array {
		return wp_parse_args(get_option(self::OPTION_KEY, []), $this->default_options());
	}

	private function default_options(): array {
		return [
			'enabled'        => 'yes',
			'gateway_id'     => self::DEFAULT_GATEWAY_ID,
			'category_ids'   => [],
			'brand_taxonomy' => '',
			'brand_term_ids' => [],
			'product_ids'    => [],
		];
	}
}

add_action('plugins_loaded', static function (): void {
	if (class_exists('WooCommerce')) {
		SCH_SnappPay_Conditional_Hide::instance();
	}
});
