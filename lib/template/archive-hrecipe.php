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

// TODO Format the Recipe Archive - Use recipe summary when available

get_header(); ?>
FIXME Using archive-hrecipe
		<div id="container">
			<section id="content" role="main">
				<?php 
				if ( have_posts() ) {
					// FIXME Add navigation
					?>
					<article>
						<header>
							<h1 class="entry-title">Recipes</h1>
						</header>
						<div class="entry-content">
							<ul class="recipe-list">
								<?php
								while ( have_posts() ) : the_post(); ?>
									<li>
										<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
											<span class="entry-title"><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title(); ?>"><?php the_title(); ?></a></span>
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
						</div><!-- .entry-content -->
					</article>
					<?php
					// FIXME Add navigation
				} ?>
			</section><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
