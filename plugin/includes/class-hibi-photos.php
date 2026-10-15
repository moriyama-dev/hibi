<?php
/**
 * Photos attached to entries.
 *
 * Files live in uploads/hibi-private/{user}/{YYYY}/{MM}/, a folder the web
 * server refuses to serve. They reach the browser only through
 * ?hibi_photo=<id>, after a login and ownership check. Every upload is
 * re-encoded as JPEG, which drops EXIF data including GPS.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Photos {

	const DIR       = 'hibi-private';
	const META      = '_hibi_photo';
	const MAX_EDGE  = 2560;
	const QUALITY   = 85;
	const MAX_BYTES = 30 * MB_IN_BYTES;

	/**
	 * Long edge per size. "full" is capped at MAX_EDGE.
	 */
	const SIZES = array(
		'thumb'  => 400,
		'medium' => 1200,
	);

	const ACCEPTED = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif', 'image/avif' );

	public static function init() {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
		add_action( 'delete_attachment', array( __CLASS__, 'delete_files' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_entry_photos' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'hide_from_media_library' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_from_media_list' ) );
	}

	/**
	 * Absolute path of the private folder.
	 *
	 * @return string
	 */
	public static function base_dir() {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . self::DIR;
	}

	/**
	 * Creates the private folder with the files that keep it closed.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_base_dir() {
		$dir = self::base_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'hibi_photo_dir', __( 'The photo folder could not be created.', 'hibi-journal' ), array( 'status' => 500 ) );
		}

		$htaccess = "# Hibi Journal: photos are served only through WordPress after a login check.\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n";

		$files = array(
			'.htaccess' => $htaccess,
			'index.php' => "<?php\n// Silence is golden.\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) && false === file_put_contents( $path, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Small fixed file inside uploads; WP_Filesystem may need credentials during a REST request.
				return new WP_Error( 'hibi_photo_dir', __( 'The photo folder could not be protected.', 'hibi-journal' ), array( 'status' => 500 ) );
			}
		}
		return true;
	}

	/**
	 * Stores an uploaded photo for an entry.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    YYYY-MM-DD.
	 * @param array  $file    One item of $_FILES.
	 * @return array|WP_Error Photo data.
	 */
	public static function upload( $user_id, $date, array $file ) {
		if ( ! isset( $file['tmp_name'], $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'hibi_photo_upload', __( 'The photo could not be uploaded.', 'hibi-journal' ), array( 'status' => 400 ) );
		}
		return self::store( $user_id, $date, $file['tmp_name'], isset( $file['name'] ) ? (string) $file['name'] : '' );
	}

	/**
	 * Re-encodes an image file and attaches it to the entry.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $date     YYYY-MM-DD.
	 * @param string $source   Path of the original file.
	 * @param string $filename Original file name, kept as the title.
	 * @return array|WP_Error
	 */
	public static function store( $user_id, $date, $source, $filename = '' ) {
		if ( filesize( $source ) > min( self::MAX_BYTES, wp_max_upload_size() ) ) {
			return new WP_Error( 'hibi_photo_too_large', __( 'The photo is too large.', 'hibi-journal' ), array( 'status' => 413 ) );
		}

		$mime = self::detect_mime( $source );
		if ( ! in_array( $mime, self::ACCEPTED, true ) ) {
			return new WP_Error( 'hibi_photo_type', __( 'This file type is not supported. Please choose a JPEG, PNG, WebP or HEIC photo.', 'hibi-journal' ), array( 'status' => 415 ) );
		}

		$ready = self::ensure_base_dir();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$relative = sprintf( '%d/%s/%s', $user_id, substr( $date, 0, 4 ), substr( $date, 5, 2 ) );
		$dir      = self::base_dir() . '/' . $relative;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'hibi_photo_dir', __( 'The photo folder could not be created.', 'hibi-journal' ), array( 'status' => 500 ) );
		}

		// Unguessable, so a leaked folder listing reveals nothing usable.
		$name    = strtolower( wp_generate_password( 24, false ) );
		$encoded = self::encode( $source, $mime, $dir . '/' . $name );
		if ( is_wp_error( $encoded ) ) {
			return $encoded;
		}

		$entry_id = Hibi_Entries::ensure( $user_id, $date );
		if ( is_wp_error( $entry_id ) ) {
			self::unlink_set( $dir . '/' . $name );
			return $entry_id;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
				'post_author'    => (int) $user_id,
				'post_parent'    => $entry_id,
			),
			false,
			$entry_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			self::unlink_set( $dir . '/' . $name );
			return $attachment_id;
		}

		update_post_meta(
			$attachment_id,
			self::META,
			array(
				'path'     => $relative . '/' . $name,
				'width'    => $encoded['width'],
				'height'   => $encoded['height'],
				'taken_at' => $encoded['taken_at'],
			)
		);

		return self::to_array( $attachment_id );
	}

	/**
	 * Writes {base}.jpg, {base}-medium.jpg and {base}-thumb.jpg.
	 *
	 * @param string $source Source path.
	 * @param string $mime   Source MIME type.
	 * @param string $base   Destination path without extension.
	 * @return array{width:int,height:int,taken_at:string}|WP_Error
	 */
	private static function encode( $source, $mime, $base ) {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			return self::encode_imagick( $source, $base );
		}
		if ( in_array( $mime, array( 'image/heic', 'image/heif' ), true ) ) {
			return new WP_Error( 'hibi_photo_heic', __( 'HEIC photos need the Imagick PHP extension on the server. Please share the photo as JPEG instead.', 'hibi-journal' ), array( 'status' => 415 ) );
		}
		return self::encode_editor( $source, $base );
	}

	/**
	 * @param string $source Source path.
	 * @param string $base   Destination path without extension.
	 * @return array|WP_Error
	 */
	private static function encode_imagick( $source, $base ) {
		try {
			$image = new Imagick();
			$image->readImage( $source );
			if ( $image->getNumberImages() > 1 ) {
				$image->setIteratorIndex( 0 );
				$image = $image->getImage();
			}

			$taken_at = (string) $image->getImageProperty( 'exif:DateTimeOriginal' );
			self::orient( $image );

			// Flatten transparency onto white; JPEG has no alpha.
			if ( $image->getImageAlphaChannel() ) {
				$image->setImageBackgroundColor( 'white' );
				$image = $image->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
			}
			$image->transformImageColorspace( Imagick::COLORSPACE_SRGB );
			$image->stripImage();
			$image->setImageFormat( 'jpeg' );
			$image->setImageCompressionQuality( self::QUALITY );
			$image->setInterlaceScheme( Imagick::INTERLACE_JPEG );

			self::fit( $image, self::MAX_EDGE );
			$image->writeImage( 'jpeg:' . $base . '.jpg' );
			$width  = $image->getImageWidth();
			$height = $image->getImageHeight();

			foreach ( self::SIZES as $size => $edge ) {
				$copy = clone $image;
				self::fit( $copy, $edge );
				$copy->writeImage( 'jpeg:' . $base . '-' . $size . '.jpg' );
				$copy->clear();
			}
			$image->clear();
		} catch ( Exception $e ) {
			self::unlink_set( $base );
			return new WP_Error( 'hibi_photo_decode', __( 'The photo could not be read.', 'hibi-journal' ), array( 'status' => 422 ) );
		}

		return array(
			'width'    => $width,
			'height'   => $height,
			'taken_at' => self::normalize_exif_date( $taken_at ),
		);
	}

	/**
	 * GD fallback through the WordPress image editor. GD never writes EXIF.
	 *
	 * @param string $source Source path.
	 * @param string $base   Destination path without extension.
	 * @return array|WP_Error
	 */
	private static function encode_editor( $source, $base ) {
		$taken_at = '';
		if ( function_exists( 'exif_read_data' ) ) {
			$exif     = @exif_read_data( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Malformed EXIF must not break the upload.
			$taken_at = is_array( $exif ) && isset( $exif['DateTimeOriginal'] ) ? (string) $exif['DateTimeOriginal'] : '';
		}

		$sizes = array_merge( array( '' => self::MAX_EDGE ), self::SIZES );
		$full  = null;
		foreach ( $sizes as $size => $edge ) {
			$editor = wp_get_image_editor( $source );
			if ( is_wp_error( $editor ) ) {
				self::unlink_set( $base );
				return new WP_Error( 'hibi_photo_decode', __( 'The photo could not be read.', 'hibi-journal' ), array( 'status' => 422 ) );
			}
			$editor->maybe_exif_rotate();
			$editor->set_quality( self::QUALITY );
			$current = $editor->get_size();
			if ( max( $current['width'], $current['height'] ) > $edge ) {
				$editor->resize( $edge, $edge, false );
			}
			$saved = $editor->save( $base . ( '' === $size ? '' : '-' . $size ) . '.jpg', 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				self::unlink_set( $base );
				return $saved;
			}
			if ( '' === $size ) {
				$full = $saved;
			}
		}

		return array(
			'width'    => (int) $full['width'],
			'height'   => (int) $full['height'],
			'taken_at' => self::normalize_exif_date( $taken_at ),
		);
	}

	/**
	 * Applies the EXIF orientation to the pixels, since stripping the
	 * metadata would otherwise leave phone photos sideways.
	 *
	 * @param Imagick $image Image.
	 */
	private static function orient( Imagick $image ) {
		switch ( $image->getImageOrientation() ) {
			case Imagick::ORIENTATION_TOPRIGHT:
				$image->flopImage();
				break;
			case Imagick::ORIENTATION_BOTTOMRIGHT:
				$image->rotateImage( 'white', 180 );
				break;
			case Imagick::ORIENTATION_BOTTOMLEFT:
				$image->flipImage();
				break;
			case Imagick::ORIENTATION_LEFTTOP:
				$image->transposeImage();
				break;
			case Imagick::ORIENTATION_RIGHTTOP:
				$image->rotateImage( 'white', 90 );
				break;
			case Imagick::ORIENTATION_RIGHTBOTTOM:
				$image->transverseImage();
				break;
			case Imagick::ORIENTATION_LEFTBOTTOM:
				$image->rotateImage( 'white', 270 );
				break;
		}
		$image->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
	}

	/**
	 * Shrinks to fit a square of $edge pixels; never enlarges.
	 *
	 * @param Imagick $image Image.
	 * @param int     $edge  Long edge.
	 */
	private static function fit( Imagick $image, $edge ) {
		if ( max( $image->getImageWidth(), $image->getImageHeight() ) > $edge ) {
			$image->resizeImage( $edge, $edge, Imagick::FILTER_LANCZOS, 1, true );
		}
	}

	/**
	 * "2026:10:14 09:30:00" to "2026-10-14T09:30:00", or "".
	 *
	 * @param string $value EXIF date.
	 * @return string
	 */
	private static function normalize_exif_date( $value ) {
		return preg_match( '/^(\d{4}):(\d{2}):(\d{2}) (\d{2}:\d{2}:\d{2})/', $value, $m ) ? "{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}" : '';
	}

	/**
	 * MIME type from the file's bytes, not its name.
	 *
	 * @param string $path File.
	 * @return string
	 */
	private static function detect_mime( $path ) {
		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = $finfo ? finfo_file( $finfo, $path ) : false;
			if ( $finfo ) {
				finfo_close( $finfo );
			}
			if ( $mime ) {
				return 'image/heif-sequence' === $mime ? 'image/heif' : (string) $mime;
			}
		}
		$mime = wp_get_image_mime( $path );
		return $mime ? $mime : '';
	}

	/**
	 * Absolute path of a photo size.
	 *
	 * @param array  $meta Photo meta.
	 * @param string $size thumb, medium or full.
	 * @return string
	 */
	private static function path( array $meta, $size ) {
		return self::base_dir() . '/' . $meta['path'] . ( 'full' === $size ? '' : '-' . $size ) . '.jpg';
	}

	/**
	 * @param string $base Path without extension.
	 */
	private static function unlink_set( $base ) {
		foreach ( array( '', '-medium', '-thumb' ) as $suffix ) {
			if ( file_exists( $base . $suffix . '.jpg' ) ) {
				wp_delete_file( $base . $suffix . '.jpg' );
			}
		}
	}

	/**
	 * URL that serves a photo after the login check.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $size thumb, medium or full.
	 * @return string
	 */
	public static function url( $id, $size ) {
		return add_query_arg(
			array(
				'hibi_photo' => (int) $id,
				'size'       => $size,
			),
			home_url( '/' )
		);
	}

	/**
	 * API representation of a photo.
	 *
	 * @param int $id Attachment ID.
	 * @return array|null
	 */
	public static function to_array( $id ) {
		$meta = get_post_meta( $id, self::META, true );
		if ( ! is_array( $meta ) ) {
			return null;
		}
		return array(
			'id'       => (int) $id,
			'width'    => (int) $meta['width'],
			'height'   => (int) $meta['height'],
			'taken_at' => (string) $meta['taken_at'],
			'thumb'    => self::url( $id, 'thumb' ),
			'medium'   => self::url( $id, 'medium' ),
			'full'     => self::url( $id, 'full' ),
		);
	}

	/**
	 * Photos per entry, oldest first.
	 *
	 * @param int[] $entry_ids Entry post IDs.
	 * @return array<int,array[]>
	 */
	public static function for_entries( array $entry_ids ) {
		$entry_ids = array_filter( array_map( 'intval', $entry_ids ) );
		if ( ! $entry_ids ) {
			return array();
		}
		$attachments = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_parent__in'        => $entry_ids,
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_key'               => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Limits to Hibi photos.
				'update_post_term_cache' => false,
			)
		);
		$out = array();
		foreach ( $attachments as $attachment ) {
			$photo = self::to_array( $attachment->ID );
			if ( $photo ) {
				$out[ (int) $attachment->post_parent ][] = $photo;
			}
		}
		return $out;
	}

	/**
	 * The attachment if it is a Hibi photo owned by the user, else null.
	 *
	 * @param int $id      Attachment ID.
	 * @param int $user_id User ID.
	 * @return WP_Post|null
	 */
	public static function owned( $id, $user_id ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'attachment' !== $post->post_type || (int) $post->post_author !== (int) $user_id || ! is_array( get_post_meta( $post->ID, self::META, true ) ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Deletes a photo the user owns.
	 *
	 * @param int $id      Attachment ID.
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id, $user_id ) {
		if ( ! self::owned( $id, $user_id ) ) {
			return new WP_Error( 'hibi_not_found', __( 'Photo not found.', 'hibi-journal' ), array( 'status' => 404 ) );
		}
		wp_delete_attachment( (int) $id, true );
		return true;
	}

	/**
	 * Removes the files when the attachment is deleted by any route.
	 *
	 * @param int $id Attachment ID.
	 */
	public static function delete_files( $id ) {
		$meta = get_post_meta( $id, self::META, true );
		if ( is_array( $meta ) && ! empty( $meta['path'] ) && false === strpos( $meta['path'], '..' ) ) {
			self::unlink_set( self::base_dir() . '/' . $meta['path'] );
		}
	}

	/**
	 * Deleting an entry deletes its photos, instead of leaving them as
	 * unattached files nobody can see.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_entry_photos( $post_id ) {
		if ( Hibi_Entry_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_attachment( $id, true );
		}
	}

	/**
	 * Serves ?hibi_photo=<id>&size=<size> to its owner only.
	 *
	 * This is not a REST route on purpose: an <img> request carries the
	 * login cookie but no REST nonce, and REST treats such a request as
	 * logged out.
	 *
	 * @param WP $wp Request.
	 */
	public static function maybe_serve( $wp ) {
		unset( $wp );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only, owner-checked file download.
		if ( ! isset( $_GET['hibi_photo'] ) ) {
			return;
		}
		$id   = absint( wp_unslash( $_GET['hibi_photo'] ) );
		$size = isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : 'medium';
		// phpcs:enable

		if ( ! in_array( $size, array( 'thumb', 'medium', 'full' ), true ) ) {
			$size = 'medium';
		}

		nocache_headers();
		if ( ! is_user_logged_in() ) {
			status_header( 401 );
			exit;
		}
		$post = self::owned( $id, get_current_user_id() );
		$meta = $post ? get_post_meta( $post->ID, self::META, true ) : null;
		$path = is_array( $meta ) ? self::path( $meta, $size ) : '';
		if ( ! $path || ! is_readable( $path ) ) {
			status_header( 404 );
			exit;
		}

		$etag = '"' . md5( $meta['path'] . $size . filemtime( $path ) ) . '"';
		header_remove( 'Expires' );
		header_remove( 'Pragma' );
		// The file behind an ID never changes, so the owner's browser may keep it.
		header( 'Cache-Control: private, max-age=31536000, immutable' );
		header( 'ETag: ' . $etag );
		header( 'X-Content-Type-Options: nosniff' );

		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) === $etag ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared verbatim, never output.
			status_header( 304 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams the image.
		exit;
	}

	/**
	 * Keeps journal photos out of the media library grid.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function hide_from_media_library( $args ) {
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = array(
			'key'     => self::META,
			'compare' => 'NOT EXISTS',
		);
		return $args;
	}

	/**
	 * Keeps journal photos out of the media library list view.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function hide_from_media_list( $query ) {
		global $pagenow;
		if ( ! is_admin() || 'upload.php' !== $pagenow || ! $query->is_main_query() ) {
			return;
		}
		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'key'     => self::META,
			'compare' => 'NOT EXISTS',
		);
		$query->set( 'meta_query', $meta );
	}
}
