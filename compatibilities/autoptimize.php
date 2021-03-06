<?php

class Brizy_Compatibilities_Autoptimize {

	public function __construct() {
		add_action( 'wp', array( $this, 'disable_js_optimize' ), 11 );
	}

	/**
	 * Enable noptimize param in the autoptimize plugin class autoptimizeScripts.
	 * It is used to perfom some actions above js scripts like: minify, concatenate ...
	 * When we are in post build mode with brizy we disable it.
	 */
	public function disable_js_optimize() {
		$pid = Brizy_Editor::get()->currentPostId();

		if ( ! $pid ) {
			return;
		}

		$post = Brizy_Editor_Post::get( $pid );

		if ( $post && $post->uses_editor() && isset( $_GET[ Brizy_Editor_Constants::EDIT_KEY_IFRAME ] ) ) {
			add_filter( 'autoptimize_filter_js_noptimize', '__return_true' );
		}
	}
}
