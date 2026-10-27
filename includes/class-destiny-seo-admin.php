<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Destiny_SEO_Admin {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_destiny_seo_review', array( __CLASS__, 'handle_review' ) );
		add_action( 'admin_post_destiny_seo_bulk_review', array( __CLASS__, 'handle_bulk_review' ) );
		add_action( 'admin_post_destiny_seo_create_draft', array( __CLASS__, 'handle_create_draft' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Destiny SEO', 'destiny-seo' ),
			__( 'Destiny SEO', 'destiny-seo' ),
			'edit_posts',
			'destiny-seo',
			array( __CLASS__, 'render_page' ),
			'dashicons-chart-line',
			58
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_destiny-seo' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'destiny-seo-admin',
			plugins_url( 'assets/admin.css', DESTINY_SEO_FILE ),
			array(),
			DESTINY_SEO_VERSION
		);
	}

	public static function handle_review(): void {
		self::require_edit_access();

		check_admin_referer( 'destiny_seo_review' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to review this item.', 'destiny-seo' ) );
		}

		$result = Destiny_SEO_Reviewer::review( $post_id );

		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=destiny-seo' );

		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg( 'destiny_seo_error', rawurlencode( $result->get_error_message() ), $redirect );
		} else {
			$redirect = add_query_arg( 'destiny_seo_reviewed', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_bulk_review(): void {
		self::require_edit_access();

		check_admin_referer( 'destiny_seo_bulk_review' );

		$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) )
			: array();

		$mode = isset( $_POST['review_mode'] ) ? sanitize_key( wp_unslash( $_POST['review_mode'] ) ) : 'selected';

		if ( 'all' === $mode ) {
			$post_type = isset( $_POST['post_type_filter'] ) ? sanitize_key( wp_unslash( $_POST['post_type_filter'] ) ) : 'all';
			$post_types = array_keys( Destiny_SEO_Reviewer::get_reviewable_post_types() );

			$post_ids = get_posts(
				array(
					'post_type'      => 'all' === $post_type ? $post_types : $post_type,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'fields'         => 'ids',
					'posts_per_page' => 50,
				)
			);
		}

		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
		$success = 0;
		$errors  = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			$result = Destiny_SEO_Reviewer::review( $post_id );
			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$success++;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'destiny-seo',
					'tab'            => 'audit',
					'bulk_reviewed'   => $success,
					'bulk_errors'     => $errors,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_create_draft(): void {
		self::require_edit_access();

		check_admin_referer( 'destiny_seo_create_draft' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to improve this item.', 'destiny-seo' ) );
		}

		$result = Destiny_SEO_Drafts::create_improved_draft( $post_id );

		if ( is_wp_error( $result ) ) {
			$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=destiny-seo' );
			$redirect = add_query_arg( 'destiny_seo_error', rawurlencode( $result->get_error_message() ), $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}

		wp_safe_redirect( (string) $result['edit_url'] );
		exit;
	}

	private static function require_edit_access(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Destiny SEO.', 'destiny-seo' ) );
		}
	}

	public static function render_page(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'audit';
		?>
		<div class="wrap destiny-seo">
			<h1><?php esc_html_e( 'Destiny SEO', 'destiny-seo' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'audit' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'destiny-seo', 'tab' => 'audit' ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Content Audit', 'destiny-seo' ); ?>
				</a>
				<a class="nav-tab <?php echo 'search-console' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'destiny-seo', 'tab' => 'search-console' ), admin_url( 'admin.php' ) ) ); ?>">
					<?php esc_html_e( 'Search Console', 'destiny-seo' ); ?>
				</a>
			</nav>

			<?php
			if ( isset( $_GET['destiny_seo_error'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html(
					rawurldecode( sanitize_text_field( wp_unslash( $_GET['destiny_seo_error'] ) ) )
				) . '</p></div>';
			}

			if ( isset( $_GET['bulk_reviewed'] ) ) {
				$success = absint( $_GET['bulk_reviewed'] );
				$errors  = absint( $_GET['bulk_errors'] ?? 0 );
				echo '<div class="notice notice-success"><p>' . esc_html(
					sprintf(
						/* translators: 1: successful reviews, 2: failed reviews */
						__( 'Bulk review complete: %1$d successful, %2$d failed.', 'destiny-seo' ),
						$success,
						$errors
					)
				) . '</p></div>';
			}

			if ( 'search-console' === $tab ) {
				self::render_search_console();
			} else {
				self::render_audit();
			}
			?>
		</div>
		<?php
	}

	private static function render_audit(): void {
		$post_types = Destiny_SEO_Reviewer::get_reviewable_post_types();
		$filter = isset( $_GET['post_type_filter'] ) ? sanitize_key( wp_unslash( $_GET['post_type_filter'] ) ) : 'all';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$query = new WP_Query(
			array(
				'post_type'      => 'all' === $filter ? array_keys( $post_types ) : $filter,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 50,
				's'              => $search,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$ready = Destiny_SEO_Search_Console::is_ready();
		?>
		<section class="destiny-seo__toolbar">
			<form method="get" class="destiny-seo__filters">
				<input type="hidden" name="page" value="destiny-seo">
				<input type="hidden" name="tab" value="audit">

				<select name="post_type_filter">
					<option value="all"><?php esc_html_e( 'All content types', 'destiny-seo' ); ?></option>
					<?php foreach ( $post_types as $slug => $object ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filter, $slug ); ?>>
							<?php echo esc_html( $object->labels->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search content…', 'destiny-seo' ); ?>">
				<button class="button"><?php esc_html_e( 'Filter', 'destiny-seo' ); ?></button>
			</form>

			<?php if ( ! $ready ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Search Console is not connected and configured. Reviews are disabled until Google is connected and a property is selected.', 'destiny-seo' ); ?></p>
				</div>
			<?php endif; ?>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="destiny_seo_bulk_review">
			<input type="hidden" name="post_type_filter" value="<?php echo esc_attr( $filter ); ?>">
			<?php wp_nonce_field( 'destiny_seo_bulk_review' ); ?>

			<div class="destiny-seo__bulk-actions">
				<button type="submit" name="review_mode" value="selected" class="button button-primary" <?php disabled( ! $ready ); ?>>
					<?php esc_html_e( 'Review selected', 'destiny-seo' ); ?>
				</button>
				<button type="submit" name="review_mode" value="all" class="button" <?php disabled( ! $ready ); ?> onclick="return confirm('<?php echo esc_js( __( 'Review up to 50 matching content items? This may use many AI requests.', 'destiny-seo' ) ); ?>');">
					<?php esc_html_e( 'Review all matching', 'destiny-seo' ); ?>
				</button>
			</div>

			<div class="destiny-seo__table-wrap">
				<table class="widefat striped destiny-seo__table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.destiny-seo-row-check').forEach(cb => cb.checked = this.checked);"></td>
							<th><?php esc_html_e( 'Content', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Type', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Score', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Impressions', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'CTR', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Position', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Opportunity', 'destiny-seo' ); ?></th>
							<th><?php esc_html_e( 'Action', 'destiny-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $query->have_posts() ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'No content found.', 'destiny-seo' ); ?></td></tr>
						<?php endif; ?>

						<?php while ( $query->have_posts() ) : $query->the_post();
							$post_id = get_the_ID();
							$review  = Destiny_SEO_Reviewer::get_saved_review( $post_id );
							$gsc     = Destiny_SEO_Reviewer::get_saved_gsc( $post_id );

							$score = isset( $review['review']['score'] ) ? absint( $review['review']['score'] ) : null;
							$totals = is_array( $gsc ) && isset( $gsc['totals'] ) ? $gsc['totals'] : array();

							$impressions = (float) ( $totals['impressions'] ?? 0 );
							$clicks      = (float) ( $totals['clicks'] ?? 0 );
							$ctr         = (float) ( $totals['ctr'] ?? 0 );
							$position    = (float) ( $totals['position'] ?? 0 );
							?>
							<tr>
								<th class="check-column">
									<input class="destiny-seo-row-check" type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (string) $post_id ); ?>">
								</th>
								<td>
									<strong><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php the_title(); ?></a></strong>
									<div class="row-actions"><span><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'destiny-seo' ); ?></a></span></div>
								</td>
								<td><?php echo esc_html( get_post_type_object( get_post_type( $post_id ) )->labels->singular_name ?? get_post_type( $post_id ) ); ?></td>
								<td>
									<?php if ( null === $score ) : ?>
										<span class="destiny-seo__muted">—</span>
									<?php else : ?>
										<span class="destiny-seo__score-pill"><?php echo esc_html( (string) $score ); ?>/100</span>
										<small><?php echo esc_html( Destiny_SEO_Reviewer::get_score_label( $score ) ); ?></small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( $impressions ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $clicks ) ); ?></td>
								<td><?php echo esc_html( $impressions > 0 ? number_format_i18n( $ctr * 100, 2 ) . '%' : '—' ); ?></td>
								<td><?php echo esc_html( $position > 0 ? number_format_i18n( $position, 1 ) : '—' ); ?></td>
								<td><?php echo esc_html( Destiny_SEO_Reviewer::get_opportunity_label( $gsc ) ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
										<?php esc_html_e( 'Open post', 'destiny-seo' ); ?>
									</a>
								</td>
							</tr>
						<?php endwhile; wp_reset_postdata(); ?>
					</tbody>
				</table>
			</div>
		</form>
		<?php
	}

	private static function render_search_console(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<p>' . esc_html__( 'Administrator access is required to configure Search Console.', 'destiny-seo' ) . '</p>';
			return;
		}

		$settings   = Destiny_SEO_Search_Console::get_settings();
		$connected  = Destiny_SEO_Search_Console::is_connected();
		$property   = Destiny_SEO_Search_Console::get_selected_property();
		$properties = $connected ? Destiny_SEO_Search_Console::get_properties() : array();
		?>
		<section class="destiny-seo__card destiny-seo__card--full">
			<h2><?php esc_html_e( 'Google Search Console', 'destiny-seo' ); ?></h2>

			<div class="destiny-seo__status <?php echo $connected ? 'is-connected' : 'is-disconnected'; ?>">
				<strong><?php esc_html_e( 'Status:', 'destiny-seo' ); ?></strong>
				<?php echo $connected ? esc_html__( 'Connected', 'destiny-seo' ) : esc_html__( 'Not connected', 'destiny-seo' ); ?>
			</div>

			<h3><?php esc_html_e( 'Google OAuth credentials', 'destiny-seo' ); ?></h3>
			<p><?php esc_html_e( 'Create a Google OAuth Web Application for this WordPress site and add the redirect URI below to its authorised redirect URIs.', 'destiny-seo' ); ?></p>

			<p>
				<strong><?php esc_html_e( 'Redirect URI', 'destiny-seo' ); ?></strong><br>
				<code><?php echo esc_html( Destiny_SEO_Search_Console::get_redirect_uri() ); ?></code>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="destiny-seo__form">
				<input type="hidden" name="action" value="destiny_seo_save_google_settings">
				<?php wp_nonce_field( 'destiny_seo_save_google_settings' ); ?>

				<label for="client_id"><?php esc_html_e( 'Google Client ID', 'destiny-seo' ); ?></label>
				<input id="client_id" name="client_id" type="text" value="<?php echo esc_attr( $settings['client_id'] ); ?>" autocomplete="off">

				<label for="client_secret"><?php esc_html_e( 'Google Client Secret', 'destiny-seo' ); ?></label>
				<input id="client_secret" name="client_secret" type="password" value="" placeholder="<?php echo ! empty( $settings['client_secret'] ) ? esc_attr__( 'Saved — leave blank to keep current secret', 'destiny-seo' ) : ''; ?>" autocomplete="new-password">

				<?php submit_button( __( 'Save credentials', 'destiny-seo' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr>

			<?php if ( ! $connected ) : ?>
				<p><?php esc_html_e( 'Search Console is not connected. SEO reviews are disabled.', 'destiny-seo' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="destiny_seo_connect_google">
					<?php wp_nonce_field( 'destiny_seo_connect_google' ); ?>
					<?php submit_button(
						__( 'Connect Google Search Console', 'destiny-seo' ),
						'primary',
						'submit',
						false,
						Destiny_SEO_Search_Console::has_credentials() ? array() : array( 'disabled' => 'disabled' )
					); ?>
				</form>
			<?php else : ?>
				<h3><?php esc_html_e( 'Search Console property', 'destiny-seo' ); ?></h3>

				<?php if ( is_wp_error( $properties ) ) : ?>
					<div class="notice notice-error inline">
						<p><?php echo esc_html( $properties->get_error_message() ); ?></p>
					</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="destiny-seo__form">
						<input type="hidden" name="action" value="destiny_seo_save_property">
						<?php wp_nonce_field( 'destiny_seo_save_property' ); ?>

						<label for="property"><?php esc_html_e( 'Property', 'destiny-seo' ); ?></label>
						<select id="property" name="property">
							<option value=""><?php esc_html_e( 'Select a property…', 'destiny-seo' ); ?></option>
							<?php foreach ( $properties as $entry ) :
								$site_url = isset( $entry['siteUrl'] ) ? (string) $entry['siteUrl'] : '';
								if ( '' === $site_url ) {
									continue;
								}
								?>
								<option value="<?php echo esc_attr( $site_url ); ?>" <?php selected( $property, $site_url ); ?>>
									<?php echo esc_html( $site_url ); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<?php submit_button( __( 'Save property', 'destiny-seo' ), 'primary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<hr>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="destiny_seo_disconnect_google">
					<?php wp_nonce_field( 'destiny_seo_disconnect_google' ); ?>
					<?php submit_button( __( 'Disconnect Google', 'destiny-seo' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</section>
		<?php
	}
}
