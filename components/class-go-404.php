<?php
/**
 * @author Gigaom <support@gigaom.com>
 */
class GO_404
{
	private $postid_cache_group = 'go-404-url-to-postid';
	private $postslug_cache_group = 'go-404-slug-to-postid';

	/**
	 * constructor
	 */
	public function __construct()
	{
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 11 );
	}//END __construct

	/**
	 * Intercept 404 requests and see if we can resolve it by removing "junk"
	 * after the last slash (/).
	 */
	public function template_redirect()
	{
		if ( ! is_404() )
		{
			return;
		}

		$url = $this->clean_and_validate_url( $_SERVER['REQUEST_URI'] );

		if ( ! empty( $url ) )
		{
			wp_redirect( esc_url_raw( $url ) );
			exit;
		}

		$url = $this->get_url_by_slug();

		if ( ! empty( $url ) )
		{
			wp_redirect( esc_url_raw( $url ) );
			exit;
		}
	}//END template_redirect

	/**
	 * attempt to clean up and validate $url.
	 *
	 * @param string $url an url to be cleaned up and validated
	 * @return a cleaned and validated url, or NULL if $url cannot be cleaned
	 *  or validated
	 */
	public function clean_and_validate_url( $url )
	{
		$url = preg_replace( '!/[^/]*?$!', '', $url );

		if ( empty( $url ) )
		{
			return NULL;
		}

		$post_id = wp_cache_get( $url, $this->postid_cache_group );
		if ( FALSE === $post_id )
		{
			$post_id = url_to_postid( $url );
			wp_cache_set( $url, $post_id, $this->postid_cache_group );
		}//end if

		if ( 0 == $post_id )
		{
			return NULL;
		}

		return get_permalink( $post_id );
	}//END clean_and_validate_url

	/**
	 * attemp to find a good url by using the post slug (from wp_query) if any
	 *
	 * @return mixed permalink to the queried post, or NULL if we cannot
	 *  find one.
	 */
	public function get_url_by_slug()
	{
		global $wp_query;

		if ( empty( $wp_query->query['name'] ) )
		{
			return NULL;
		}

		$post_slug = $wp_query->query['name'];

		$post_id = wp_cache_get( $post_slug, $this->postslug_cache_group );

		if ( ( FALSE !== $post_id ) && empty( $post_id ) )
		{
			// $post_slug is mapped to an empty result (slug not found)
			return NULL;
		}
		elseif ( FALSE === $post_id )
		{
			// if we have a post slug, try looking up the post by slug
			$posts = get_posts(
				array(
					'name' => $post_slug,
					'post_status' => 'publish',
					'post_type' => ( ! empty( $wp_query->query['post_type'] ) ? $wp_query->query['post_type'] : 'post' ),
				)
			);

			if ( empty( $posts ) )
			{
				// store the negative result too so we won't rerun the same query again next time
				wp_cache_set( $post_slug, '', $this->postslug_cache_group );
				return NULL;
			}

			$post_id = $posts[0]->ID;

			wp_cache_set( $post_slug, $post_id, $this->postslug_cache_group );
		}//END elseif

		return get_permalink( $post_id );
	}//END get_url_by_slug
}//END class

/**
 * The singleton
 */
function go_404()
{
	global $go_404;

	if ( ! isset( $go_404 ) )
	{
		$go_404 = new GO_404();
	}//END if

	return $go_404;
}//END go_404
