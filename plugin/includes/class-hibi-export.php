<?php
/**
 * Export and import of a user's whole journal.
 *
 * The ZIP holds entries.json (everything, versioned), one Markdown file per
 * day under markdown/YYYY/ with a YAML header, and photos/ — readable by
 * any other tool in decades, without Hibi.
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

class Hibi_Export {

	const SCHEMA_VERSION = 1;

	/**
	 * Largest import accepted, in bytes.
	 */
	const MAX_IMPORT_BYTES = 512 * MB_IN_BYTES;

	/**
	 * A temporary file name. wp_tempnam() lives in an admin include that
	 * REST and front-end requests do not load.
	 *
	 * @param string $name Base name.
	 * @return string
	 */
	private static function temp_file( $name ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return wp_tempnam( $name );
	}

	/**
	 * Every entry of a user, oldest first.
	 *
	 * @param int $user_id User ID.
	 * @return array[]
	 */
	public static function entries( $user_id ) {
		$ids = get_posts(
			array(
				'post_type'      => Hibi_Entry_Type::POST_TYPE,
				'post_status'    => 'private',
				'author'         => (int) $user_id,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_hibi_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Ordering by journal date.
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = Hibi_Entries::to_array( get_post( $id ) );
		}
		return $out;
	}

	/**
	 * Builds the export ZIP in a temporary file.
	 *
	 * @param int $user_id User ID.
	 * @return string|WP_Error Path of the ZIP. The caller deletes it.
	 */
	public static function build_zip( $user_id ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'hibi_export_zip', __( 'Exporting needs the PHP Zip extension.', 'hibi-journal' ), array( 'status' => 501 ) );
		}
		$path = self::temp_file( 'hibi-export.zip' );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'hibi_export_zip', __( 'The export file could not be created.', 'hibi-journal' ), array( 'status' => 500 ) );
		}

		$records = array();
		foreach ( self::entries( $user_id ) as $entry ) {
			$photos = array();
			foreach ( $entry['photos'] as $photo ) {
				$file = self::photo_file( $photo['id'] );
				if ( ! $file ) {
					continue;
				}
				$name = sprintf( 'photos/%s-%d.jpg', $entry['date'], $photo['id'] );
				$zip->addFile( $file, $name );
				$photos[] = array(
					'file'     => $name,
					'taken_at' => $photo['taken_at'],
				);
			}

			$record    = array(
				'date'       => $entry['date'],
				'content'    => $entry['content'],
				'mood'       => $entry['mood'],
				'tags'       => $entry['tags'],
				'weather'    => $entry['weather'],
				'location'   => $entry['location'],
				'photos'     => $photos,
				'updated_at' => $entry['updated_at'],
			);
			$records[] = $record;

			$zip->addFromString( sprintf( 'markdown/%s/%s.md', substr( $entry['date'], 0, 4 ), $entry['date'] ), self::markdown( $record ) );
		}

		$zip->addFromString(
			'entries.json',
			wp_json_encode(
				array(
					'schema_version' => self::SCHEMA_VERSION,
					'generator'      => 'Hibi Journal ' . HIBI_VERSION,
					'exported_at'    => gmdate( 'c' ),
					'timezone'       => wp_timezone_string(),
					'entries'        => $records,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
		$zip->close();
		return $path;
	}

	/**
	 * One day as Markdown with a YAML header.
	 *
	 * @param array $record Export record.
	 * @return string
	 */
	public static function markdown( array $record ) {
		$yaml = array( '---', 'date: ' . $record['date'] );
		if ( null !== $record['mood'] ) {
			$yaml[] = 'mood: ' . (int) $record['mood'];
		}
		if ( $record['tags'] ) {
			$yaml[] = 'tags: [' . implode( ', ', array_map( array( __CLASS__, 'yaml_string' ), $record['tags'] ) ) . ']';
		}
		if ( $record['weather'] ) {
			$w      = $record['weather'];
			$yaml[] = 'weather: { code: ' . (int) $w['weather_code'] . ', max: ' . ( null === $w['temp_max'] ? 'null' : $w['temp_max'] ) . ', min: ' . ( null === $w['temp_min'] ? 'null' : $w['temp_min'] ) . ', label: ' . self::yaml_string( (string) $w['label'] ) . ' }';
		}
		$yaml[] = '---';

		$body = rtrim( $record['content'] );
		foreach ( $record['photos'] as $photo ) {
			$body .= "\n\n![](../../" . $photo['file'] . ')';
		}
		return implode( "\n", $yaml ) . "\n\n" . $body . "\n";
	}

	/**
	 * @param string $value Text.
	 * @return string Double-quoted YAML string.
	 */
	private static function yaml_string( $value ) {
		return '"' . str_replace( array( '\\', '"', "\n" ), array( '\\\\', '\\"', '\\n' ), $value ) . '"';
	}

	/**
	 * Path of a photo's full-size file, if it exists.
	 *
	 * @param int $id Attachment ID.
	 * @return string|null
	 */
	private static function photo_file( $id ) {
		$meta = get_post_meta( $id, Hibi_Photos::META, true );
		if ( ! is_array( $meta ) || empty( $meta['path'] ) ) {
			return null;
		}
		$path = Hibi_Photos::base_dir() . '/' . $meta['path'] . '.jpg';
		return is_readable( $path ) ? $path : null;
	}

	/**
	 * Imports a Hibi ZIP or entries.json. Days that already have an entry
	 * are skipped, so importing twice changes nothing.
	 *
	 * @param int    $user_id User ID.
	 * @param string $path    Uploaded file.
	 * @return array{imported:int,skipped:int,photos:int,errors:string[]}|WP_Error
	 */
	public static function import( $user_id, $path ) {
		if ( ! is_readable( $path ) || filesize( $path ) > self::MAX_IMPORT_BYTES ) {
			return new WP_Error( 'hibi_import_file', __( 'The import file is missing or too large.', 'hibi-journal' ), array( 'status' => 400 ) );
		}

		$zip  = null;
		$head = (string) file_get_contents( $path, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the file signature of a local upload.
		if ( "PK\x03\x04" === $head ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return new WP_Error( 'hibi_import_zip', __( 'Importing a ZIP needs the PHP Zip extension.', 'hibi-journal' ), array( 'status' => 501 ) );
			}
			$zip = new ZipArchive();
			if ( true !== $zip->open( $path, ZipArchive::RDONLY ) ) {
				return new WP_Error( 'hibi_import_zip', __( 'The ZIP file could not be opened.', 'hibi-journal' ), array( 'status' => 400 ) );
			}
			$json = $zip->getFromName( 'entries.json' );
		} else {
			$json = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload.
		}

		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['schema_version'], $data['entries'] ) || ! is_array( $data['entries'] ) ) {
			if ( $zip ) {
				$zip->close();
			}
			return new WP_Error( 'hibi_import_format', __( 'This is not a Hibi export file.', 'hibi-journal' ), array( 'status' => 400 ) );
		}
		if ( (int) $data['schema_version'] > self::SCHEMA_VERSION ) {
			if ( $zip ) {
				$zip->close();
			}
			return new WP_Error( 'hibi_import_version', __( 'This file comes from a newer version of Hibi Journal. Please update the plugin first.', 'hibi-journal' ), array( 'status' => 400 ) );
		}

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'photos'   => 0,
			'errors'   => array(),
		);

		foreach ( $data['entries'] as $record ) {
			$date = isset( $record['date'] ) ? (string) $record['date'] : '';
			if ( ! Hibi_Day::is_valid( $date ) ) {
				$result['errors'][] = sprintf( 'Invalid date: %s', sanitize_text_field( $date ) );
				continue;
			}
			$current = Hibi_Entries::get( $user_id, $date );
			if ( $current['exists'] && ( $current['char_count'] > 0 || $current['photos'] || null !== $current['mood'] ) ) {
				++$result['skipped'];
				continue;
			}

			$saved = Hibi_Entries::save(
				$user_id,
				$date,
				array(
					'base_version' => $current['version'],
					'content'      => isset( $record['content'] ) ? (string) $record['content'] : '',
					'mood'         => isset( $record['mood'] ) ? $record['mood'] : null,
					'tags'         => isset( $record['tags'] ) && is_array( $record['tags'] ) ? $record['tags'] : array(),
					'weather'      => isset( $record['weather'] ) ? $record['weather'] : null,
					'location'     => isset( $record['location'] ) ? $record['location'] : null,
				)
			);
			if ( is_wp_error( $saved ) ) {
				$result['errors'][] = $date . ': ' . $saved->get_error_message();
				continue;
			}
			++$result['imported'];

			if ( $zip && ! empty( $record['photos'] ) && is_array( $record['photos'] ) ) {
				foreach ( $record['photos'] as $photo ) {
					$name = isset( $photo['file'] ) ? (string) $photo['file'] : '';
					// Only plain names inside photos/: nothing is ever extracted by path.
					if ( ! preg_match( '#^photos/[A-Za-z0-9._-]+\.(jpe?g|png|webp|heic)$#i', $name ) ) {
						continue;
					}
					$bytes = $zip->getFromName( $name );
					if ( false === $bytes ) {
						continue;
					}
					$tmp = self::temp_file( 'hibi-import-photo' );
					file_put_contents( $tmp, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary file in the system temp directory.
					$stored = Hibi_Photos::store( $user_id, $date, $tmp, basename( $name ) );
					wp_delete_file( $tmp );
					if ( is_wp_error( $stored ) ) {
						$result['errors'][] = $date . ': ' . $stored->get_error_message();
					} else {
						++$result['photos'];
					}
				}
			}
		}

		if ( $zip ) {
			$zip->close();
		}
		return $result;
	}
}
