<?php
/**
 * Class file for Imsanity_CLI
 *
 * Imsanity_CLI contains an extension for WP-CLI to enable bulk resizing of images via command line.
 *
 * @package Imsanity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Implements wp-cli extension for bulk resizing.
 */
class Imsanity_CLI extends WP_CLI_Command {
	/**
	 * Bulk Resize Images
	 *
	 * ## OPTIONS
	 *
	 * <noprompt>
	 * : do not prompt, just resize everything
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli imsanity resize --noprompt
	 *
	 * @synopsis [--noprompt]
	 *
	 * @param array $args A numbered array of arguments provided via WP-CLI without option names.
	 * @param array $assoc_args An array of named arguments provided via WP-CLI.
	 */
	public function resize( $args, $assoc_args ) {

		// let's get started, shall we?
		// imsanity_init();.
		$maxw = imsanity_get_option( 'imsanity_max_width', IMSANITY_DEFAULT_MAX_WIDTH );
		$maxh = imsanity_get_option( 'imsanity_max_height', IMSANITY_DEFAULT_MAX_HEIGHT );

		if ( empty( $assoc_args['noprompt'] ) ) {
			WP_CLI::warning(
				__( 'Bulk Resize will alter your original images and cannot be undone!', 'imsanity' ) . "\n" .
				__( 'It is HIGHLY recommended that you backup your wp-content/uploads folder before proceeding. You will be prompted before resizing each image.', 'imsanity' ) . "\n" .
				__( 'It is also recommended that you initially resize only 1 or 2 images and verify that everything is working properly before processing your entire library.', 'imsanity' )
			);
		}

		/* translators: 1: width in pixels, 2: height in pixels */
		WP_CLI::line( sprintf( __( 'Resizing images to %1$d x %2$d', 'imsanity' ), $maxw, $maxh ) );

		global $wpdb;
		$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE (post_type = 'attachment' OR post_type = 'ims_image') AND post_mime_type LIKE '%%image%%' ORDER BY ID DESC" );

		$image_count = count( $attachments );
		if ( ! $image_count ) {
			WP_CLI::success( __( 'There are no images to resize.', 'imsanity' ) );
			return;
		} elseif ( empty( $assoc_args['noprompt'] ) ) {
			WP_CLI::confirm(
				/* translators: %d: number of images */
				sprintf( __( 'There are %d images to check.', 'imsanity' ), $image_count ) .
				' ' . __( 'Continue?', 'imsanity' )
			);
		} else {
			/* translators: %d: number of images */
			WP_CLI::line( sprintf( __( 'There are %d images to check.', 'imsanity' ), $image_count ) );
		}

		$images_finished = 0;
		foreach ( $attachments as $id ) {
			$imagew = false;
			$imageh = false;
			++$images_finished;

			$path = get_attached_file( $id );
			if ( $path ) {
				list( $imagew, $imageh ) = getimagesize( $path );
			}
			if ( empty( $imagew ) || empty( $imageh ) ) {
				continue;
			}

			if ( $imagew <= $maxw && $imageh <= $maxh ) {
				/* translators: %s: File-name of the image */
				WP_CLI::line( sprintf( esc_html__( 'SKIPPED: %s (Resize not required)', 'imsanity' ), $path ) . " -- $imagew x $imageh" );
				continue;
			}

			$confirm = '';
			if ( empty( $assoc_args['noprompt'] ) ) {
				$confirm = \cli\prompt(
					$path . ': ' . $imagew . 'x' . $imageh .
					"\n" . __( 'Resize (Y/n)?', 'imsanity' )
				);
			}
			if ( 'n' === $confirm ) {
				continue;
			}

			$result = imsanity_resize_from_id( $id );

			if ( $result['success'] ) {
				WP_CLI::line( $result['message'] . " $images_finished / $image_count" );
			} else {
				WP_CLI::warning( $result['message'] . " $images_finished / $image_count" );
			}
		}

		// and let the user know we are done.
		WP_CLI::success( __( 'Finished Resizing!', 'imsanity' ) );
	}

	/**
	 * Convert Images to WebP
	 *
	 * ## OPTIONS
	 *
	 * <noprompt>
	 * : do not prompt, just convert everything
	 *
	 * ## EXAMPLES
	 *
	 *     wp-cli imsanity convert --noprompt
	 *
	 * @synopsis [--noprompt]
	 *
	 * @param array $args A numbered array of arguments provided via WP-CLI without option names.
	 * @param array $assoc_args An array of named arguments provided via WP-CLI.
	 */
	public function convert( $args, $assoc_args ) {

		if ( empty( $assoc_args['noprompt'] ) ) {
			WP_CLI::warning(
				__( 'WebP conversion will alter your original images and cannot be undone!', 'imsanity' ) . "\n" .
				__( 'It is HIGHLY recommended that you backup your wp-content/uploads folder before proceeding. You will be prompted before converting each image.', 'imsanity' ) . "\n" .
				__( 'It is also recommended that you initially convert only 1 or 2 images and verify that everything is working properly before processing your entire library.', 'imsanity' )
			);
		}

		WP_CLI::line( __( 'Converting non-WebP images to WebP format', 'imsanity' ) );

		global $wpdb;
		// Query for non-WebP image attachments
		$attachments = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_mime_type NOT LIKE 'image/webp' ORDER BY ID DESC" );

		$image_count = count( $attachments );
		if ( ! $image_count ) {
			WP_CLI::success( __( 'There are no non-WebP images to convert.', 'imsanity' ) );
			return;
		} elseif ( empty( $assoc_args['noprompt'] ) ) {
			WP_CLI::confirm(
				/* translators: %d: number of images */
				sprintf( __( 'There are %d non-WebP images to convert.', 'imsanity' ), $image_count ) .
				' ' . __( 'Continue?', 'imsanity' )
			);
		} else {
			/* translators: %d: number of images */
			WP_CLI::line( sprintf( __( 'There are %d non-WebP images to convert.', 'imsanity' ), $image_count ) );
		}

		$images_finished = 0;
		foreach ( $attachments as $id ) {
			++$images_finished;

			$path = get_attached_file( $id );
			if ( ! $path || ! file_exists( $path ) ) {
				WP_CLI::warning( sprintf( __( 'SKIPPED: Attachment %d - file not found', 'imsanity' ), $id ) . " $images_finished / $image_count" );
				continue;
			}

			$mime_type = get_post_mime_type( $id );
			if ( 'image/webp' === $mime_type ) {
				WP_CLI::line( sprintf( __( 'SKIPPED: %s (Already WebP)', 'imsanity' ), $path ) . " $images_finished / $image_count" );
				continue;
			}

			// Check if it's a supported image type for conversion
			if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/jpg', 'image/png', 'image/bmp' ), true ) ) {
				WP_CLI::line( sprintf( __( 'SKIPPED: %s (Unsupported format: %s)', 'imsanity' ), $path, $mime_type ) . " $images_finished / $image_count" );
				continue;
			}

			$confirm = '';
			if ( empty( $assoc_args['noprompt'] ) ) {
				$confirm = \cli\prompt(
					$path . ': ' . $mime_type .
					"\n" . __( 'Convert to WebP (Y/n)?', 'imsanity' )
				);
			}
			if ( 'n' === $confirm ) {
				continue;
			}

			// Prepare params for conversion
			$params = array(
				'file' => $path,
				'type' => $mime_type,
			);

			// Use the existing imsanity_convert_to_webp function
			$result = imsanity_convert_to_webp( $mime_type, $params );

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning( sprintf( __( 'FAILED: %s - %s', 'imsanity' ), $path, $result->get_error_message() ) . " $images_finished / $image_count" );
				continue;
			}

			// Check if conversion actually happened
			if ( $result['file'] !== $path ) {
				// Update attachment metadata
				$attachment_metadata = wp_get_attachment_metadata( $id );
				if ( $attachment_metadata ) {
					// Update the main file path
					$old_filename = basename( $path );
					$new_filename = basename( $result['file'] );
					
					// Update file in metadata
					if ( isset( $attachment_metadata['file'] ) ) {
						$attachment_metadata['file'] = str_replace( $old_filename, $new_filename, $attachment_metadata['file'] );
					}

					// Update sizes if they exist
					if ( isset( $attachment_metadata['sizes'] ) && is_array( $attachment_metadata['sizes'] ) ) {
						foreach ( $attachment_metadata['sizes'] as $size => $size_data ) {
							if ( isset( $size_data['file'] ) ) {
								$size_old_ext = pathinfo( $size_data['file'], PATHINFO_EXTENSION );
								$size_new_file = str_replace( '.' . $size_old_ext, '.webp', $size_data['file'] );
								$attachment_metadata['sizes'][ $size ]['file'] = $size_new_file;
								$attachment_metadata['sizes'][ $size ]['mime-type'] = 'image/webp';
							}
						}
					}

					wp_update_attachment_metadata( $id, $attachment_metadata );
				}

				// Update the attachment post
				wp_update_post( array(
					'ID' => $id,
					'post_mime_type' => 'image/webp',
				) );

				// Update the attached file path
				update_attached_file( $id, $result['file'] );

				// Regenerate thumbnails for the converted WebP image
				$regenerate_result = wp_generate_attachment_metadata( $id, $result['file'] );
				if ( $regenerate_result ) {
					wp_update_attachment_metadata( $id, $regenerate_result );
					WP_CLI::success( sprintf( __( 'CONVERTED: %s -> %s (thumbnails regenerated)', 'imsanity' ), $path, $result['file'] ) . " $images_finished / $image_count" );
				} else {
					WP_CLI::success( sprintf( __( 'CONVERTED: %s -> %s (thumbnail regeneration failed)', 'imsanity' ), $path, $result['file'] ) . " $images_finished / $image_count" );
				}
			} else {
				WP_CLI::line( sprintf( __( 'SKIPPED: %s (No conversion needed)', 'imsanity' ), $path ) . " $images_finished / $image_count" );
			}
		}

		// and let the user know we are done.
		WP_CLI::success( __( 'Finished Converting to WebP!', 'imsanity' ) );
	}
}

WP_CLI::add_command( 'imsanity', 'Imsanity_CLI' );
