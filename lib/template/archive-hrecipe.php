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

get_header(); ?>
		<div id="container">
			<section id="content" role="main">
				<?php 
				if ( have_posts() ) {
					?>
					<nav class="nav-content nav-content-top">
						<span class="float-left"><?php previous_posts_link('&laquo; Previous Entries') ?></span>
						<span class="float-right"><?php next_posts_link('Next Entries &raquo;') ?></span>
					</nav>
					
					<article>
						<header>
							<h1 class="entry-title">Recipes</h1>
						</header>
						<div class="entry-content">
							<ul class="hrecipe-list">
								<?php
								while ( have_posts() ) : the_post(); ?>
									<li>
										<div id="post-<?php the_ID(); ?>" class="hrecipe-list-entry">
											<span class="entry-title"><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php echo strip_tags(the_title('','',false)); ?>"><?php the_title(); ?></a></span>
											<?php
										 	/* TODO enable reporting of ratings echo $hrecipe_microformat->recipe_rating_html(); */ 
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

					<nav class="nav-content nav-content-bottom">
						<span class="float-left"><?php previous_posts_link('&laquo; Previous Entries') ?></span>
						<span class="float-right"><?php next_posts_link('Next Entries &raquo;') ?></span>
					</nav>					
					<?php
				} ?>
			</section><!-- #content -->
		</div><!-- #container -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
