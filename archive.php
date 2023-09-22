<?php get_header();

  /////////////////////////////////////////////////
  ////////HANDLE MILLION POSTS BY HUYDOCODE////////
  /////////////////////////////////////////////////

  //Reduce load time for post_tag have 1.5 million posts to 0.5s without Redis Cache
	$tag = get_query_var('tag'); //slug, force tag
	$tax = $wp_query->get_queried_object();
	$tax_count = get_queried_object()->count; //default or custom by count($args1);

	$tax_slug = $tax->slug;
	$tax_title = $tax->name;
	
  #### QUERY METHOD[1]
	global $wp_query, $wpdb, $paged;
	$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ): 1;

	$sql_0 = "SELECT term_id FROM wp_terms WHERE 1=1 AND slug IN ( '".$tag."' ) GROUP BY term_id";
	$sql_0 = $wpdb->get_results($sql_0, ARRAY_A);
	$sql_0 = wp_list_pluck( $sql_0, 'term_id' );
	$sql_0 = implode( ',', $sql_0 ); //array

  $max_ID = 2542143; //Fastest
  //$max_ID = $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts WHERE post_status = 'publish'"); //Slower
  $min_ID_10000 = $max_ID-10000;
  $min_ID_1000 = $max_ID-1000;

  //LIMIT 10.000 post from $sql_0 array
  //Match "AND ((wp_posts.ID BETWEEN 34 AND 20000)..." same with vars on function.php
	if ( $tax_count > 100000 ) {
  	$sql_post = 'SELECT wp_posts.ID FROM wp_posts
  		LEFT JOIN wp_term_relationships ON (wp_posts.ID = wp_term_relationships.object_id)
  		WHERE 1=1 AND
  		wp_term_relationships.term_taxonomy_id IN ('.$sql_0.')
  		AND wp_posts.post_type = "post"
  		AND wp_posts.post_status = "publish"
      AND ((wp_posts.ID BETWEEN 34 AND 20000) OR (wp_posts.ID BETWEEN 1006576 AND 1100000) OR (wp_posts.ID BETWEEN 2000000 AND 2100000) OR (wp_posts.ID BETWEEN '.$min_ID_1000.' AND '.$max_ID.'))
  		GROUP BY wp_posts.ID
  		ORDER BY wp_posts.ID ASC
      LIMIT 0, 10000';
	}
	if ( $tax_count < 100000 && $tax_count > 10000 ) {
  	$sql_post = 'SELECT wp_posts.ID FROM wp_posts
  		LEFT JOIN wp_term_relationships ON (wp_posts.ID = wp_term_relationships.object_id)
  		WHERE 1=1 AND
  		wp_term_relationships.term_taxonomy_id IN ('.$sql_0.')
  		AND wp_posts.post_type = "post"
  		AND wp_posts.post_status = "publish"
  		AND ((wp_posts.ID BETWEEN 1 AND 20000) OR (wp_posts.ID BETWEEN 1006576 AND 1200000) OR (wp_posts.ID BETWEEN '.$min_ID_10000.' AND '.$max_ID.'))
  		GROUP BY wp_posts.ID
  		ORDER BY wp_posts.ID ASC
  		LIMIT 0, 10000';
	} 
	if ( $tax_count < 14000 ) {
  	$sql_post = 'SELECT wp_posts.ID FROM wp_posts
  		LEFT JOIN wp_term_relationships ON (wp_posts.ID = wp_term_relationships.object_id)
  		WHERE 1=1 AND
  		wp_term_relationships.term_taxonomy_id IN ('.$sql_0.')
  		AND wp_posts.post_type = "post"
  		AND wp_posts.post_status = "publish"
  		GROUP BY wp_posts.ID
  		ORDER BY wp_posts.ID ASC
  		LIMIT 0, 10000';
	}

	// SET LIMIT CALL POSTS
	$results = $wpdb->get_results($sql_post, ARRAY_A);
	$post_ids = wp_list_pluck( $results, 'ID' ); //Array
	$paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
	$display_count = get_option( 'posts_per_page' ); //60 @Reading Setting WP Admin Dashboard

	$args = array(
		'post_type' => 'post',
		'post_status' =>'publish',
		'ignore_sticky_posts' => true,
		'paged' => $paged,
		'posts_per_page'=> $display_count,
		'post__in' => $post_ids, //BUG: Tag 0 post, show latest posts
    
		//'meta_key' => 'sticky', // Orderby Post Meta Option
		//'meta_type' => 'NUMERIC', // Orderby Post Meta Option
		//'orderby' => array('meta_value_num' => 'ASC', 'ID' => 'ASC'), //Orderby Post Meta Option
    
		'orderby' => 'ID', //FASTEST
		'order' => 'ASC', //FASTEST
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'cache_results'          => false,
	);

	// FIX PAGINATION (BECAUSE PAGINATION DISABLE by function.php METHOD[1])
	$page_count = ceil(count($post_ids) / $posts_per_page);
	if ($page_count > 167) { $page_count = 167; } //Set Maximum paged: 10000/60=167
	$wp_query->max_num_pages = $page_count;

  //QUERY
	$query = new WP_Query( $args );
?>
<header class='page-header'>
<!-- YOUR TEMPLATE HERE -->
</header>
<div class= "wrapper section-gap">
	<div id="primary" class="content-area">
    <main id="main">
    <!-- YOUR TEMPLATE HERE -->
    <?php
      if ( $query->have_posts() ) :
        while ( $query->have_posts() ) : $query->the_post();
          get_template_part( 'template-parts/content', get_post_format() ); //LOOP CONTENT SET HERE
        endwhile;
        ###PAGINATION FIX
        $pagination_args = array(
          'prev_text'          => __( 'Previous page', 'theme-domain' ),
          'next_text'          => __( 'Next page', 'theme-domain' ),
          'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'theme-domain' ) . ' </span>'
        );
        //$GLOBALS['wp_query'] = $query; ### METHOD[1], NEED TEST!
        the_posts_pagination( $pagination_args ); //Default WP Pagination
        //if(function_exists('wp_pagenavi')) { wp_pagenavi( array( 'query' => $query ) ); } //PageNavi Option
        ###END PAGINATION FIX
      endif; 
      wp_reset_postdata();
      wp_reset_query(); 
    ?>
    <!-- YOUR TEMPLATE HERE -->
		</main><!-- #main -->
	</div><!-- #primary -->
	</div>
<?php
get_sidebar();
get_footer();
