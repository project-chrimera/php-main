<?php
// EXAMPLE INDEX.php
require(__DIR__ . '/wordpress/wp-load.php');
get_header();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Yet Another Project to Save the World</title>
  
  <!-- Open Graph tags -->
  <meta property="og:title" content="Yet Another Project to Save the World" />
  <meta property="og:description" content="We Act so others can see when they dont listen" />
  <meta property="og:image" content="https://www.yetanotherprojecttosavetheworld.org/images/quote.png" />
  <meta property="og:url" content="https://www.yetanotherprojecttosavetheworld.org/" />
  <meta property="og:type" content="website" />

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Yet Another Project to Save the World" />
  <meta name="twitter:description" content="We Act so others can see when they dont listen"" />
  <meta name="twitter:image" content="https://www.yetanotherprojecttosavetheworld.org/images/quote.png" />
</head>
<?php
/** FEATURED: Post/Page ID 287 */
$featured = get_post(287);
if ($featured instanceof WP_Post) {
    echo '<section style="max-width:1100px;margin:0 auto 32px auto;">';
        echo '<h1 style="margin:0 0 12px 0;">' . esc_html(get_the_title($featured)) . '</h1>';
        echo apply_filters('the_content', $featured->post_content);
    echo '</section>';
} else {
    echo '<p style="color:#a00;">Post 287 not found.</p>';
}

/** GRID: Last 5 blog posts (excluding 287 to avoid duplicate if it’s a post) */
$query = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => 4,
    'post__not_in'   => [287],
    'no_found_rows'  => true,
]);

if ($query->have_posts()) {
    echo '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:20px;">';
    while ($query->have_posts()) {
        $query->the_post();
        $plink = esc_url(get_permalink());
        echo '<a href="' . $plink . '" style="text-decoration:none; color:inherit;">';
            echo '<div style="border:1px solid #e5e7eb; padding:14px; box-shadow:0 2px 6px rgba(0,0,0,0.06); border-radius:12px; height:100%;">';
                if (has_post_thumbnail()) {
                    the_post_thumbnail('medium', ['style' => 'width:100%;height:auto;border-radius:8px;margin-bottom:10px;']);
                }
                echo '<h3 style="margin:0 0 8px 0;">' . esc_html(get_the_title()) . '</h3>';
                echo '<p style="margin:0;">' . esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()), 20, '…')) . '</p>';
            echo '</div>';
        echo '</a>';
    }
    echo '</div>';
    wp_reset_postdata();
} else {
    echo '<p>No recent posts found.</p>';
}

?>
<div style="text-align:center; margin-top:2em;">
    <h2 style="margin-bottom:1em; font-size:1.8em; font-weight:bold; color:#333;">
        Quote of the Day
    </h2>

    <div style="font-style:italic; margin-bottom:1em;">
        <?php echo nl2br(file_get_contents("/home/yap2stw/llm/quote.txt")); ?>
    </div>

    <img src="/images/quote.png" alt="Quote" style="max-width:90%; height:auto;" />
</div>
<?php
get_footer();
?>
