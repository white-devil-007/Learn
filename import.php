<?php 

    # Check Whether The Page Contains URL Parameter
    # If No, Display The Error Message And Stop The Program
    if(!isset($_GET['url'])) {
        echo "<h1>'url' Parameter Not Found In URL</h1>";
        exit();
    }

    # Functions

    function upload_image($image_url, $my_image_title = '') {
        $image_name       = basename( strtok($image_url, '?') );
        $upload_dir       = wp_upload_dir(); // Set upload folder
        $image_data       = file_get_contents($image_url); // Get image data
        $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
        $filename         = basename( $unique_file_name ); // Create image file name

        // Check folder permission and define file location
        if( wp_mkdir_p( $upload_dir['path'] ) ) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        // Create the image  file on the server
        file_put_contents( $file, $image_data );

        // Check image file type
        $wp_filetype = wp_check_filetype( $filename, null );

        // Set attachment data
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'	 => $my_image_title,		// Set image Title to sanitized title
            'post_excerpt'	 => $my_image_title,		// Set image Caption (Excerpt) to sanitized title
            'post_content'	 => $my_image_title,		// Set image Description (Content) to sanitized title
            'post_status'    => 'inherit'
        );

        // Create the attachment
        $attach_id = wp_insert_attachment( $attachment, $file, 0 );

        // Set the image Alt-Text
        update_post_meta( $attach_id, '_wp_attachment_image_alt', $my_image_title );    

        // Define attachment metadata
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

        // Assign metadata to attachment
        wp_update_attachment_metadata( $attach_id, $attach_data );
        
        return $attach_id;

    }

    # Return Category Id If Exist Or Create Category And Return The Id
    function Createcategory($catag) {
        $check = category_exists($catag);
        if(!$check) {
            $cat_defaults = array(
            'cat_ID' => 0,
            'cat_name' => $catag,
            'taxonomy' => 'category');
            # Updates an existing Category or creates a new Category.
            return wp_insert_category( $cat_defaults );
        } else {
            return $check;
        }
        #$term_id = term_exists($catag, 'category');
        #$term_id = category_exists($catag);
        #return $term_id;
    }

    # Import Simple HTML Dom
    include_once('simple_html_dom.php');
    header('Content-Type: text/html; charset=utf-8');
    
    # To Avoid Some Scraping Issues 
    # Added Ahrefs Crawling Bot
    ini_set('user_agent', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)');

    # Import WordPress
    define('WP_USE_THEMES', false);
    global $wpdb;
    require('wp-load.php');
	require_once('wp-admin/includes/taxonomy.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $html = file_get_html($_GET['url']);

    foreach($html->find('p') as $p) {
        if(strpos($p->innertext, "Also Read") !== false){
            $p->outertext = "";
        }
    }
    
    $article['description'] = $html->find('meta[property="og:description"]')[0]->content;
    $article['img'] = $html->find('meta[property="og:image"]')[0]->content;
    $article['title'] = $html->find('.post .td-post-header .entry-title')[0]->innertext;

    foreach($html->find('[data-index], .quads-location') as $del) {
        $del->outertext = "";
    }

    foreach($html->find('[data-lazy-src]') as $img) {
        $img->src = $img->{'data-lazy-src'};
    }

    foreach($html->find('.td-post-content img') as $imgonly) {
        $attachment_ID = upload_image(str_replace("&amp;","&",$imgonly->src), $imgonly->alt);
        $attachment = wp_get_attachment_image_src( $attachment_ID, 'full' );
        if(!isset($attachment[0])) {
            echo str_replace("&amp;","&",$imgonly->src)."\n";
            continue;
        }
        $images_ID[] = $attachment_ID;
        $imgonly->outertext = '<img loading="lazy" src="'.$attachment[0].'" alt="'.$imgonly->alt.'" width="'.$imgonly->width.'" height="'.$imgonly->height.'">';
    }


    foreach($html->find('.td-post-content [data-wpel-link="internal"]') as $c1) {
        $c1->outertext."\n<br>";
    }

    foreach($html->find('a noscript') as $img) {
        $img->parent ()->outertext = $img->innertext;
    }

    foreach($html->find('noscript img') as $img) {
        $img->parent ()->outertext = $img->innertext;
    }

    foreach($html->find('p, h3') as $empty_element) {
        if(trim($empty_element->innertext) == '') {
            $empty_element->outertext = '';
        }
    }
    
    $username = $html->find('.td-module-meta-info .td-post-author-name a')[0]->innertext;
    $user_id = username_exists( $username );
    if(!$user_id) {
        $user_id = wp_create_user($username, $password = '12345445645646546453', $email = '');
    }

    $noFirst = false;
    $cat = array();
    foreach($html->find('.entry-crumbs span') as $catagory) {
        if($noFirst == false) {
           $noFirst = true;
           continue;
        }
        $cat[] = $catagory->plaintext;
    }
    
    foreach($html->find('.td-post-content [class]') as $removeAttribute){
        $removeAttribute->removeAttribute ('class');
    }
    foreach($html->find('.td-post-content [sizes]') as $removeAttribute){
        $removeAttribute->removeAttribute ('sizes');
    }
    foreach($html->find('.td-post-content [srcset]') as $removeAttribute){
        $removeAttribute->removeAttribute ('srcset');
    }    
    foreach($html->find('.td-post-content [data-lazy-src]') as $removeAttribute){
        $removeAttribute->removeAttribute ('data-lazy-src');
    }    
    foreach($html->find('.td-post-content [data-wpel-link]') as $removeAttribute){
        $removeAttribute->removeAttribute ('data-wpel-link');
    }

    $article['content'] = $html->find('.td-post-content')[0]->innertext;

    # Category
    if(is_array($cat)) {
        $term_id = array();
        foreach($cat as $catag) {
            $term_id[] = Createcategory($catag);
        }
    } else {
        $term_id = Createcategory($catag);
        $term_id = array($term_id);
    }

    $my_post = array(
        'post_title'    => wp_strip_all_tags($article['title']),
        'post_content'  => $article['content'],
        'post_author'   => $user_id,
        'post_category' => $term_id,
        'post_status'   => 'publish'
    );

    if(isset($article['description'])) {
        $my_post['post_excerpt'] = $article['description'];
    }

    $post_id = wp_insert_post( $my_post );

    foreach($images_ID as $change) {
        $post_change = array(
            'ID'            => $change,
            'post_parent'   => $my_post
        );
        wp_insert_post($post_change);
    }


    echo "aaaaaaaaaaaaaaaaaaaaa   -?>   ".$post_id;

    $article['alt'] = $article['title'];
    
    ##############################
    # Add Featured Image to Post #
    ##############################

    // Add Featured Image to Post
    $image_url        = $article['img']; // Define the image URL here
    $image_name       = basename( $image_url );
    $upload_dir       = wp_upload_dir(); // Set upload folder
    $image_data       = file_get_contents($image_url); // Get image data
    $unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
    $filename         = basename( $unique_file_name ); // Create image file name

    // Check folder permission and define file location
    if( wp_mkdir_p( $upload_dir['path'] ) ) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    // Create the image  file on the server
    file_put_contents( $file, $image_data );

    // Check image file type
    $wp_filetype = wp_check_filetype( $filename, null );

    // Sanitize the title:  remove hyphens, underscores & extra spaces:
    $my_image_title = preg_replace( '%\s*[-_\s]+\s*%', ' ',  $article['alt'] );

    // Sanitize the title:  capitalize first letter of every word (other letters lower case):
    $my_image_title = ucwords( strtolower( $my_image_title ) );

    // Set attachment data
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'	=> $my_image_title,		// Set image Title to sanitized title
        'post_excerpt'	=> $my_image_title,		// Set image Caption (Excerpt) to sanitized title
        'post_content'	=> $my_image_title,		// Set image Description (Content) to sanitized title
        'post_status'    => 'inherit'
    );

    // Create the attachment
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );

    // Set the image Alt-Text
    update_post_meta( $attach_id, '_wp_attachment_image_alt', $my_image_title );    
                    
    // Define attachment metadata
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );

    // Assign metadata to attachment
    wp_update_attachment_metadata( $attach_id, $attach_data );

    // And finally assign featured image to post
    set_post_thumbnail( $post_id, $attach_id );
