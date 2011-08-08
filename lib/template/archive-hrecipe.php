<?php
/**
 * The template for displaying archive of Recipes.
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 **/

// FIXME Format the Recipe Archive - Use recipe summary when available

get_header(); ?>

		<div id="container">
			<div id="content" role="main">
				
				Verily!  A gaggle of recipes!

				<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

								<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
									<?php if ( is_front_page() ) { ?>
										<h2 class="entry-title"><?php the_title(); ?></h2>
									<?php } else { ?>
										<h1 class="entry-title"><?php the_title(); ?></h1>
									<?php } ?>

									<div class="entry-content">
										<?php the_content(); ?>
										<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) ); ?>
										<?php edit_post_link( __( 'Edit', 'twentyten' ), '<span class="edit-link">', '</span>' ); ?>
									</div><!-- .entry-content -->
								</div><!-- #post-## -->

								<?php comments_template( '', true ); ?>

				<?php endwhile; // end of the loop. ?>
			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
