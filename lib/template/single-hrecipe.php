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

global $hrecipe_microformat;

get_header(); ?>

		<div id="container">
			<div id="content" role="main">
				
				<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
								<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
									<header class="entry-header">
										<hgroup>
											<h1 class="entry-title"><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php echo strip_tags(the_title('','',false)); ?>"><?php the_title(); ?></a></h1>
											<h2 class="entry-posted-on"><?php $hrecipe_microformat->posted_on(); ?></h2>
										</hgroup>
									</header>
									<div class="entry-content">
										<?php the_content(); ?>
									</div><!-- .entry-content -->
									<footer class="entry-footer">
										<?php $hrecipe_microformat->posted_in(); ?>
										<?php edit_post_link('Edit', '<nav class="nav-content">', '</nav>'); ?>
									</footer>
								</article><!-- #post-## -->

								<?php comments_template( '', true ); ?>

				<?php endwhile; // end of the loop. ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>