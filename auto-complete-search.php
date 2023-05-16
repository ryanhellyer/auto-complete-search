<?php
/* 
Plugin Name: Auto Complete Search
Plugin URI: https://geek.hellyer.kiwi/plugins/auto-complete-search/
Description: ...
Version: 1.0
Author: Ryan Hellyer
Author URI: https://geek.hellyer.kiwi/
Text Domain: auto-complete-search
License: GPL2

XXX

------------------------------------------------------------------------
Copyright Ryan Hellyer

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

*/

add_action( 'wp_head', 'css' );
function css() {
	echo '
	<style>
	.autocomplete-suggestions {
		text-align: left; cursor: default; border: 1px solid #ccc; border-top: 0; background: #fff; box-shadow: -1px 1px 3px rgba(0,0,0,.1);
		position: absolute; display: none; z-index: 9999; max-height: 254px; overflow: hidden; overflow-y: auto; box-sizing: border-box;
	}
	.autocomplete-suggestion { position: relative; padding: 0 .6em; line-height: 23px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 1.02em; color: #333; }
	.autocomplete-suggestion b { font-weight: normal; color: #1f8dd6; }
	.autocomplete-suggestion.selected { background: #f0f0f0; }
	.error {
		margin-left: 20px;
		padding: 5px 20px;
		float: left;
		border: 1px solid rgba(255,0,0,0.4);
		border-radius: 10px;
		background: rgba(255,0,0,0.1);
	}

	</style>
	';
}

add_action( 'rest_api_init', 'register_route' );
function register_route() {
	register_rest_route( 'auto-complete-search/v1', '/search', array(
		'methods'  => 'GET',
		'callback' => 'search',
	) );

}

function search( $request ) {
	$request_params = $request->get_query_params();

	$search_term = esc_html( $request_params['s'] );

	add_filter( 'posts_where', 'title_filter', 10, 2 );

	$query = new WP_Query(
		array(
			'search_title'            => $search_term, // Uses custom searh filter
			'post_type'               => 'any',
			'no_found_rows'           => true, // improves performance
			'update_post_meta_cache'  => false, // improves performance
			'update_post_term_cache'  => false, // improves performance
			'fields'                  => 'ids', // improves performance
			'posts_per_page'          => 8, // We only need a few results
		)
	);

	$results = array();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$results[] = get_the_title();
		}
	}

	remove_filter( 'posts_where', 'title_filter' );

	return $results;
}


add_action( 'wp_footer', 'scripts' );
function scripts() {

	echo '

	<script src="http://dev-hellyer.kiwi/unique-headers/wp-content/plugins/auto-complete-search/js/auto-complete.js"></script>
	<script>
	var search_input = document.getElementsByName("s")[0];
	search_input.id = "someID";
	console.log(search_input);

	new autoComplete({
		selector: "#someID",
		minChars: 1,
		source: function(term, suggest){
			term = term.toLowerCase();

			// Do AJAX request for the results
			var xhttp = new XMLHttpRequest();
			xhttp.onreadystatechange = function() {

				if (this.readyState == 4 && this.status == 200) {

					// Take suggestions and serve to user
					var choices = JSON.parse( this.responseText );

					var suggestions = [];
					for (i=0;i<choices.length;i++)
						if (~choices[i].toLowerCase().indexOf(term)) suggestions.push(choices[i]);
					suggest(suggestions);

				}

			};
			xhttp.open("GET", "http://dev-hellyer.kiwi/unique-headers/wp-json/auto-complete-search/v1/search/?s=" + term, true);
			xhttp.send();

		}
	});
	</script>
	';
}



/**
 * Filter for searching only the post-title.
 */
function title_filter( $where, $wp_query ) {
	global $wpdb;
	if ( $search_term = $wp_query->get( 'search_title' ) ) {
		$where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( like_escape( $search_term ) ) . '%\'';
	}
	return $where;
}
