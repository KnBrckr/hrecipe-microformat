<?php
/**
 * The Template for displaying all single recipes
 */

get_header(); ?>

		<div id="container">
			<div id="content" role="main">
				
				<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

								<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
									<h1 class="entry-title"><?php the_title(); ?></h1>

									<div class="entry-meta">
										<?php /* FIXME */ twentyten_posted_on(); ?>
									</div><!-- .entry-meta -->

									<div class="entry-content">
										<?php the_content(); ?>
									</div><!-- .entry-content -->

									<div class="entry-utility">
										<?php /* FIXME */ twentyten_posted_in(); ?>
										<?php edit_post_link( __( 'Edit'), '<span class="edit-link">', '</span>' ); ?>
									</div><!-- .entry-utility -->
								</div><!-- #post-## -->

								<?php comments_template( '', true ); ?>

				<?php endwhile; // end of the loop. ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>