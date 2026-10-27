<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Destiny_SEO_Editor {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
	}

	public static function register_meta_boxes(): void {
		foreach ( Destiny_SEO_Reviewer::get_reviewable_post_types() as $post_type => $object ) {
			add_meta_box(
				'destiny-seo-review',
				__( 'Destiny SEO', 'destiny-seo' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public static function render_meta_box( WP_Post $post ): void {
		$review = Destiny_SEO_Reviewer::get_saved_review( $post->ID );
		$gsc    = Destiny_SEO_Reviewer::get_saved_gsc( $post->ID );
		$ready  = Destiny_SEO_Search_Console::is_ready();

		if ( ! $ready ) {
			echo '<p>' . esc_html__( 'Connect Search Console and select a property before reviewing this content.', 'destiny-seo' ) . '</p>';
			return;
		}

		if ( $review && ! empty( $review['review'] ) ) {
			$data = $review['review'];
			$score = absint( $data['score'] ?? 0 );

			echo '<div class="destiny-seo-editor__score"><strong>' . esc_html( (string) $score ) . '/100</strong><br><span>' . esc_html( Destiny_SEO_Reviewer::get_score_label( $score ) ) . '</span></div>';

			if ( ! empty( $gsc['totals'] ) ) {
				$totals = $gsc['totals'];
				echo '<p><strong>' . esc_html__( 'Search Console (90 days)', 'destiny-seo' ) . '</strong></p>';
				echo '<ul>';
				echo '<li>' . esc_html__( 'Impressions:', 'destiny-seo' ) . ' ' . esc_html( number_format_i18n( (float) ( $totals['impressions'] ?? 0 ) ) ) . '</li>';
				echo '<li>' . esc_html__( 'Clicks:', 'destiny-seo' ) . ' ' . esc_html( number_format_i18n( (float) ( $totals['clicks'] ?? 0 ) ) ) . '</li>';
				echo '<li>' . esc_html__( 'CTR:', 'destiny-seo' ) . ' ' . esc_html( number_format_i18n( (float) ( $totals['ctr'] ?? 0 ) * 100, 2 ) . '%' ) . '</li>';
				echo '<li>' . esc_html__( 'Position:', 'destiny-seo' ) . ' ' . esc_html( number_format_i18n( (float) ( $totals['position'] ?? 0 ), 1 ) ) . '</li>';
				echo '</ul>';
			}

			if ( ! empty( $data['issues'] ) ) {
				echo '<p><strong>' . esc_html__( 'Top issues', 'destiny-seo' ) . '</strong></p><ul>';
				foreach ( array_slice( (array) $data['issues'], 0, 3 ) as $item ) {
					echo '<li>' . esc_html( (string) $item ) . '</li>';
				}
				echo '</ul>';
			}

			if ( ! empty( $data['seo_opportunities'] ) ) {
				echo '<p><strong>' . esc_html__( 'Top opportunities', 'destiny-seo' ) . '</strong></p><ul>';
				foreach ( array_slice( (array) $data['seo_opportunities'], 0, 3 ) as $item ) {
					echo '<li>' . esc_html( (string) $item ) . '</li>';
				}
				echo '</ul>';
			}
		} else {
			echo '<p>' . esc_html__( 'This content has not been reviewed yet.', 'destiny-seo' ) . '</p>';
		}

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
			<input type="hidden" name="action" value="destiny_seo_review">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
			<?php wp_nonce_field( 'destiny_seo_review' ); ?>
			<button type="submit" class="button button-secondary" style="width:100%;">
				<?php echo $review ? esc_html__( 'Review again', 'destiny-seo' ) : esc_html__( 'Review with AI', 'destiny-seo' ); ?>
			</button>
		</form>

		<?php if ( $review ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
				<input type="hidden" name="action" value="destiny_seo_create_draft">
				<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
				<?php wp_nonce_field( 'destiny_seo_create_draft' ); ?>
				<button type="submit" class="button button-primary" style="width:100%;" onclick="return confirm('<?php echo esc_js( __( 'Generate a new improved draft? The current post will not be changed.', 'destiny-seo' ) ); ?>');">
					<?php esc_html_e( 'Generate improved draft', 'destiny-seo' ); ?>
				</button>
			</form>
			<p class="description"><?php esc_html_e( 'Creates a separate draft. It will not publish or overwrite this post.', 'destiny-seo' ); ?></p>
		<?php endif; ?>
		<?php
	}
}
