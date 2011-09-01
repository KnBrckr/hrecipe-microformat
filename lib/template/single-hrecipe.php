<?php
/**
 * The template for displaying a single Recipe.
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

get_header(); ?>

		<div id="container">
			<div id="content" role="main">
				
				<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
								<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
									<h1 class="entry-title"><?php the_title(); ?></h1>
									<div class="entry-meta">
										<?php hrecipe_microformat::posted_on(); ?>
									</div><!-- .entry-meta -->
									<?php hrecipe_microformat::recipe_head(); ?>
									<div class="entry-content">
										<?php the_content(); ?>
									</div><!-- .entry-content -->
									<?php hrecipe_microformat::recipe_footer();?>
									<div class="entry-utility">
										<?php hrecipe_microformat::posted_in(); ?>
										<?php edit_post_link( __( 'Edit', hrecipe_microformat::p), '<span class="edit-link">', '</span>' ); ?>
									</div><!-- .entry-utility -->
								</div><!-- #post-## -->

								<?php comments_template( '', true ); ?>

				<?php endwhile; // end of the loop. ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>