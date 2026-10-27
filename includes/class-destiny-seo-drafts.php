<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Destiny_SEO_Drafts {

	public static function create_improved_draft( int $post_id ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new WP_Error(
				'destiny_seo_ai_missing',
				__( 'The WordPress AI Client is unavailable or no compatible AI provider is configured.', 'destiny-seo' )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'destiny_seo_forbidden', __( 'You cannot edit this content.', 'destiny-seo' ) );
		}

		if ( ! Destiny_SEO_Search_Console::is_ready() ) {
			return new WP_Error(
				'destiny_seo_gsc_not_ready',
				__( 'Connect Search Console and select a property first.', 'destiny-seo' )
			);
		}

		$content = Destiny_SEO_Reviewer::get_content_payload( $post_id );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$review = Destiny_SEO_Reviewer::get_saved_review( $post_id );

		if ( ! $review || empty( $review['review'] ) ) {
			$result = Destiny_SEO_Reviewer::review( $post_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$review = Destiny_SEO_Reviewer::get_saved_review( $post_id );
		}

		$gsc = Destiny_SEO_Reviewer::get_saved_gsc( $post_id );

		$prompt = sprintf(
			'Improve this WordPress Gutenberg post for SEO and usefulness.

Important rules:
- Return ONLY valid Gutenberg block markup.
- Preserve the factual meaning of the original content.
- Do not invent product features, statistics, claims, rankings, traffic, testimonials, pricing, or business facts.
- Use the SEO review and Search Console evidence to improve clarity, structure, headings, internal relevance, and completeness.
- Keep the tone professional and natural.
- Do not include a post title in the block content.
- Do not publish anything; this output will be saved as a draft.

Original title:
%1$s

Original Gutenberg content:
%2$s

Latest SEO review:
%3$s

Search Console:
%4$s',
			(string) $content['title'],
			(string) $content['raw'],
			wp_json_encode( $review['review'] ?? array(), JSON_PRETTY_PRINT ),
			wp_json_encode( $gsc ?? array(), JSON_PRETTY_PRINT )
		);

		$result = wp_ai_client_prompt( $prompt )->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$generated = trim( (string) $result );
		$generated = preg_replace( '/^```(?:html)?\s*/i', '', $generated );
		$generated = preg_replace( '/\s*```$/', '', (string) $generated );

		$blocks = parse_blocks( (string) $generated );

		if ( empty( $blocks ) ) {
			return new WP_Error(
				'destiny_seo_invalid_blocks',
				__( 'The AI response did not contain valid Gutenberg content.', 'destiny-seo' )
			);
		}

		$review_data = $review['review'] ?? array();
		$new_title = ! empty( $review_data['suggested_title'] )
			? (string) $review_data['suggested_title']
			: (string) $content['title'] . ' — SEO Draft';

		$draft_id = wp_insert_post(
			array(
				'post_type'    => (string) $content['post_type'],
				'post_status'  => 'draft',
				'post_title'   => wp_strip_all_tags( $new_title ),
				'post_excerpt' => sanitize_textarea_field( (string) ( $review_data['suggested_excerpt'] ?? '' ) ),
				'post_content' => (string) $generated,
				'post_author'  => get_current_user_id(),
				'post_parent'  => 0,
				'meta_input'   => array(
					'_destiny_seo_source_post_id' => $post_id,
				),
			),
			true
		);

		if ( is_wp_error( $draft_id ) ) {
			return $draft_id;
		}

		return array(
			'source_post_id' => $post_id,
			'draft_post_id'  => (int) $draft_id,
			'edit_url'       => get_edit_post_link( $draft_id, 'raw' ),
			'status'         => 'draft',
		);
	}
}
