<?php
/**
 * @package HibiJournal
 */

class Test_Hibi_Photos extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $user_id;

	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create();
		wp_set_current_user( $this->user_id );
	}

	/**
	 * A 40x20 JPEG whose EXIF says: rotate 90° (orientation 6), taken
	 * 2026-10-14 09:30, at latitude 43°39' N.
	 *
	 * @return string Path of a temporary file.
	 */
	public static function jpeg_with_gps() {
		$image = imagecreatetruecolor( 40, 20 );
		imagefilledrectangle( $image, 0, 0, 39, 19, imagecolorallocate( $image, 200, 80, 40 ) );
		ob_start();
		imagejpeg( $image, null, 90 );
		$jpeg = ob_get_clean();

		$entry = static function ( $tag, $type, $count, $value ) {
			return pack( 'vvV', $tag, $type, $count ) . $value;
		};

		// TIFF header, little endian, IFD0 at offset 8.
		$tiff  = 'II' . pack( 'vV', 0x2A, 8 );
		$tiff .= pack( 'v', 3 );
		$tiff .= $entry( 0x0112, 3, 1, pack( 'vv', 6, 0 ) );   // Orientation.
		$tiff .= $entry( 0x8769, 4, 1, pack( 'V', 50 ) );      // Exif IFD.
		$tiff .= $entry( 0x8825, 4, 1, pack( 'V', 88 ) );      // GPS IFD.
		$tiff .= pack( 'V', 0 );
		// Exif IFD at 50.
		$tiff .= pack( 'v', 1 );
		$tiff .= $entry( 0x9003, 2, 20, pack( 'V', 68 ) );     // DateTimeOriginal.
		$tiff .= pack( 'V', 0 );
		$tiff .= "2026:10:14 09:30:00\0";
		// GPS IFD at 88.
		$tiff .= pack( 'v', 2 );
		$tiff .= $entry( 0x0001, 2, 2, "N\0\0\0" );            // GPSLatitudeRef.
		$tiff .= $entry( 0x0002, 5, 3, pack( 'V', 118 ) );     // GPSLatitude.
		$tiff .= pack( 'V', 0 );
		$tiff .= pack( 'VVVVVV', 43, 1, 39, 1, 0, 1 );

		$payload = "Exif\0\0" . $tiff;
		$app1    = "\xFF\xE1" . pack( 'n', strlen( $payload ) + 2 ) . $payload;

		$path = wp_tempnam( 'hibi-gps.jpg' );
		file_put_contents( $path, substr( $jpeg, 0, 2 ) . $app1 . substr( $jpeg, 2 ) );
		return $path;
	}

	private function file_of( $photo, $size = 'full' ) {
		$meta = get_post_meta( $photo['id'], Hibi_Photos::META, true );
		return Hibi_Photos::base_dir() . '/' . $meta['path'] . ( 'full' === $size ? '' : '-' . $size ) . '.jpg';
	}

	public function test_fixture_really_has_gps() {
		$exif = exif_read_data( self::jpeg_with_gps() );
		$this->assertSame( 'N', $exif['GPSLatitudeRef'] );
		$this->assertSame( 6, $exif['Orientation'] );
	}

	public function test_store_strips_gps_rotates_and_keeps_taken_at() {
		$photo = Hibi_Photos::store( $this->user_id, '2026-10-14', self::jpeg_with_gps(), 'IMG_0001.jpg' );

		$this->assertIsArray( $photo );
		$this->assertSame( 20, $photo['width'], 'Orientation 6 turns 40x20 into 20x40.' );
		$this->assertSame( 40, $photo['height'] );
		$this->assertSame( '2026-10-14T09:30:00', $photo['taken_at'] );

		foreach ( array( 'full', 'medium', 'thumb' ) as $size ) {
			$path = $this->file_of( $photo, $size );
			$this->assertFileExists( $path );
			$this->assertSame( 'image/jpeg', wp_get_image_mime( $path ) );
			$exif = @exif_read_data( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->assertFalse( is_array( $exif ) && isset( $exif['GPSLatitude'] ), "GPS must be gone from {$size}." );
			$this->assertFalse( is_array( $exif ) && isset( $exif['GPSLatitudeRef'] ) );
		}

		$this->assertStringContainsString( '/' . Hibi_Photos::DIR . '/' . $this->user_id . '/2026/10/', $this->file_of( $photo ) );
	}

	public function test_private_folder_is_closed() {
		Hibi_Photos::ensure_base_dir();
		$htaccess = file_get_contents( Hibi_Photos::base_dir() . '/.htaccess' );
		$this->assertStringContainsString( 'Require all denied', $htaccess );
		$this->assertStringContainsString( 'Deny from all', $htaccess );
		$this->assertFileExists( Hibi_Photos::base_dir() . '/index.php' );
	}

	public function test_large_photo_is_capped() {
		$image = imagecreatetruecolor( 3000, 1000 );
		$path  = wp_tempnam( 'hibi-big.png' );
		imagepng( $image, $path );

		$photo = Hibi_Photos::store( $this->user_id, '2026-10-14', $path, 'big.png' );
		$this->assertSame( 2560, $photo['width'] );
		$size = getimagesize( $this->file_of( $photo, 'thumb' ) );
		$this->assertSame( 400, $size[0] );
		$size = getimagesize( $this->file_of( $photo, 'medium' ) );
		$this->assertSame( 1200, $size[0] );
	}

	public function test_rejects_non_images() {
		$path = wp_tempnam( 'hibi-text.jpg' );
		file_put_contents( $path, '<?php echo "no";' );
		$result = Hibi_Photos::store( $this->user_id, '2026-10-14', $path );
		$this->assertWPError( $result );
		$this->assertSame( 'hibi_photo_type', $result->get_error_code() );
	}

	public function test_photo_creates_entry_without_breaking_versions() {
		Hibi_Photos::store( $this->user_id, '2026-10-14', self::jpeg_with_gps() );

		$entry = Hibi_Entries::get( $this->user_id, '2026-10-14' );
		$this->assertTrue( $entry['exists'] );
		$this->assertSame( 0, $entry['version'] );
		$this->assertCount( 1, $entry['photos'] );

		$saved = Hibi_Entries::save(
			$this->user_id,
			'2026-10-14',
			array(
				'base_version' => 0,
				'content'      => 'text after photo',
			)
		);
		$this->assertSame( 1, $saved['version'] );
		$this->assertCount( 1, $saved['photos'] );
	}

	public function test_only_owner_can_delete() {
		$photo = Hibi_Photos::store( $this->user_id, '2026-10-14', self::jpeg_with_gps() );
		$path  = $this->file_of( $photo );

		$other = self::factory()->user->create();
		$this->assertWPError( Hibi_Photos::delete( $photo['id'], $other ) );
		$this->assertFileExists( $path );

		$this->assertTrue( Hibi_Photos::delete( $photo['id'], $this->user_id ) );
		$this->assertFileDoesNotExist( $path );
		$this->assertFileDoesNotExist( substr( $path, 0, -4 ) . '-thumb.jpg' );
	}

	public function test_deleting_entry_deletes_its_photos() {
		$photo = Hibi_Photos::store( $this->user_id, '2026-10-14', self::jpeg_with_gps() );
		$path  = $this->file_of( $photo );

		wp_delete_post( Hibi_Entries::find( $this->user_id, '2026-10-14' )->ID, true );

		$this->assertNull( get_post( $photo['id'] ) );
		$this->assertFileDoesNotExist( $path );
	}

	public function test_photo_urls_are_not_file_urls() {
		$photo = Hibi_Photos::store( $this->user_id, '2026-10-14', self::jpeg_with_gps() );
		$this->assertStringContainsString( 'hibi_photo=' . $photo['id'], $photo['thumb'] );
		$this->assertStringNotContainsString( Hibi_Photos::DIR, $photo['full'] );
	}

	public function test_hidden_from_media_library() {
		$args = Hibi_Photos::hide_from_media_library( array() );
		$this->assertSame( 'NOT EXISTS', $args['meta_query'][0]['compare'] );
	}
}
