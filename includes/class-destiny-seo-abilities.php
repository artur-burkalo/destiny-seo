<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Destiny_SEO_Abilities {

	public static function init(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			'destiny-seo',
			array(
				'label'       => __( 'Destiny SEO', 'destiny-seo' ),
				'description' => __( 'WordPress content and Search Console SEO abilities.', 'destiny-seo' ),
			)
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'destiny-seo/get-content',
			array(
				'label'       => __( 'Get content', 'destiny-seo' ),
				'description' => __( 'Returns a safe content payload for a WordPress content item.', 'destiny-seo' ),
				'category'    => 'destiny-seo',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => static function ( $input ): bool {
					$post_id = absint( $input['post_id'] ?? 0 );
					return $post_id > 0 && current_user_can( 'edit_post', $post_id );
				},
				'execute_callback' => static function ( $input ) {
					return Destiny_SEO_Reviewer::get_content_payload( absint( $input['post_id'] ?? 0 ) );
				},
			)
		);

		wp_register_ability(
			'destiny-seo/get-search-performance',
			array(
				'label'       => __( 'Get Search Console performance', 'destiny-seo' ),
				'description' => __( 'Returns Search Console query performance for a WordPress content item.', 'destiny-seo' ),
				'category'    => 'destiny-seo',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
						'days'    => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 180 ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => static function ( $input ): bool {
					$post_id = absint( $input['post_id'] ?? 0 );

					return Destiny_SEO_Search_Console::is_ready()
						&& $post_id > 0
						&& current_user_can( 'edit_post', $post_id );
				},
				'execute_callback' => static function ( $input ) {
					$post_id = absint( $input['post_id'] ?? 0 );
					$url     = get_permalink( $post_id );

					if ( ! $url ) {
						return new WP_Error(
							'destiny_seo_url_missing',
							__( 'Could not determine the content URL.', 'destiny-seo' )
						);
					}

					return Destiny_SEO_Search_Console::get_page_performance(
						$url,
						absint( $input['days'] ?? 90 )
					);
				},
			)
		);

		wp_register_ability(
			'destiny-seo/review-content',
			array(
				'label'       => __( 'Review content with AI', 'destiny-seo' ),
				'description' => __( 'Reviews WordPress content using the configured AI provider and real Search Console data.', 'destiny-seo' ),
				'category'    => 'destiny-seo',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => static function ( $input ): bool {
					$post_id = absint( $input['post_id'] ?? 0 );

					return Destiny_SEO_Search_Console::is_ready()
						&& $post_id > 0
						&& current_user_can( 'edit_post', $post_id );
				},
				'execute_callback' => static function ( $input ) {
					return Destiny_SEO_Reviewer::review( absint( $input['post_id'] ?? 0 ) );
				},
			)
		);

		wp_register_ability(
			'destiny-seo/create-improved-draft',
			array(
				'label'       => __( 'Create improved draft', 'destiny-seo' ),
				'description' => __( 'Creates a separate AI-improved Gutenberg draft without modifying or publishing the source post.', 'destiny-seo' ),
				'category'    => 'destiny-seo',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema' => array( 'type' => 'object' ),
				'permission_callback' => static function ( $input ): bool {
					$post_id = absint( $input['post_id'] ?? 0 );

					return Destiny_SEO_Search_Console::is_ready()
						&& $post_id > 0
						&& current_user_can( 'edit_post', $post_id );
				},
				'execute_callback' => static function ( $input ) {
					return Destiny_SEO_Drafts::create_improved_draft( absint( $input['post_id'] ?? 0 ) );
				},
			)
		);
	}
}
