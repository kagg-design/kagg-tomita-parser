<?php

/**
 * Class KAGG_Tomita_Parser
 */
class KAGG_Tomita_Parser {

	const TOMITA_BINARY = 'tomita-linux64';
	const TOMITA_CONFIG = 'config.proto';

	/**
	 * Delimiter between post contents.
	 * Untouched by Tomita.
	 */
	const POST_DELIMITER = '<post>';

	/**
	 * Delimiter between post contents.
	 * Untouched by Tomita.
	 */
	const POST_FIELDS_DELIMITER = '<field>';

	/**
	 * Delimiter between post contents.
	 * Untouched by Tomita.
	 */
	const STRING_DELIMITER = '<string>';

	/**
	 * Class os <span> to highlight found search string.
	 */
	const SPAN_CLASS = 'tomita-search';

	/**
	 * @var string Search string.
	 */
	private $search = '';

	/**
	 * Tomita_Parser constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	protected function includes() {
		require_once TOMITA_PARSER_PATH . '/includes/simple_html_dom.php';
		require_once TOMITA_PARSER_PATH . '/includes/class-tomita-parser.php';
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		register_activation_hook( TOMITA_PARSER_FILE, array( $this, 'activate_plugin' ) );

		add_filter( 'the_posts', array( $this, 'the_posts_filter' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'wp_trim_excerpt', array( $this, 'wp_trim_excerpt_filter' ), 10, 2 );
	}

	/**
	 * Plugin activation hook.
	 * Make Tomita binary executable.
	 */
	public function activate_plugin() {
		$binary = TOMITA_PARSER_PATH . '/tomita-parser/' . self::TOMITA_BINARY;
		$mode   = fileperms( $binary );
		$mode   = $mode | 0111;
		chmod( $binary, $mode );
	}

	/**
	 * Filter the_posts hook.
	 *
	 * @param array $posts Posts got by WP_Query.
	 * @param WP_Query $query Current WP_Query instance.
	 *
	 * @return array
	 */
	public function the_posts_filter( $posts, $query ) {
		if ( ! $query->is_search ) {
			return $posts;
		}

		$this->search = get_search_query();

		$posts = $this->parse_posts( $posts, $this->search );

		return $posts;
	}

	/**
	 * Parse posts received by WP_Query.
	 *
	 * @param array $posts Posts received by WP_Query.
	 * @param string $search Search string.
	 *
	 * @return array
	 */
	public function parse_posts( $posts, $search ) {
		$all_contents = array();

		foreach ( $posts as $post ) {
			/** @var WP_Post $post */

			// Combine all contents.
			$all_content_arr = array(
				$this->get_plain_text( trim( $post->post_content, '.' ) . '.' ),
				$this->get_plain_text( trim( $post->post_excerpt, '.' ) . '.' ),
			);

			$all_content = implode( self::POST_FIELDS_DELIMITER, $all_content_arr );

			// Add to general array of posts.
			$all_contents[] = $all_content;
		}

		// Convert all contents to string with a fake delimiter as delimiter, as Tomita doesn't touch it.
		$all_content_str = implode( self::POST_DELIMITER, $all_contents );

		// We have to run parser only once, as it takes significant time to start (some 0.5s).
		$parser = new Tomita_Parser(
			TOMITA_PARSER_PATH . '/tomita-parser/' . self::TOMITA_BINARY,
			TOMITA_PARSER_PATH . '/tomita-parser/' . self::TOMITA_CONFIG
		);

		$result = $parser->run( $all_content_str );

		if ( false === $result ) {
			return $posts;
		}

		// $result['output'] is an array of phrases.
		array_walk( $result['output'], array( $this, 'clean_phrase' ) );
		$output_str = implode( self::STRING_DELIMITER, $result['output'] );

		// Parser sometimes changes <br> to < br>, <p> to < p >, and similar with spaces.
		// Clean leading and trailing spaces from tags.
		$output_str = $this->clean_tags( $output_str );

		// Explode $output_str by POST_DELIMITER - we should have same number of items, as $all_contents = number of posts on page.
		$output_arr = explode( self::POST_DELIMITER, $output_str );
		if ( count( $output_arr ) === count( $all_contents ) ) {
			$all_contents = $output_arr;
			foreach ( $posts as $key => $post ) {
				$post->post_title   = $this->highlight_string( $post->post_title, $search );
				$all_content_arr    = explode( self::POST_FIELDS_DELIMITER, $all_contents[ $key ] );
				$content            = isset( $all_content_arr[0] ) ? $all_content_arr[0] : '';
				$excerpt            = isset( $all_content_arr[1] ) ? $all_content_arr[1] : '';
				$post->post_content = $this->highlight_post_field( $content, $search );
				$post->post_excerpt = $this->highlight_post_field( $excerpt, $search );
				$posts[ $key ]      = $post;
			}
		}

		return $posts;
	}

	/**
	 * Get plain text from html content.
	 *
	 * @param string $content An html content.
	 *
	 * @return string
	 */
	private function get_plain_text( $content ) {
		// Clean spaces in tags.
		$content = $this->clean_tags( $content );

		// Clean out all html, leave only text.
		$html  = str_get_html( $content );
		$nodes = $html->find( 'text' );

		$texts = array();
		foreach ( $nodes as $node ) {
			$texts[] = $node->plaintext;
		}

		// Convert texts to string with a fake tag as delimiter, as Tomita doesn't touch it.
		return implode( self::STRING_DELIMITER, $texts );
	}

	/**
	 * Clean leading and trailing spaces from tags.
	 * Make < p   > as <p>, < br / > as <br/> and so on.
	 *
	 * @param $html string Html code.
	 *
	 * @return null|string|string[]
	 */
	private function clean_tags( $html ) {
		$html = preg_replace( '/<\s+/', '<', $html, - 1 );
		$html = preg_replace( '/\s+>/', '>', $html, - 1 );
		$html = preg_replace( '/\s+\/>/', '/>', $html, - 1 );

		return $html;
	}

	/**
	 * Clean resulted phrase.
	 *
	 * @param string $phrase
	 * @param $key
	 */
	private function clean_phrase( &$phrase, $key ) {
		$phrase = $this->clean_tags( $phrase );
		$phrase = str_replace(
			array(
				self::STRING_DELIMITER,
				' .',
			),
			array(
				'',
				'.',
			),
			$phrase
		);
	}

	/**
	 * Highlight search string in post title.
	 *
	 * @param string $title Post title.
	 * @param string $search Search string.
	 *
	 * @return null|string|string[]
	 */
	private function highlight_string( $title, $search ) {
		$result = preg_replace(
			'/(' . $search . ')/iu',
			'<span class="' . self::SPAN_CLASS . '">$1</span>',
			$title
		);

		return $result;
	}

	/**
	 * Highlight search string in post content.
	 *
	 * @param string $all_content_str All content.
	 * @param string $search Search string.
	 *
	 * @return string
	 */
	private function highlight_post_field( $all_content_str, $search ) {
		$replace = '<span class="' . self::SPAN_CLASS . '">' . $search . '</span>';

		// Explode all content in array of phrases.
		$texts   = explode( self::STRING_DELIMITER, $all_content_str );
		$results = array();

		foreach ( $texts as $text ) {
			$pos = mb_stripos( $text, $search );
			if ( false !== $pos ) {
				$results[] = str_ireplace( $search, $replace, $text );
			}
		}

		$result = implode( '', $results );

		return $result;
	}

	/**
	 * Trim excerpt filter.
	 * We need this, since when excerpt is empty (or cleared by this plugins as not containing the search string),
	 * WordPress outputs excerpt from the content, where strips all tags.
	 *
	 * @param $text
	 * @param $raw_excerpt
	 *
	 * @return null|string|string[]
	 */
	public function wp_trim_excerpt_filter( $text, $raw_excerpt ) {
		return $this->highlight_string( $text, $this->search );
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'tomita-parser', TOMITA_PARSER_URL . '/css/style.css', array(), TOMITA_PARSER_VERSION );
	}
}
