<?php if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

class Brizy_Editor_Post /* extends Brizy_Editor_Project */
{

	const BRIZY_POST = 'brizy-post';

	/**
	 * @var Brizy_Editor_API_Page
	 */
	private $api_page;


	/**
	 * @var int
	 */
	private $wp_post_id;

	/**
	 * @var string
	 */
	private $draft;

	/**
	 * @var string
	 */
	private $compiled_html_body;

	/**
	 * @param $wp_post_id
	 *
	 * @return Brizy_Editor_Post
	 * @throws Brizy_Editor_Exceptions_UnsupportedPostType
	 */
	public static function get( $wp_post_id ) {
		if ( ! in_array( ( $type = get_post_type( $wp_post_id ) ), brizy()->supported_post_types() ) ) {
			throw new Brizy_Editor_Exceptions_UnsupportedPostType(
				"Brizy editor doesn't support '$type' post type"
			);
		}

		$brizy_editor_storage_post = Brizy_Editor_Storage_Post::instance( $wp_post_id );

		return $brizy_editor_storage_post->get( self::BRIZY_POST );
	}

	/**
	 * @param Brizy_Editor_Project $project
	 * @param WP_Post $post
	 *
	 * @return Brizy_Editor_Post
	 * @throws Brizy_Editor_Exceptions_UnsupportedPostType
	 */
	public static function create( $project, $post ) {
		if ( ! in_array( ( $type = get_post_type( $post->ID ) ), brizy()->supported_post_types() ) ) {
			throw new Brizy_Editor_Exceptions_UnsupportedPostType(
				"Brizy editor doesn't support '$type' post type"
			);
		}

		$api_page = Brizy_Editor_API_Page::get()
		                                 ->set_title( $post->post_title );

		$api_page = Brizy_Editor_User::get()->create_page( $project, $api_page );

		$post = new self( $api_page, $post->ID );

		return $post;
	}

	/**
	 * @return bool
	 */
	public function save() {

		try {
			$this->storage()->set( self::BRIZY_POST, $this );

			wp_update_post( array(
				'ID'           => $this->get_id(),
				'post_content' => $this->get_compiled_html_body()
			) );


			$project = Brizy_Editor_Project::get();

			Brizy_Editor_User::get()->update_page( $project->get_api_project(), $this->api_page );

		} catch ( Exception $exception ) {
			return false;
		}
	}

	/**
	 * Brizy_Editor_Post constructor.
	 *
	 * @param $api_page
	 * @param $wp_post_id
	 */
	public function __construct( $api_page, $wp_post_id ) {
		$this->api_page   = $api_page;
		$this->wp_post_id = (int) $wp_post_id;
	}

	/**
	 * @return mixed
	 */
	public function get_id() {
		return $this->wp_post_id;
	}

	public function get_compiled_html_body() {
		return $this->compiled_html_body;
	}

	public function set_compiled_html_body( $html ) {
		$this->compiled_html_body = $html;

		return $this;
	}

	public function get_title() {
		return $this->api_page->get_title();
	}

	public function set_title( $title ) {
		$this->api_page->set_title( $title );

		return $this;
	}

	public function set_is_index( $index ) {
		$this->api_page->set_is_index( $index );

		return $this;
	}

	public function is_index( ) {
		return $this->api_page->is_index();
	}

	/**
	 * @return bool
	 */
	public function can_edit() {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * @return $this
	 * @throws Brizy_Editor_Exceptions_AccessDenied
	 */
	public function enable_editor() {
		if ( ! $this->can_edit() ) {
			throw new Brizy_Editor_Exceptions_AccessDenied( 'Current user cannot edit page' );
		}

		$this->storage()->set( Brizy_Editor_Constants::USES_BRIZY, 1 );

		return $this;
	}

	/**
	 * @return $this
	 * @throws Brizy_Editor_Exceptions_AccessDenied
	 */
	public function disable_editor() {
		if ( ! $this->can_edit() ) {
			throw new Brizy_Editor_Exceptions_AccessDenied( 'Current user cannot edit page' );
		}

		$this->storage()->delete( Brizy_Editor_Constants::USES_BRIZY );

		return $this;
	}

	/**
	 * @return Brizy_Editor_Storage_Post
	 */
	public function storage() {

		return Brizy_Editor_Storage_Post::instance( $this->wp_post_id );
	}

	/**
	 * @return array|null|WP_Post
	 */
	public function get_wp_post() {
		return get_post( $this->get_id() );
	}


	/**
	 * @return bool
	 */
	public function uses_editor() {

		try {
			return (bool) $this->storage()->get( Brizy_Editor_Constants::USES_BRIZY );
		} catch ( Exception $exception ) {
			return false;
		}
	}


	/**
	 * @return string
	 */
	public function edit_url() {
		return add_query_arg(
			array( Brizy_Editor_Constants::EDIT_KEY => '' ),
			get_permalink( $this->get_id() )
		);
	}

	/**
	 * @return Brizy_Editor_API_Page
	 */
	public function get_api_page() {
		return $this->api_page;
	}

	/**
	 * @return int
	 */
	public function get_data() {

		return stripslashes( $this->api_page->get_content() );
	}

	/**
	 * @param $data
	 *
	 * @return $this
	 */
	public function set_data( $data ) {

		$this->api_page->set_content( $data );

		return $this;
	}


	public function compile_page() {

		$brizy_editor_page_html = Brizy_Editor_User::get()->compile_page( Brizy_Editor_Project::get(), $this );

		$this->store_head_scripts( $brizy_editor_page_html->get_head_scripts() );
		$this->store_footer_scripts( $brizy_editor_page_html->get_footer_scripts() );
		$this->store_links( $brizy_editor_page_html->get_links_tags() );
		$this->store_inline_styles( $brizy_editor_page_html->get_inline_styles() );

		$this->compiled_html_body = $brizy_editor_page_html->get_body();


		return $this;
	}

	/**
	 * @param $list
	 *
	 * @return $this
	 */
	protected function store_scripts( $key, $list, $in_footer ) {

		$new = array();

		foreach ( $list as $item ) {
			$id    = implode( '-', array( $this->get_id(), basename( $item ) ) );
			$new[] = new Brizy_Editor_Resources_StaticScript( "brizy-$id", $item, array(), null, $in_footer );
		}

		$this->storage()->set( $key, $this->store_static( $new ) );

		return $this;
	}

	/**
	 * @param $list
	 *
	 * @return $this
	 */
	protected function store_head_scripts( $list ) {

		$this->store_scripts( 'head_scripts', $list, false );

		return $this;
	}

	/**
	 * @param $list
	 *
	 * @return $this
	 */
	protected function store_footer_scripts( $list ) {

		$this->store_scripts( 'footer_scripts', $list, true );

		return $this;
	}

	/**
	 * @param $link_tags
	 *
	 * @return $this
	 */
	protected function store_links( $link_tags ) {
		$new = array();


		foreach ( $link_tags as $link_tag ) {
			$uri   = $link_tag->get_attr( 'href' );
			$id    = implode( '-', array( $this->get_id(), basename( $uri ) ) );
			$new[] = new Brizy_Editor_Resources_StaticStyle( "brizy-$id", $uri );
		}

		// remove this when the head_links will be ready.
		$this->storage()->set( 'links', $this->store_static( $new ) );

		$head_links = array();

		foreach ( $link_tags as $i => $link_tag ) {
			$link         = $link_tag->get_attrs();
			$link['href'] = $new[ $i ]->get_url();

			$head_links[] = $link;
		}

		$this->storage()->set( 'head_links', $head_links );

		return $this;
	}

	/**
	 * @param $list
	 *
	 * @return $this
	 */
	protected function store_inline_styles( $list ) {
		$this->storage()->set( 'inline-styles', $list );

		return $this;
	}

	public function get_links_tags() {
		try {

			$links = $this->storage()->get( 'head_links' );

			$link_tags = array();

			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					if ( isset( $link['rel'] ) && $link['rel'] != 'stylesheet' ) {
						$link_tags[] = $link;
					}
				}
			}

			return $link_tags;

		} catch ( Exception $exception ) {
			return array();
		}
	}

	/**
	 * @return Brizy_Editor_Resources_StaticStyle[]
	 */
	public function get_styles() {
		try {

			$links = $this->storage()->get( 'head_links' );

			$static_styles = array();

			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					if ( isset( $link['rel'] ) && $link['rel'] == 'stylesheet' ) {
						$static_styles[] = new Brizy_Editor_Resources_StaticStyle( basename( $link['href'] ), $link['href'] );
					}
				}
			}

			return $static_styles;

		} catch ( Exception $exception ) {
			return array();
		}
	}


	public function get_head_scripts() {
		try {

			$links = $this->storage()->get( 'head_scripts' );

			$script_tags = array();

			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					$script_tags[] = $link;
				}
			}

		} catch ( Exception $exception ) {
			return array();
		}

		return $script_tags;
	}


	public function get_footer_scripts() {
		try {

			$links = $this->storage()->get( 'footer_scripts' );

			$script_tags = array();

			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					$script_tags[] = $link;
				}
			}

		} catch ( Exception $exception ) {
			return array();
		}

		return $script_tags;
	}

//
//	/**
//	 * @return bool
//	 */
//	public function is_draft() {
//		return (bool) $this->api_page->get_published();
//	}
//
//	/**
//	 * @param $published
//	 *
//	 * @return $this
//	 */
//	public function set_is_draft( $published ) {
//		$this->api_page->set_published( (bool) $published );
//
//		return $this;
//	}


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


//	/**
//	 * @return string
//	 */
//	public function get_title() {
//		return get_the_title( $this->get_id() );
//	}
//
//
//	/**
//	 * @param $data
//	 *
//	 * @return $this
//	 * @throws Brizy_Editor_Exceptions_AccessDenied
//	 */
//	public function set_title( $data ) {
//
//		if ( ! $this->can_edit() ) {
//			throw new Brizy_Editor_Exceptions_AccessDenied();
//		}
//
//		wp_update_post( array(
//			'ID'         => $this->get_id(),
//			'post_title' => $data
//		) );
//
//		return $this;
//	}


	/**
	 * @return $this
	 * @throws Brizy_Editor_Exceptions_AccessDenied
	 */
//	public function disable_editor() {
//		if ( ! $this->can_edit() ) {
//			throw new Brizy_Editor_Exceptions_AccessDenied( 'Current user cannot edit page' );
//		}
//
//		$this->storage()->delete( Brizy_Editor_Constants::USES_BRIZY );
//
//		return $this;
//	}

	/**
	 * @return Brizy_Editor_Resources_StaticScript[]
	 */
//	public function get_scripts() {
//		try {
//			return $this->storage()->get( 'scripts' );
//		} catch ( Exception $exception ) {
//			return array();
//		}
//	}

	/**
	 * @return Brizy_Editor_Resources_StaticStyle[]
	 */
//	public function get_styles() {
//		try {
//			return $this
//				->storage()
//				->get( 'styles' );
//		} catch ( Exception $exception ) {
//			return array();
//		}
//	}


	/**
	 * @return array
	 */
	public function get_inline_styles() {
		try {
			return $this
				->storage()
				->get( 'inline-styles' );
		} catch ( Exception $exception ) {
			return array();
		}
	}

	/**
	 * @param Brizy_Editor_Resources_Static[] $list
	 *
	 * @return array
	 */
	public function store_static( $list ) {
		$new = array();

		if ( is_array( $list ) ) {
			foreach ( $list as $item ) {
				try {
					$new[] = Brizy_Editor_Resources_StaticStorage::get( $item )
					                                             ->store()
					                                             ->get_resource();
				} catch ( Exception $exception ) {
					continue;
				}
			}
		}

		return $new;
	}


	/**
	 * @return array
	 */
	public function get_templates() {
		$type = get_post_type( $this->get_id() );
		$list = array(
			array(
				'id'    => '',
				'title' => __( 'Default' )
			)
		);

		return apply_filters( "brizy:$type:templates", $list );
	}

	/**
	 * @return string
	 */
	public function get_template() {
		return get_post_meta( $this->get_id(), '_wp_page_template', true );
	}

	/**
	 * @param string $template
	 *
	 * @return $this
	 */
	public function set_template( $template ) {
		update_post_meta( $this->get_id(), '_wp_page_template', $template );

		return $this;
	}


}
