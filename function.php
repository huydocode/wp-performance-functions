<?php

/////////////////////////////////////////////////
////////HANDLE MILLION POSTS BY HUYDOCODE////////
/////////////////////////////////////////////////

//Tag archive page with 1,5 million posts load by 0.5s without Redis Cache
//Tag archive page with 1,5 million with 100.000 range post ids limit load by 0,001s

### DISABLE CATEGORY! RECOMMEND FOR MILLION POSTS
// Use category will create a huge amount rows in wp_term_relationships
// Use post_tag to split smaller of the range of ids to query faster
// Eg: Category Vehicle will duplicate contents with Tags: Transport, Car, Ship, Truck,...
function alter_taxonomy_for_post() {
  unregister_taxonomy_for_object_type( 'category', 'post' );
}
add_action( 'init', 'alter_taxonomy_for_post' ); 

################ QUERY OPTIMIZE #################
### DISABLE GROUP BY & ORDER BY IN MAIN QUERY
function remove_groupby_and_orderby( $groupby, $query ) {
    if ( $query->is_main_query() && $query->is_archive() ) {
        $groupby = ''; // Remove GROUP BY
    }
    return $groupby;
}
add_filter( 'posts_groupby', 'remove_groupby_and_orderby', 99999, 2 ); //Max Priority 99999
function remove_orderby( $orderby, $query ) {
    if ( $query->is_main_query() && $query->is_archive() ) {
        $orderby = ''; // Remove ORDER BY
    }
    return $orderby;
}
add_filter( 'posts_orderby', 'remove_orderby', 99999, 2 );

### DISABLE META CACHE & TERM CACHE
if (! function_exists('wpartisan_set_no_found_rows')) :
    function wpartisan_set_no_found_rows(\WP_Query $wp_query)
    {	if (is_archive()) {
			$wp_query->set('no_found_rows', true); 
      //Will disable default pagination, and rebuild it in archive.php
      //Reduce query time about 0,3s
		}
		$wp_query->set('update_post_meta_cache', false); //Improve Query Speed
		$wp_query->set('update_post_term_cache', false); //Improve Query Speed
		$wp_query->set('cache_results', false); //Improve Query Speed
		$wp_query->set('post_status', 'publish'); //Improve Query Speed
    }
endif;
add_filter('pre_get_posts', 'wpartisan_set_no_found_rows', 9999, 1);

### METHOD[1] OPTIMIZE MAIN QUERY & SQL POST_WHERE & RANGE IDS LIMIT
function alm_first_letter_query($where, $query) {	
    if( $query->is_main_query() ) { // && ! $query->is_admin ) {
		global $wp_query, $wpdb;
		$max_ID = 2542143; //Fastest
    //$wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts WHERE post_status = 'publish'"); //Slower
		$min_ID_10000 = $max_ID-10000;//Eg: For /{archive} page query
		$min_ID_1000 = $max_ID-1000; //Eg: For /lastest page query
		$min_ID_100 = $max_ID-100; //Eg: For /home page query
		if (is_archive()) {
			$tax = $wp_query->get_queried_object();
			$tax_count = get_queried_object()->count; //Count publish post
			if ( $tax_count > 100000 ) {
				$where .= ' AND ((wp_posts.ID BETWEEN 34 AND 20000) OR (wp_posts.ID BETWEEN 1006576 AND 1100000) OR (wp_posts.ID BETWEEN 2000000 AND 2100000) OR (wp_posts.ID BETWEEN '.$min_ID_1000.' AND '.$max_ID.'))';
        //Limit scan rows in wp_term_relationships, show latest posts by range ids
			}
			if ( $tax_count < 100000 && $tax_count > 10000 ) {
				$where .= ' AND ((wp_posts.ID BETWEEN 1 AND 20000) OR (wp_posts.ID BETWEEN 1006576 AND 1200000) OR (wp_posts.ID BETWEEN '.$min_ID_10000.' AND '.$max_ID.'))';
        //Limit scan rows in wp_term_relationships, show latest posts by range ids
			}
			if ( $tax_count < 14000 ) {
				//$where .= ' AND wp_posts.ID BETWEEN 1 AND 1000000'; 
        //Can handle by scanning all (million) rows
			}
		}
		if (is_home()) {
			$where .= ' AND wp_posts.ID BETWEEN '.$min_ID_100.' AND '.$max_ID.'';
      //Limit recommend!
		}
		if (is_admin()) {
			//$where .= ' AND wp_posts.ID BETWEEN '.$min_ID.' AND '.$max_ID.'';
      //Limit how many posts show in WP Admin Dashboard that need improve!
		}
	}
	return $where;	
}
add_filter('posts_where', 'alm_first_letter_query', 9999, 2);

### METHOD[1] ADD DEFAULT ORDER BY TO QUERY
if (! function_exists('wpartisan_fix')) :
    function wpartisan_fix($clauses, \WP_Query $wp_query)
    {
      if ($wp_query->is_singular()) {
          return $clauses;
      }
  		if (is_archive()) { //NOT WORK ON PAGE-TEMPLATE
  			$where = isset($clauses[ 'where' ]) ? $clauses[ 'where' ] : '';
  			$join = isset($clauses[ 'join' ]) ? $clauses[ 'join' ] : '';
  			$distinct = isset($clauses[ 'distinct' ]) ? $clauses[ 'distinct' ] : '';
  			if ($clauses['orderby'] == 'wp_posts.post_date DESC') {
  				$clauses['orderby'] = " wp_posts.ID ASC"; //Fastest
  			}
  			
  		}
  		return $clauses;
    }
endif;
add_filter('posts_clauses', 'wpartisan_fix', 9999, 2);

### METHOD[2] ARTISAN: SLOWER
if (! function_exists('wpartisan_set_found_posts')) :
    function wpartisan_set_found_posts($clauses, \WP_Query $wp_query)
    {
        if ($wp_query->is_singular()) {
            return $clauses;
        }
		if (is_archive()) { //NOT WORK ON PAGE-TEMPLATE
  		global $wp_query, $wpdb;
  		$tag = get_query_var('tag'); 
  		$tax = $wp_query->get_queried_object();
  		$tax_count = get_queried_object()->count;

      $where = isset($clauses[ 'where' ]) ? $clauses[ 'where' ] : '';
      $join = isset($clauses[ 'join' ]) ? $clauses[ 'join' ] : '';
      $distinct = isset($clauses[ 'distinct' ]) ? $clauses[ 'distinct' ] : '';
      $wp_query->found_posts = $tax_count;

      if ($clauses['orderby'] == 'wp_posts.post_date DESC') {
          $clauses['orderby'] = " wp_posts.ID DESC";
      }
                       
  		$posts_per_page = 60;
  		$page_count = ceil($tax_count / $posts_per_page);
  		if ($page_count > 167) { $page_count = 167; }
  		$wp_query->max_num_pages = $page_count;
          // Return the $clauses so the main query can run.
          return $clauses;
  		}
    }
endif;
//add_filter('posts_clauses', 'wpartisan_set_found_posts', 9999, 2); //disable if choose METHOD[1]

######## ADMIN DASHBOARD FOR MILLION POST ########
### SHOW HIDDEN CUSTOM FIELDS
add_filter( 'is_protected_meta', '__return_false' ); 

### ADMIN DISABLE MONTHS DROPDOWN
add_filter( 'disable_months_dropdown', '__return_true' );
//@https://wordpress.stackexchange.com/questions/187612/admin-very-slow-edit-page-caused-by-core-meta-query
function set_postmeta_choice( $string, $post ) {
    $meta_keys = array();
    foreach(has_meta( $post->ID ) as $meta){
        $meta_keys[] = $meta["meta_key"];
    }
    return $meta_keys;
}
add_filter( 'postmeta_form_keys', 'set_postmeta_choice', 10, 3 );

### ADMIN CACHE MONTHY MEDIA
//@https://derrick.blog/2018/06/25/query-caching-and-a-little-extra/
function wpcom_vip_media_library_months_with_files() {
	$months = wp_cache_get( 'media_library_months_with_files', 'extra-caching' );
	if ( false === $months ) {
		global $wpdb;
		$months = $wpdb->get_results( $wpdb->prepare( "
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM $wpdb->posts
			WHERE post_type = %s
			ORDER BY post_date DESC
			", 'attachment' )
		);
		wp_cache_set( 'media_library_months_with_files', $months, 'extra-caching' );
	}
	return $months;
}
add_filter( 'media_library_months_with_files', 'wpcom_vip_media_library_months_with_files' );
##################### YOAST #######################
// How to create a Sitemap for Million: use Python!
