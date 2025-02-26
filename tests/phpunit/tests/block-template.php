<?php
/**
 * Tests_Block_Template class
 *
 * @package WordPress
 */

/**
 * Tests for the block template loading algorithm.
 *
 * @group block-templates
 */
class Tests_Block_Template extends WP_UnitTestCase {
	private static $post;

	private static $template_canvas_path = ABSPATH . WPINC . '/template-canvas.php';

	public function set_up() {
		parent::set_up();
		switch_theme( 'block-theme' );
	}

	public function tear_down() {
		global $_wp_current_template_content;
		unset( $_wp_current_template_content );

		parent::tear_down();
	}

	function test_page_home_block_template_takes_precedence_over_less_specific_block_templates() {
		global $_wp_current_template_content;
		$type                   = 'page';
		$templates              = array(
			'page-home.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path = locate_block_template( get_stylesheet_directory() . '/page-home.php', $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/page-home.html', $_wp_current_template_content );
	}

	function test_page_block_template_takes_precedence() {
		global $_wp_current_template_content;
		$type                   = 'page';
		$templates              = array(
			'page-slug-doesnt-exist.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path = locate_block_template( get_stylesheet_directory() . '/page.php', $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/page.html', $_wp_current_template_content );
	}

	function test_block_template_takes_precedence_over_equally_specific_php_template() {
		global $_wp_current_template_content;
		$type                   = 'index';
		$templates              = array(
			'index.php',
		);
		$resolved_template_path = locate_block_template( get_stylesheet_directory() . '/index.php', $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/index.html', $_wp_current_template_content );
	}

	/**
	 * In a hybrid theme, a PHP template of higher specificity will take precedence over a block template
	 * with lower specificity.
	 *
	 * Covers https://github.com/WordPress/gutenberg/pull/29026.
	 */
	function test_more_specific_php_template_takes_precedence_over_less_specific_block_template() {
		$page_id_template       = 'page-1.php';
		$page_id_template_path  = get_stylesheet_directory() . '/' . $page_id_template;
		$type                   = 'page';
		$templates              = array(
			'page-slug-doesnt-exist.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path = locate_block_template( $page_id_template_path, $type, $templates );
		$this->assertSame( $page_id_template_path, $resolved_template_path );
	}

	/**
	 * If a theme is a child of a block-based parent theme but has php templates for some of its pages,
	 * a php template of the child will take precedence over the parent's block template if they have
	 * otherwise equal specificity.
	 *
	 * Covers https://github.com/WordPress/gutenberg/pull/31123.
	 *
	 */
	function test_child_theme_php_template_takes_precedence_over_equally_specific_parent_theme_block_template() {
		/**
		 * @todo This test is currently marked as skipped, since it wouldn't pass. Turns out that in Gutenberg,
		 * it only passed due to a erroneous test setup.
		 * For details, see https://github.com/WordPress/wordpress-develop/pull/1920#issuecomment-975929818.
		 */
		$this->markTestSkipped( 'The block template resolution algorithm needs fixing in order for this test to pass.' );

		switch_theme( 'block-theme-child' );

		$page_slug_template      = 'page-home.php';
		$page_slug_template_path = get_stylesheet_directory() . '/' . $page_slug_template;
		$type                    = 'page';
		$templates               = array(
			'page-home.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path  = locate_block_template( $page_slug_template_path, $type, $templates );
		$this->assertSame( $page_slug_template_path, $resolved_template_path );
	}

	function test_child_theme_block_template_takes_precedence_over_equally_specific_parent_theme_php_template() {
		global $_wp_current_template_content;

		switch_theme( 'block-theme-child' );

		$page_template                   = 'page-1.php';
		$parent_theme_page_template_path = get_template_directory() . '/' . $page_template;
		$type                            = 'page';
		$templates                       = array(
			'page-slug-doesnt-exist.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path          = locate_block_template( $parent_theme_page_template_path, $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertStringEqualsFile( get_stylesheet_directory() . '/templates/page-1.html', $_wp_current_template_content );
	}

	/**
	 * Regression: https://github.com/WordPress/gutenberg/issues/31399.
	 */
	public function test_custom_page_php_template_takes_precedence_over_all_other_templates() {
		$custom_page_template      = 'templates/full-width.php';
		$custom_page_template_path = get_stylesheet_directory() . '/' . $custom_page_template;
		$type                      = 'page';
		$templates                 = array(
			$custom_page_template,
			'page-slug.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path    = locate_block_template( $custom_page_template_path, $type, $templates );
		$this->assertSame( $custom_page_template_path, $resolved_template_path );
	}

	/**
	 * Covers: https://github.com/WordPress/gutenberg/pull/30438.
	 */
	public function test_custom_page_block_template_takes_precedence_over_all_other_templates() {
		global $_wp_current_template_content;

		// Set up custom template post.
		$args = array(
			'post_type'    => 'wp_template',
			'post_name'    => 'wp-custom-template-my-block-template',
			'post_title'   => 'My Custom Block Template',
			'post_content' => 'Content',
			'post_excerpt' => 'Description of my block template',
			'tax_input'    => array(
				'wp_theme' => array(
					get_stylesheet(),
				),
			),
		);
		$post = self::factory()->post->create_and_get( $args );
		wp_set_post_terms( $post->ID, get_stylesheet(), 'wp_theme' );

		$custom_page_block_template = 'wp-custom-template-my-block-template';
		$page_template_path         = get_stylesheet_directory() . '/' . 'page.php';
		$type                       = 'page';
		$templates                  = array(
			$custom_page_block_template,
			'page-slug.php',
			'page-1.php',
			'page.php',
		);
		$resolved_template_path     = locate_block_template( $page_template_path, $type, $templates );
		$this->assertSame( self::$template_canvas_path, $resolved_template_path );
		$this->assertSame( $post->post_content, $_wp_current_template_content );

		wp_delete_post( $post->ID );
	}

	/**
	 * Regression: https://github.com/WordPress/gutenberg/issues/31652.
	 */
	public function test_template_remains_unchanged_if_templates_array_is_empty() {
		$resolved_template_path = locate_block_template( '', 'search', array() );
		$this->assertSame( '', $resolved_template_path );
	}
}
