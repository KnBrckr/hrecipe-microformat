<?php
/**
 * Display recipe archive
 */

// Get info on taxonomy query that got us here
$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );

get_header(); ?>
FIXME taxonomy-hrecipe_category
	<section id="content" role="main">
		<?php 
		if (have_posts()) {
			?>
			<nav class="nav-content nav-content-top">
				<span class="float-left"><?php previous_posts_link('&laquo; Previous Entries') ?></span>
				<span class="float-right"><?php next_posts_link('Next Entries &raquo;') ?></span>
			</nav>

			<article>
				<header>
					<h1 class="entry-title">
						<?php echo ('' != $term->name) ? $term->name : "Recipes" ?>
					</h1>
				</header>
				<?php if ('' != $term->description) {} ?>
					<div class="entry-content">
						<ul class="hrecipe-list">
							<?php
							while ( have_posts() ) : the_post(); ?>
								<li>
									<div id="post-<?php the_ID(); ?>"  class="hrecipe-list-entry">
										<span class="entry-title"><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php echo strip_tags(the_title('','',false)); ?>"><?php the_title(); ?></a></span>
										<?php
									 	/* FIXME enable reporting of ratings echo $hrecipe_microformat->recipe_rating_html(); */ 
										edit_post_link('Edit', '', '');
										?>
									</div>
								</li><!-- #post-## -->
								<?php
							endwhile; // end of the loop.
							?>
						</ul>						
					</div>
			</article>

			<nav class="nav-content nav-content-bottom">
				<span class="float-left"><?php previous_posts_link('&laquo; Previous Entries') ?></span>
				<span class="float-right"><?php next_posts_link('Next Entries &raquo;') ?></span>
			</nav>					
		  <?php
		}
		?>
	</section><!-- #content -->

<?php 
get_sidebar();
get_footer(); 
?>