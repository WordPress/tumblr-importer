<?php
if ( class_exists( 'WP_Importer_Cron' ) ) {
	/**
	 * Tumblr Importer
	 *
	 * @package WordPress
	 * @subpackage Importer
	 */
	class Tumblr_Import extends WP_Importer_Cron {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'tumblr_importer_metadata', array( $this, 'tumblr_importer_metadata' ) );
			add_filter( 'tumblr_importer_format_post', array( $this, 'filter_format_post' ) );
			add_filter( 'tumblr_importer_get_consumer_key', array( $this, 'get_consumer_key' ) );
			add_filter( 'wp_insert_post_empty_content', array( $this, 'filter_allow_empty_content' ), 10, 2 );
			parent::__construct();
		}

		/**
		 * Figures out what to do, then does it.
		 *
		 * @return void
		 */
		public function start() {
			if ( isset( $_POST['restart'] ) ) {
				check_admin_referer( 'tumblr-import' );
				$this->restart();
			}

			if ( ! isset( $this->error ) ) {
				$this->error = null;
			}

			@$this->consumerkey = defined( 'TUMBLR_CONSUMER_KEY' ) ? TUMBLR_CONSUMER_KEY : ( ! empty( $_POST['consumerkey'] ) ? $_POST['consumerkey'] : $this->consumerkey );
			@$this->secretkey   = defined( 'TUMBLR_SECRET_KEY' ) ? TUMBLR_SECRET_KEY : ( ! empty( $_POST['secretkey'] ) ? $_POST['secretkey'] : $this->secretkey );

			// if we have access tokens, verify that they work
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			if ( ! empty( $this->access_tokens ) ) {
				// TODO
			} elseif ( isset( $_GET['oauth_verifier'] ) ) {
				$this->check_permissions();
			} elseif ( ! empty( $this->consumerkey ) && ! empty( $this->secretkey ) ) {
				$this->check_credentials();
			}
			if ( isset( $_POST['blogurl'] ) ) {
				$this->start_blog_import();
			}
			if ( isset( $this->blogs ) ) {
				$this->show_blogs( $this->error );
			} else {
				$this->greet( $this->error );
			}

			unset( $this->error );

			$saved = false;

			if ( ! isset( $_POST['restart'] ) ) {
				$saved = $this->save_vars();
			}

			if ( $saved && ! isset( $_GET['noheader'] ) ) {
				?>
			<p><?php esc_html_e( 'We have saved some information about your Tumblr account in your WordPress database. Clearing this information will allow you to start over. Restarting will not affect any posts you have already imported. If you attempt to re-import a blog, duplicate posts will be skipped.', 'tumblr-importer' ); ?></p>
			<p><?php esc_html_e( 'Note: This will stop any import currently in progress.', 'tumblr-importer' ); ?></p>
			<form method='post' action='?import=tumblr&amp;noheader=true'>
				<?php wp_nonce_field( 'tumblr-import' ); ?>
			<p class='submit' style='text-align:left;'>
			<input type='submit' class='button' value='<?php esc_attr_e( 'Clear account information', 'tumblr-importer' ); ?>' name='restart' />
			</p>
			</form>
				<?php
			}
		}

		/**
		 * Displays the greeting screen.
		 *
		 * @param string $error Optional error message.
		 *
		 * @return void
		 */
		public function greet( $error = null ) {
			if ( ! empty( $error ) ) {
				echo "<div class='error'><p>" . esc_html( $error ) . '</p></div>';
			}
			?>

		<div class='wrap'>
			<?php
			if ( version_compare( get_bloginfo( 'version' ), '3.8.0', '<' ) ) {
				// phpcs:ignore WordPress.WP.DeprecatedFunctions
				screen_icon(); // Behind a version check.
			}
			?>
		<h2><?php esc_html_e( 'Import Tumblr', 'tumblr-importer' ); ?></h2>
			<?php if ( empty( $this->request_tokens ) ) { ?>
		<p><?php esc_html_e( 'Howdy! This importer allows you to import posts from your Tumblr account into your WordPress site.', 'tumblr-importer' ); ?></p>
		<p><?php esc_html_e( "First, you will need to create an 'app' on Tumblr. The app provides a connection point between your blog and Tumblr's servers.", 'tumblr-importer' ); ?></p>

		<p><?php esc_html_e( 'To create an app, visit this page:', 'tumblr-importer' ); ?> <a href="https://www.tumblr.com/oauth/apps">https://www.tumblr.com/oauth/apps</a></p>
		<ol>
		<li><?php esc_html_e( 'Click the large green "Register Application" button.', 'tumblr-importer' ); ?></li>
		<li><?php esc_html_e( 'You need to fill in the "Application Name", "Application Website", and "Default Callback URL" fields. All the rest can be left blank.', 'tumblr-importer' ); ?></li>
		<li>
				<?php
				esc_html_e( 'For the "Application Website" and "Default Callback URL" fields, please put in this URL: ', 'tumblr-importer' );
				echo '<strong>' . esc_url( home_url() ) . '</strong>';
				?>
		</li>
		<li><?php echo wp_kses( __( 'Note: It is important that you put in that URL <em>exactly as given</em>.', 'tumblr-importer' ), array( 'em' => array() ) ); ?></li>
		</ol>

		<p><?php esc_html_e( 'After creating the application, copy and paste the "OAuth Consumer Key" and "Secret Key" into the given fields below.', 'tumblr-importer' ); ?></p>

		<form action='?import=tumblr' method='post'>
				<?php wp_nonce_field( 'tumblr-import' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for='consumerkey'><?php esc_html_e( 'OAuth Consumer Key:', 'tumblr-importer' ); ?></label></th>
					<td>
						<input type='text' class="regular-text" name='consumerkey' value='<?php echo isset( $this->consumerkey ) ? esc_attr( $this->consumerkey ) : ''; ?>' />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for='secretkey'><?php esc_html_e( 'Secret Key:', 'tumblr-importer' ); ?></label></th>
					<td>
						<input type='text' class="regular-text" name='secretkey' value='<?php echo isset( $this->secretkey ) ? esc_attr( $this->secretkey ) : ''; ?>' />
					</td>
				</tr>
			</table>
			<p class='submit'>
				<input type='submit' class='button' value="<?php esc_attr_e( 'Connect to Tumblr', 'tumblr-importer' ); ?>" />
			</p>
		</form>
		</div>
				<?php
			} else {
				?>
			<p><?php esc_html_e( 'Everything seems to be in order, so now you need to tell Tumblr to allow the plugin to access your account.', 'tumblr-importer' ); ?></p>
			<p><?php esc_html_e( "To do this, click the Authorize link below. You will be redirected back to this page when you've granted the permission.", 'tumblr-importer' ); ?></p>

			<p><a href="<?php echo esc_url_raw( $this->authorize_url ); ?>"><?php esc_html_e( 'Authorize the Application', 'tumblr-importer' ); ?></a></p>
				<?php
			}
		}

		/**
		 * Checks the credentials.
		 *
		 * @return void
		 */
		public function check_credentials() {
			check_admin_referer( 'tumblr-import' );
			$response = $this->oauth_get_request_token();
			if ( ! $response ) {
				return;
			}

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$this->error = __( 'Tumblr returned an error: ', 'tumblr-importer' ) . wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_body( $response );
				return;
			}
			// parse the body
			$this->request_tokens = array();
			wp_parse_str( wp_remote_retrieve_body( $response ), $this->request_tokens );
			$this->authorize_url = add_query_arg(
				array(
					'oauth_token' => $this->request_tokens ['oauth_token'],
				),
				'https://www.tumblr.com/oauth/authorize'
			);

			return;
		}

		/**
		 * Checks the permissions.
		 *
		 * @return void
		 */
		public function check_permissions() {
			$verifier = $_GET['oauth_verifier'];
			$token    = $_GET['oauth_token'];

			// get the access_tokens
			$url = 'https://www.tumblr.com/oauth/access_token';

			$params = array(
				'oauth_consumer_key'     => $this->consumerkey,
				'oauth_nonce'            => time() . rand(),
				'oauth_timestamp'        => time(),
				'oauth_token'            => $this->request_tokens['oauth_token'],
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_verifier'         => $verifier,
				'oauth_version'          => '1.0',
			);

			$params['oauth_signature'] = $this->oauth_signature( array( $this->secretkey, $this->request_tokens['oauth_token_secret'] ), 'GET', $url, $params );

			$url      = add_query_arg( array_map( 'urlencode', $params ), $url );
			$response = wp_remote_get( $url );
			unset( $this->request_tokens );
			if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
				$this->error = __( 'Tumblr returned an error: ', 'tumblr-importer' ) . wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_body( $response );
				return;
			} else {
				$this->access_tokens = array();
				wp_parse_str( wp_remote_retrieve_body( $response ), $this->access_tokens );
			}

			// try to get the list of blogs on the account

			$blogs = $this->get_blogs();
			if ( is_wp_error( $blogs ) ) {
				$this->error = $blogs->get_error_message();
			} else {
				$this->blogs = $blogs;
			}
			return;
		}

		/**
		 * Displays the blogs screen.
		 *
		 * @param string $error Optional error message.
		 *
		 * @return void
		 */
		public function show_blogs( $error = null ) {

			if ( ! empty( $error ) ) {
				echo "<div class='error'><p>" . esc_html( $error ) . '</p></div>';
			}

			$authors = get_users( version_compare( get_bloginfo( 'version' ), '5.9.0', '<' ) ? array( 'who' => 'authors' ) : array( 'capability' => 'edit_posts' ) );
			?>
		<div class='wrap'>
			<?php
			if ( version_compare( get_bloginfo( 'version' ), '3.8.0', '<' ) ) {
                // phpcs:ignore WordPress.WP.DeprecatedFunctions
				screen_icon(); // Behind a version check.
			}
			?>
		<h2><?php esc_html_e( 'Import Tumblr', 'tumblr-importer' ); ?></h2>
		<p><?php esc_html_e( 'Please select the Tumblr blog you would like to import into your WordPress site and then click on the "Import this Blog" button to continue.', 'tumblr-importer' ); ?></p>
		<p><?php esc_html_e( 'If your import gets stuck for a long time or you would like to import from a different Tumblr account instead then click on the "Clear account information" button below to reset the importer.', 'tumblr-importer' ); ?></p>
			<?php if ( 1 < count( $authors ) ) : ?>
			<p><?php esc_html_e( 'As Tumblr does not expose the "author", even from multi-author blogs you will need to select which WordPress user will be listed as the author of the imported posts.', 'tumblr-importer' ); ?></p>
		<?php endif; ?>
		<table class="widefat" cellspacing="0"><thead>
		<tr>
		<th><?php esc_html_e( 'Tumblr Blog', 'tumblr-importer' ); ?></th>
		<th><?php esc_html_e( 'URL', 'tumblr-importer' ); ?></th>
		<th><?php esc_html_e( 'Posts Imported', 'tumblr-importer' ); ?></th>
		<th><?php esc_html_e( 'Drafts Imported', 'tumblr-importer' ); ?></th>
		<!--<th><?php esc_html_e( 'Queued Imported', 'tumblr-importer' ); ?></th>-->
		<th><?php esc_html_e( 'Pages Imported', 'tumblr-importer' ); ?></th>
		<th><?php esc_html_e( 'Author', 'tumblr-importer' ); ?></th>
		<th><?php esc_html_e( 'Action/Status', 'tumblr-importer' ); ?></th>
		</tr></thead>
		<tbody>
			<?php
			$style          = '';
			$custom_domains = false;
			foreach ( $this->blogs as $blog ) {
				$url   = $blog['url'];
				$style = ( 'alternate' == $style ) ? '' : 'alternate';
				if ( ! isset( $this->blog[ $url ] ) ) {
					$this->blog[ $url ]['posts_complete']  = 0;
					$this->blog[ $url ]['drafts_complete'] = 0;
					$this->blog[ $url ]['queued_complete'] = 0;
					$this->blog[ $url ]['pages_complete']  = 0;
					$this->blog[ $url ]['total_posts']     = $blog['posts'];
					$this->blog[ $url ]['total_drafts']    = $blog['drafts'];
					$this->blog[ $url ]['total_queued']    = $blog['queued'];
					$this->blog[ $url ]['name']            = $blog['name'];
				}

				if ( empty( $this->blog[ $url ]['progress'] ) ) {
					$submit = "<input type='submit' value='" . esc_attr__( 'Import this blog', 'tumblr-importer' ) . "' />";
				} elseif ( 'finish' === $this->blog[ $url ]['progress'] ) {
					$submit = '<img src="' . esc_url( admin_url( 'images/yes.png' ) ) . '" style="vertical-align: top; padding: 0 4px;" alt="' . esc_attr__( 'Finished!', 'tumblr-importer' ) . '" title="' . esc_attr__( 'Finished!', 'tumblr-importer' ) . '" /><span>' . esc_html__( 'Finished!', 'tumblr-importer' ) . '</span>';
				} else {
					$submit = '<img src="' . admin_url( 'images/loading.gif' ) . '" style="vertical-align: top; padding: 0 4px;" alt="' . __( 'In Progress', 'tumblr-importer' ) . '" title="' . __( 'In Progress', 'tumblr-importer' ) . '" /><span>' . __( 'In Progress', 'tumblr-importer' ) . '</span>';
					// Just a little js page reload to show progress if we're in the in-progress phase of the import.
					$submit .= "<script type='text/javascript'>setTimeout( 'window.location.href = window.location.href', 15000);</script>";
				}

				// Check to see if this url is a custom domain. The API doesn't play nicely with these
				// (intermittently returns 408 status), so make the user disable the custom domain
				// before importing.
				if ( ! preg_match( '|tumblr.com/|', $url ) ) {
					$submit         = '<nobr><img src="' . admin_url( 'images/no.png' ) . '" style="vertical-align:top; padding: 0 4px;" alt="' . __( 'Tumblr Blogs with Custom Domains activated cannot be imported, please disable the custom domain first.', 'tumblr-importer' ) . '" title="' . __( 'Tumblr Blogs with Custom Domains activated cannot be imported, please disable the custom domain first.', 'tumblr-importer' ) . '" /><span style="cursor: pointer;" title="' . __( 'Tumblr Blogs with Custom Domains activated cannot be imported, please disable the custom domain first.', 'tumblr-importer' ) . '">' . __( 'Custom Domain', 'tumblr-importer' ) . '</nobr></span>';
					$custom_domains = true;
				}

				// Build an author selector / static name depending on number
				if ( 1 == count( $authors ) ) {
					$author_selection = "<input type='hidden' value='" . esc_attr( $authors[0]->ID ) . "' name='post_author' />" . esc_html( $authors[0]->display_name );
				} else {
					$args = array(
						'who'  => 'authors',
						'name' => 'post_author',
						'echo' => false,
					);
					if ( isset( $this->blog[ $url ]['post_author'] ) ) {
						$args['selected'] = $this->blog[ $url ]['post_author'];
					}
					$author_selection = wp_dropdown_users( $args );
				}
				?>
			<tr class="<?php echo esc_attr( $style ); ?>">
			<form action='?import=tumblr' method='post'>
				<?php wp_nonce_field( 'tumblr-import' ); ?>
			<input type='hidden' name='blogurl' value='<?php echo esc_attr( $blog['url'] ); ?>' />

				<td><?php echo esc_html( $blog['title'] ); ?></td>
				<td><?php echo esc_html( $blog['url'] ); ?></td>
				<td><?php echo esc_html( $this->blog[ $url ]['posts_complete'] . ' / ' . esc_html( $this->blog[ $url ]['total_posts'] ) ); ?></td>
				<td><?php echo esc_html( $this->blog[ $url ]['drafts_complete'] . ' / ' . esc_html( $this->blog[ $url ]['total_drafts'] ) ); ?></td>
				<!--<td><?php echo esc_html( $this->blog[ $url ]['queued_complete'] ); ?></td>-->
				<td><?php echo esc_html( $this->blog[ $url ]['pages_complete'] ); ?></td>
				<?php // The below are generated above and escaped where needed. ?>
				<td><?php echo $author_selection; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				<td><?php echo $submit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</form>
			</tr>
				<?php
			}
			?>
		</tbody>
		</table>
			<?php if ( $custom_domains ) : ?>
		<p><strong>
				<?php esc_html_e( 'As one or more of your Tumblr blogs has a Custom Domain mapped to it. If you would like to import one of these sites you will need to temporarily remove the custom domain mapping and clear the account information from the importer to import. Once the import is completed you can re-enable the custom domain for your site.', 'tumblr-importer' ); ?>
		</strong></p>
		<?php endif; ?>
		<p><?php esc_html_e( "Importing your Tumblr blog can take a while so the importing process happens in the background and you may not see immediate results here. Come back to this page later to check on the importer's progress.", 'tumblr-importer' ); ?></p>
		</div>
			<?php
		}

		/**
		 * Starts the blog import.
		 *
		 * @return void
		 */
		public function start_blog_import() {
			check_admin_referer( 'tumblr-import' );
			$url = $_POST['blogurl'];

			if ( ! isset( $this->blog[ $url ] ) ) {
				$this->error = __( 'The specified blog cannot be found.', 'tumblr-importer' );
				return;
			}

			if ( ! empty( $this->blog[ $url ]['progress'] ) ) {
				$this->error = __( 'This blog is currently being imported.', 'tumblr-importer' );
				return;
			}

			$this->blog[ $url ]['progress']    = 'start';
			$this->blog[ $url ]['post_author'] = (int) $_POST['post_author'];

			$this->schedule_import_job( 'do_blog_import', array( $url ) );
		}

		/**
		 * Restarts the import.
		 *
		 * @return void
		 */
		public function restart() {
			check_admin_referer( 'tumblr-import' );
			delete_option( get_class( $this ) );
			wp_redirect( '?import=tumblr' );
		}

		/**
		 * Performs the blog import.
		 *
		 * @param string $url The URL of the blog to import.
		 *
		 * @return void
		 */
		public function do_blog_import( $url ) {
			if ( ! defined( 'WP_IMPORTING' ) ) {
				define( 'WP_IMPORTING', true );
			}

			// default to the done state
			$done = true;

			$this->error = null;
			if ( ! empty( $this->blog[ $url ]['progress'] ) ) {
				$done = false;
				do {
					switch ( $this->blog[ $url ]['progress'] ) {
						case 'start':
						case 'posts':
							$this->do_posts_import( $url );
							break;
						case 'drafts':
							$this->do_drafts_import( $url );
							break;
						case 'queued':
							// TODO Tumblr's API is broken for queued posts
							$this->blog[ $url ]['progress'] = 'pages';
							// $this->do_queued_import($url);
							break;
						case 'pages':
							// TODO Tumblr's new API has no way to retrieve pages that I can find
							$this->blog[ $url ]['progress'] = 'finish';
							// $this->do_pages_import($url);
							break;
						case 'finish':
						default:
							$done = true;
							break;
					}
					$this->save_vars();
				} while ( empty( $this->error ) && ! $done && $this->have_time() );
			}

			return $done;
		}

		/**
		 * Performs the posts import.
		 *
		 * @param string $url The URL of the blog to import.
		 *
		 * @return void
		 */
		public function do_posts_import( $url ) {
			$start = $this->blog[ $url ]['posts_complete'];
			$total = $this->blog[ $url ]['total_posts'];

			// check for posts completion
			if ( $start >= $total ) {
				$this->blog[ $url ]['progress'] = 'drafts';
				return;
			}

			// get the already imported posts to prevent dupes
			$dupes = $this->get_imported_posts( 'tumblr', $this->blog[ $url ]['name'] );

			if ( $this->blog[ $url ]['posts_complete'] + TUMBLR_MAX_IMPORT > $total ) {
				$count = $total - $start;
			} else {
				$count = TUMBLR_MAX_IMPORT;
			}

			$imported_posts = $this->fetch_posts( $url, $start, $count, $this->email, $this->password );

			if ( false === $imported_posts ) {
				$this->error = __( 'Problem communicating with Tumblr, retrying later', 'tumblr-importer' );
				return;
			}

			if ( is_array( $imported_posts ) && ! empty( $imported_posts ) ) {
				reset( $imported_posts );
				$post = current( $imported_posts );
				do {
					// skip dupes
					if ( ! empty( $dupes[ $post['tumblr_url'] ] ) ) {
						++$this->blog[ $url ]['posts_complete'];
						$this->save_vars();
						continue;
					}

					if ( isset( $post['private'] ) ) {
						$post['post_status'] = 'private';
					} else {
						$post['post_status'] = 'publish';
					}

					$post['post_author'] = $this->blog[ $url ]['post_author'];

					do_action( 'tumblr_importing_post', $post );
					$id = wp_insert_post( $post );

					if ( ! is_wp_error( $id ) ) {
						$post['ID'] = $id; // Allows for the media importing to wp_update_post()
						if ( isset( $post['format'] ) ) {
							set_post_format( $id, $post['format'] );
						}

						// @todo: Add basename of the permalink as a 404 redirect handler for when a custom domain has been brought accross
						add_post_meta( $id, 'tumblr_' . $this->blog[ $url ]['name'] . '_permalink', $post['tumblr_url'] );
						add_post_meta( $id, 'tumblr_' . $this->blog[ $url ]['name'] . '_id', $post['tumblr_id'] );

						$import_result = $this->handle_sideload( $post );

						// Handle failed imports.. If empty content and failed to import media..
						if ( is_wp_error( $import_result ) ) {
							if ( empty( $post['post_content'] ) ) {
								wp_delete_post( $id, true );
							}
						}
					}

					++$this->blog[ $url ]['posts_complete'];
					$this->save_vars();

				} while ( false != ( $post = next( $imported_posts ) ) && $this->have_time() );
			}
		}

		/**
		 * Gets the draft post type.
		 *
		 * @param string $post_type The post type.
		 *
		 * @return string
		 */
		public function get_draft_post_type( $post_type ) {
			return 'draft';
		}

		/**
		 * Performs the drafts import.
		 *
		 * @param string $url The URL of the blog to import.
		 *
		 * @return void
		 */
		public function do_drafts_import( $url ) {
			$start = $this->blog[ $url ]['drafts_complete'];
			$total = $this->blog[ $url ]['total_drafts'];

			// check for posts completion
			if ( $start >= $total ) {
				$this->blog[ $url ]['progress'] = 'queued';
				return;
			}

			// get the already imported posts to prevent dupes
			$dupes = $this->get_imported_posts( 'tumblr', $this->blog[ $url ]['name'] );

			if ( $this->blog[ $url ]['posts_complete'] + TUMBLR_MAX_IMPORT > $total ) {
				$count = $total - $start;
			} else {
				$count = TUMBLR_MAX_IMPORT;
			}

			add_filter( 'tumblr_post_type', array( $this, 'get_draft_post_type' ) );
			$imported_posts = $this->fetch_posts( $url, $start, $count, $this->email, $this->password, 'draft' );

			if ( empty( $imported_posts ) ) {
				$this->error = __( 'Problem communicating with Tumblr, retrying later', 'tumblr-importer' );
				return;
			}

			if ( is_array( $imported_posts ) && ! empty( $imported_posts ) ) {
				reset( $imported_posts );
				$post = current( $imported_posts );
				do {
					// skip dupes
					if ( ! empty( $dupes[ $post['tumblr_url'] ] ) ) {
						++$this->blog[ $url ]['drafts_complete'];
						$this->save_vars();
						continue;
					}

					$post['post_status'] = 'draft';
					$post['post_author'] = $this->blog[ $url ]['post_author'];

					do_action( 'tumblr_importing_post', $post );
					$id = wp_insert_post( $post );
					if ( ! is_wp_error( $id ) ) {
						$post['ID'] = $id;
						if ( isset( $post['format'] ) ) {
							set_post_format( $id, $post['format'] );
						}

						add_post_meta( $id, 'tumblr_' . $this->blog[ $url ]['name'] . '_permalink', $post['tumblr_url'] );
						add_post_meta( $id, 'tumblr_' . $this->blog[ $url ]['name'] . '_id', $post['tumblr_id'] );

						$this->handle_sideload( $post );
					}

					++$this->blog[ $url ]['drafts_complete'];
					$this->save_vars();
				} while ( false != ( $post = next( $imported_posts ) ) && $this->have_time() );
			}
		}

		/**
		 * Performs the pages import.
		 *
		 * @param string $url The URL of the blog to import.
		 *
		 * @return void
		 */
		public function do_pages_import( $url ) {
			$start = $this->blog[ $url ]['pages_complete'];

			// get the already imported posts to prevent dupes
			$dupes = $this->get_imported_posts( 'tumblr', $this->blog[ $url ]['name'] );

			$imported_pages = $this->fetch_pages( $url, $this->email, $this->password );

			if ( false === $imported_pages ) {
				$this->error = __( 'Problem communicating with Tumblr, retrying later', 'tumblr-importer' );
				return;
			}

			if ( is_array( $imported_pages ) && ! empty( $imported_pages ) ) {
				reset( $imported_pages );
				$post = current( $imported_pages );
				do {
					// skip dupes
					if ( ! empty( $dupes[ $post['tumblr_url'] ] ) ) {
						continue;
					}

					$post['post_type']   = 'page';
					$post['post_status'] = 'publish';
					$post['post_author'] = $this->blog[ $url ]['post_author'];

					$id = wp_insert_post( $post );
					if ( ! is_wp_error( $id ) ) {
						add_post_meta( $id, 'tumblr_' . $this->blog[ $url ]['name'] . '_permalink', $post['tumblr_url'] );
						$post['ID'] = $id;
						$this->handle_sideload( $post );
					}

					++$this->blog[ $url ]['pages_complete'];
					$this->save_vars();
				} while ( false != ( $post = next( $imported_pages ) ) );
			}
			$this->blog[ $url ]['progress'] = 'finish';
		}

		/**
		 * Handles the sideload import.
		 *
		 * @param array  $post The post.
		 * @param string $source The source.
		 * @param string $description The description.
		 * @param string $filename The filename.
		 *
		 * @return int|WP_Error
		 */
		public function handle_sideload_import( $post, $source, $description = '', $filename = false ) {
			// Make a HEAD request to get the filename:
			if ( empty( $filename ) ) {
				$head = wp_remote_request( $source, array( 'method' => 'HEAD' ) );
				if ( ! empty( $head['headers']['location'] ) ) {
					$source   = $head['headers']['location'];
					$filename = preg_replace( '!\?.*!', '', basename( $source ) ); // Strip off the Query vars
				}
			}

			// still empty? Darned inconsistent tumblr...
			if ( empty( $filename ) ) {
				$path     = parse_url( $source, PHP_URL_PATH );
				$filename = basename( $path );
			}
			// Download file to temp location
			$tmp = download_url( $source );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}

			$file_array['name']     = ! empty( $filename ) ? $filename : basename( $tmp );
			$file_array['tmp_name'] = $tmp;
			// do the validation and storage stuff
			$id = media_handle_sideload( $file_array, $post['ID'], $description, array( 'post_excerpt' => $description ) );

			if ( $id && ! is_wp_error( $id ) ) {
				// Update the date/time on the attachment to that of the Tumblr post.
				$attachment = get_post( $id, ARRAY_A );
				foreach ( array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' ) as $field ) {
					if ( isset( $post[ $field ] ) ) {
						$attachment[ $field ] = $post[ $field ];
					}
				}
				wp_update_post( $attachment );
			}

			// If error storing permanently, unlink
			if ( is_wp_error( $id ) ) {
				@unlink( $file_array['tmp_name'] );
			}
			return $id;
		}

		/**
		 * Handles the sideload.
		 *
		 * @param array $post The post.
		 *
		 * @return void
		 */
		public function handle_sideload( $post ) {

			if ( empty( $post['format'] ) ) {
				return; // Nothing to import.
			}

			switch ( $post['format'] ) {
				case 'gallery':
					if ( ! empty( $post['gallery'] ) ) {
						foreach ( $post['gallery'] as $i => $photo ) {
							$id = $this->handle_sideload_import( $post, (string) $photo['src'], (string) $photo['caption'] );
							if ( is_wp_error( $id ) ) {
								return $id;
							}
						}
						$post['post_content'] = "[gallery]\n" . $post['post_content'];
						$post                 = apply_filters( 'tumblr_importer_format_post', $post );
						do_action( 'tumblr_importer_metadata', $post );
						wp_update_post( $post );
						break; // If we processed a gallery, break, otherwise let it fall through to the Image handler
					}
					// fall through

				case 'image':
					if ( isset( $post['media']['src'] ) ) {
						$id = $this->handle_sideload_import( $post, (string) $post['media']['src'], (string) $post['post_title'] );
						if ( is_wp_error( $id ) ) {
							return $id;
						}

						$link = ! empty( $post['media']['link'] ) ? $post['media']['link'] : null;
						// image_send_to_editor has a filter to wrap in a shortcode.
						$post_content                = $post['post_content'];
						$post['post_content']        = get_image_send_to_editor( $id, (string) $post['post_title'], (string) $post['post_title'], 'none', $link, true, 'full' );
						$post['post_content']       .= $post_content;
						$post['meta']['attribution'] = $link;
						$post                        = apply_filters( 'tumblr_importer_format_post', $post );
						do_action( 'tumblr_importer_metadata', $post );
						// $post['post_content'] .= "\n" . $post['post_content']; // the [caption] shortcode doesn't allow HTML, but this might have some extra markup
						wp_update_post( $post );
					}
					break;

				case 'audio':
					// Handle Tumblr Hosted Audio
					if ( isset( $post['media']['audio'] ) ) {
						$id = $this->handle_sideload_import( $post, (string) $post['media']['audio'], $post['post_title'], (string) $post['media']['filename'] );
						if ( is_wp_error( $id ) ) {
							return $id;
						}
						$post['post_content'] = wp_get_attachment_link( $id ) . "\n" . $post['post_content'];
					} else {
						// Try to work out a "source" link to display Tumblr-style.
						preg_match( '/(http[^ "<>\']+)/', $post['post_content'], $matches );
						if ( isset( $matches[1] ) ) {
							$url_parts                   = parse_url( $matches[1] );
							$post['meta']['attribution'] = $url_parts['scheme'] . '://' . $url_parts['host'] . '/';
						}
					}
					$post = apply_filters( 'tumblr_importer_format_post', $post );
					do_action( 'tumblr_importer_metadata', $post );
					wp_update_post( $post );
					break;

				case 'video':
					// Handle Tumblr hosted video
					if ( isset( $post['media']['video'] ) ) {
						$id = $this->handle_sideload_import( $post, (string) $post['media']['video'], $post['post_title'], (string) $post['media']['filename'] );
						if ( is_wp_error( $id ) ) {
							return $id;
						}

						// @TODO: Check/change this to embed the imported video.
						$link                        = wp_get_attachment_link( $id ) . "\n" . $post['post_content'];
						$post['post_content']        = $link;
						$post['meta']['attribution'] = $link;
					} else {
						// Try to work out a "source" link to mimic Tumblr's post formatting.
						preg_match( '/(http[^ "<>\']+)/', $post['post_content'], $matches );
						if ( isset( $matches[1] ) ) {
							$url_parts                   = parse_url( $matches[1] );
							$post['meta']['attribution'] = $url_parts['scheme'] . '://' . $url_parts['host'] . '/';
						}
					}
					$post = apply_filters( 'tumblr_importer_format_post', $post );
					do_action( 'tumblr_importer_metadata', $post );
					wp_update_post( $post );

					// Else, Check to see if the url embedded is handled by oEmbed (or not)
					break;
			}

			return true; // all processed
		}


		/**
		 * Get a request token from the OAuth endpoint (also serves as a test)
		 *
		 * @return bool|WP_Error
		 */
		public function oauth_get_request_token() {
			if ( empty( $this->consumerkey ) || empty( $this->secretkey ) ) {
				return false;
			}

			$url = 'https://www.tumblr.com/oauth/request_token';

			$params = array(
				'oauth_callback'         => self_admin_url( 'admin.php?import=tumblr' ),
				'oauth_consumer_key'     => $this->consumerkey,
				'oauth_version'          => '1.0',
				'oauth_nonce'            => time(),
				'oauth_timestamp'        => time(),
				'oauth_signature_method' => 'HMAC-SHA1',
			);

			$params['oauth_signature'] = $this->oauth_signature( array( $this->secretkey, '' ), 'POST', $url, $params );

			$response = wp_remote_post( $url, array( 'body' => $params ) );

			return $response;
		}

		/**
		 * Fetch a list of blogs for a user
		 *
		 * @returns array of blog info or a WP_Error
		 */
		public function get_blogs() {
			$url      = 'https://api.tumblr.com/v2/user/info';
			$response = $this->oauth_get_request( $url );

			switch ( $response->meta->status ) {
				case 403: // Bad Username / Password
					do_action( 'tumblr_importer_handle_error', 'get_blogs_403' );
					return new WP_Error( 'tumblr_error', __( 'Tumblr says that the the app is not authorized. Please check the settings and try to connect again.', 'tumblr-importer' ) );
				case 200: // OK
					break;
				default:
					// translators: %s is the error message from Tumblr.
					$_error = sprintf( __( 'Tumblr replied with an error: %s', 'tumblr-importer' ), $response->meta->msg );
					do_action( 'tumblr_importer_handle_error', 'response_' . $response->meta->status );
					return new WP_Error( 'tumblr_error', $_error );
			}

			$blogs = array();
			foreach ( $response->response->user->blogs as $tblog ) {
				$blog           = array();
				$blog['title']  = (string) $tblog->title;
				$blog['posts']  = (int) $tblog->posts;
				$blog['drafts'] = (int) $tblog->drafts;
				$blog['queued'] = (int) $tblog->queue;
				$blog['avatar'] = '';
				$blog['url']    = (string) $this->sanitize_blog_url( $tblog->url );
				$blog['name']   = (string) $tblog->name;

				$blogs[] = $blog;
			}
			$this->blogs = $blogs;
			return $this->blogs;
		}

		/**
		 * Make sure the URL of the tumblr blog is in the correct format.
		 *
		 * If the URL is in the format https://tumblr.com/blogname, then we need to convert it to https://blogname.tumblr.com.
		 * If the URL is in the format https://blogname.tumblr.com, we don't need to do anything.
		 * Finally, we skip sanitizing custom Tumblr domains.
		 *
		 * @param string $url URL of Tumblr blog returned by the API.
		 *
		 * @return string
		 */
		private function sanitize_blog_url( $url ) {

			// If the URL is already in a valid format, just return it.
			if ( preg_match( '#^https://.*?\.tumblr.com/?$#', $url ) ) {
				return $url;
			}

			// If the URL is not in a correct format, we compose the new one with the short name of the blog.
			if ( preg_match( '#^https://(?:www\.)?tumblr.com/(?:blog/view/)?(?P<id>.+?)/?$#', $url, $matches ) ) {
				return sprintf( 'https://%s.tumblr.com/', $matches['id'] );
			}

			// Or, just return the original URL.
			return $url;
		}

		/**
		 * Gets the consumer key.
		 *
		 * @return string
		 */
		public function get_consumer_key() {
			return $this->consumerkey;
		}

		/**
		 * Fetch a subset of posts from a tumblr blog
		 *
		 * @param string $url The URL of the blog.
		 * @param int    $start Index to start at.
		 * @param int    $count How many posts to get (max 50).
		 * @param string $email The email.
		 * @param string $password The password.
		 * @param string $state Can be empty for normal posts, or "draft", "queue", or "submission" to get those posts.
		 *
		 * @return false|array
		 */
		public function fetch_posts( $url, $start = 0, $count = 50, $email = null, $password = null, $state = null ) {
			$url       = parse_url( $url, PHP_URL_HOST );
			$post_type = apply_filters( 'tumblr_post_type', '' );
			$url       = trailingslashit( "https://api.tumblr.com/v2/blog/$url/posts/$post_type" );

			do_action( 'tumblr_importer_pre_fetch_posts' );

			// These extra params hose up the auth if passed for oauth requests e.g. for drafts, so use them only for normal posts.
			if ( '' === $post_type ) {
				$params = array(
					'offset'  => $start,
					'limit'   => $count,
					'api_key' => apply_filters( 'tumblr_importer_get_consumer_key', '' ),
				);
				$url    = add_query_arg( $params, $url );
			}

			$response = $this->oauth_get_request( $url );

			switch ( $response->meta->status ) {
				case 200: // OK
					break;
				default:
					// translators: %s is the error message from Tumblr.
					$_error = sprintf( __( 'Tumblr replied with an error: %s', 'tumblr-importer' ), $response->meta->msg );
					do_action( 'tumblr_importer_handle_error', 'response_' . $response->meta->status );
					return new WP_Error( 'tumblr_error', $_error );
			}

			$posts  = array();
			$tposts = $response->response->posts;
			foreach ( $tposts as $tpost ) {
				$post                  = array();
				$post['tumblr_id']     = (string) $tpost->id;
				$post['tumblr_url']    = (string) $tpost->post_url;
				$post['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime( (string) $tpost->date ) );
				$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $tpost->date ) );
				$post['post_name']     = (string) $tpost->slug;
				if ( 'private' === $tpost->state ) {
					$post['private'] = (string) $tpost->state;
				}
				if ( isset( $tpost->tags ) ) {
					$post['tags_input'] = array();
					foreach ( $tpost->tags as $tag ) {
						$post['tags_input'][] = rtrim( (string) $tag, ',' ); // Strip trailing Commas off it too.
					}
				}

				switch ( (string) $tpost->type ) {
					case 'photo':
						$post['format']          = 'image';
						$post['media']['src']    = (string) $tpost->photos[0]->original_size->url;
						$post['media']['link']   = '';// TODO: Find out what to use here.(string) $tpost->{'photo-link-url'};
						$post['media']['width']  = (string) $tpost->photos[0]->original_size->width;
						$post['media']['height'] = (string) $tpost->photos[0]->original_size->height;
						$post['post_content']    = (string) $tpost->caption;
						if ( ! empty( $tpost->photos ) ) {
							$post['format'] = 'gallery';
							foreach ( $tpost->photos as $photo ) {
								$post['gallery'][] = array(
									'src'     => $photo->original_size->url,
									'width'   => $photo->original_size->width,
									'height'  => $photo->original_size->height,
									'caption' => $photo->caption,
								);
							}
						}
						break;
					case 'quote':
						$post['format']        = 'quote';
						$post['post_content']  = '<blockquote>' . (string) $tpost->text . '</blockquote>';
						$post['post_content'] .= "\n\n<div class='attribution'>" . (string) $tpost->source . '</div>';
						break;
					case 'link':
						$post['format']       = 'link';
						$linkurl              = (string) $tpost->url;
						$linktext             = (string) $tpost->title;
						$post['post_content'] = "<a href='$linkurl'>$linktext</a>";
						if ( ! empty( $tpost->description ) ) {
							$post['post_content'] .= '<div class="link_description">' . (string) $tpost->description . '</div>';
						}
						$post['post_title'] = (string) $tpost->title;
						break;
					case 'chat':
						$post['format']       = 'chat';
						$post['post_title']   = (string) $tpost->title;
						$post['post_content'] = (string) $tpost->body;
						break;
					case 'audio':
						$post['format']            = 'audio';
						$post['media']['filename'] = basename( (string) $tpost->audio_url );
						// If no .mp3 extension, add one so that sideloading works.
						if ( ! preg_match( '/\.mp3$/', $post['media']['filename'] ) ) {
							$post['media']['filename'] .= '.mp3';
						}
						$post['media']['audio'] = (string) $tpost->audio_url . '?plead=please-dont-download-this-or-our-lawyers-wont-let-us-host-audio';
						$post['post_content']   = (string) $tpost->player . "\n" . (string) $tpost->caption;
						break;
					case 'video':
						$post['format']       = 'video';
						$post['post_content'] = '';

						$video = array_shift( $tpost->player );

						if ( false !== strpos( (string) $video->embed_code, 'embed' ) ) {
							if ( preg_match_all( '/<embed (.+?)>/', (string) $video->embed_code, $matches ) ) {
								foreach ( $matches[1] as $match ) {
									foreach ( wp_kses_hair( $match, array( 'http' ) ) as $attr ) {
										$embed[ $attr['name'] ] = $attr['value'];
									}
								}

								// special case for weird youtube vids
								$embed['src'] = preg_replace( '|http://www.youtube.com/v/([a-zA-Z0-9_]+).*|i', 'http://www.youtube.com/watch?v=$1', $embed['src'] );

								// TODO find other special cases, since tumblr is full of them
								$post['post_content'] = $embed['src'];
							}

							// Sometimes, video-source contains iframe markup.
							if ( preg_match( '/<iframe/', $video->embed_code ) ) {
								$embed['src']         = preg_replace( '|<iframe.*src="http://www.youtube.com/embed/([a-zA-Z0-9_\-]+)\??.*".*</iframe>|', 'http://www.youtube.com/watch?v=$1', $video->embed_code );
								$post['post_content'] = $embed['src'];
							}
						} elseif ( preg_match( '/<iframe.*vimeo/', $video->embed_code ) ) {
							$embed['src']         = preg_replace( '|<iframe.*src="(http://player.vimeo.com/video/([a-zA-Z0-9_\-]+))\??.*".*</iframe>.*|', 'http://vimeo.com/$2', $video->embed_code );
							$post['post_content'] = $embed['src'];
						} else {
							// @todo: See if the video source is going to be oEmbed'able before adding the flash player
							$post['post_content'] .= $video->embed_code;
						}

						$post['post_content'] .= "\n" . (string) $tpost->caption;
						break;
					case 'answer':
						// TODO: Include asking_name and asking_url values?
						$post['post_title']   = (string) $tpost->question;
						$post['post_content'] = (string) $tpost->answer;
						break;
					case 'regular':
					case 'text':
					default:
						$post['post_title']   = (string) $tpost->title;
						$post['post_content'] = (string) $tpost->body;
						break;
				}
				$posts[] = $post;
			}

			return $posts;
		}

		/**
		 * Fetch the Pages from a tumblr blog
		 *
		 * @param string $url The URL of the blog.
		 * @param string $email The email.
		 * @param string $password The password.
		 *
		 * @return false|array
		 */
		public function fetch_pages( $url, $email = null, $password = null ) {
			$tumblrurl = trailingslashit( $url ) . 'api/pages';
			$params    = array(
				'email'    => $email,
				'password' => $password,
			);
			$options   = array( 'body' => $params );

			// fetch the pages
			$out = wp_remote_post( $tumblrurl, $options );
			if ( wp_remote_retrieve_response_code( $out ) !== 200 ) {
				return false;
			}
			$body = wp_remote_retrieve_body( $out );

			// parse the XML into something useful
			$xml = simplexml_load_string( $body );

			if ( ! isset( $xml->pages ) ) {
				return false;
			}

			$tpages = $xml->pages;
			$pages  = array();
			foreach ( $tpages->page as $tpage ) {
				if ( ! empty( $tpage['title'] ) ) {
					$page['post_title'] = (string) $tpage['title'];
				} elseif ( ! empty( $tpage['link-title'] ) ) {
					$page['post_title'] = (string) $tpage['link-title'];
				} else {
					$page['post_title'] = '';
				}
				$page['post_name']    = str_replace( $url, '', (string) $tpage['url'] );
				$page['post_content'] = (string) $tpage;
				$page['tumblr_url']   = (string) $tpage['url'];
				$pages[]              = $page;
			}

			return $pages;
		}

		/**
		 * Filters the post format.
		 *
		 * @param array $_post The post.
		 *
		 * @return array
		 */
		public function filter_format_post( $_post ) {
			if ( isset( $_post['meta']['attribution'] ) ) {
				$attribution = $_post['meta']['attribution'];
				if ( preg_match( '/^http[^ ]+$/', $_post['meta']['attribution'] ) ) {
					$attribution = sprintf( '<a href="%s">%s</a>', $_post['meta']['attribution'], $_post['meta']['attribution'] );
				}
				$_post['post_content'] .= sprintf( '<div class="attribution">(<span>' . __( 'Source:', 'tumblr-importer' ) . '</span> %s)</div>', $attribution );
			}

			return $_post;
		}

		/**
		 * Adds the Tumblr metadata to the post.
		 *
		 * @param array $_post The post.
		 *
		 * @return void
		 */
		public function tumblr_importer_metadata( $_post ) {
			if ( isset( $_post['meta'] ) ) {
				foreach ( $_post['meta'] as $key => $val ) {
					add_post_meta( $_post['ID'], 'tumblr_' . $key, $val );
				}
			}
		}

		/**
		 * When galleries have no caption, the post_content field is empty, which
		 * along with empty title and excerpt causes the post not to insert.
		 * Here we override the default behavior.
		 *
		 * @param bool  $maybe_empty Whether the post content is empty.
		 * @param array $_post The post.
		 *
		 * @return bool
		 */
		public function filter_allow_empty_content( $maybe_empty, $_post ) {
			if ( ! empty( $_post['format'] ) && 'gallery' === $_post['format'] ) {
				return false;
			}

			return $maybe_empty;
		}


		/**
		 * OAuth Signature creation
		 *
		 * @param string $secret The secret.
		 * @param string $method The method.
		 * @param string $url The URL.
		 * @param array  $params The parameters.
		 *
		 * @return string
		 */
		public function oauth_signature( $secret, $method, $url, $params = array() ) {
			uksort( $params, 'strcmp' );
			foreach ( $params as $k => $v ) {
				$pairs[] = $this->urlencode_rfc3986( $k ) . '=' . $this->urlencode_rfc3986( $v );
			}
			$concatenated_params = implode( '&', $pairs );
			$base_string         = $method . '&' . $this->urlencode_rfc3986( $url ) . '&' . $this->urlencode_rfc3986( $concatenated_params );
			if ( ! is_array( $secret ) ) {
				$secret[0] = $secret;
				$secret[1] = '';
			}
			$secret          = $this->urlencode_rfc3986( $secret[0] ) . '&' . $this->urlencode_rfc3986( $secret[1] );
			$oauth_signature = base64_encode( hash_hmac( 'sha1', $base_string, $secret, true ) );
			return $oauth_signature;
		}

		/**
		 * Helper function for OAuth Signature creation
		 *
		 * @param string $input The input.
		 *
		 * @return string
		 */
		protected function urlencode_rfc3986( $input ) {
			if ( is_array( $input ) ) {
				return array_map( array( $this, 'urlencode_rfc3986' ), $input );
			} elseif ( is_scalar( $input ) ) {
				return str_replace( array( '+', '%7E' ), array( ' ', '~' ), rawurlencode( $input ) );
			} else {
				return '';
			}
		}

		/**
		 * Do a GET request with the access tokens
		 *
		 * @param string $url The URL.
		 *
		 * @return false|array
		 */
		public function oauth_get_request( $url ) {
			if ( empty( $this->access_tokens ) ) {
				return false;
			}

			$params = array(
				'oauth_consumer_key'     => $this->get_consumer_key(),
				'oauth_nonce'            => time(),
				'oauth_timestamp'        => time(),
				'oauth_token'            => $this->access_tokens['oauth_token'],
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_version'          => '1.0',
			);

			$params['oauth_signature'] = $this->oauth_signature( array( $this->secretkey, $this->access_tokens['oauth_token_secret'] ), 'GET', $url, $params );

			$url = add_query_arg( array_map( 'urlencode', $params ), $url );

			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return false;
			} else {
				$body = wp_remote_retrieve_body( $response );
				return json_decode( $body );
			}
		}
	}
}
