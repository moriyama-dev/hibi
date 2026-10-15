<?php
/**
 * @package HibiJournal
 */

require_once __DIR__ . '/test-photos.php';

class Test_Hibi_Export extends WP_UnitTestCase {

	public function test_round_trip_to_another_user() {
		$from = self::factory()->user->create();
		$to   = self::factory()->user->create();
		Hibi_Entries::save(
			$from,
			'2026-10-01',
			array(
				'base_version' => 0,
				'content'      => "# 見出し\n本文 \"引用\"",
				'mood'         => 4,
				'tags'         => array( '家族', '散歩' ),
			)
		);
		Hibi_Entries::save(
			$from,
			'2026-10-02',
			array(
				'base_version' => 0,
				'content'      => '二日目',
			)
		);
		Hibi_Photos::store( $from, '2026-10-02', Test_Hibi_Photos::jpeg_with_gps() );

		$zip = Hibi_Export::build_zip( $from );
		$this->assertFileExists( $zip );

		$result = Hibi_Export::import( $to, $zip );
		$this->assertSame( 2, $result['imported'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertSame( 1, $result['photos'] );

		$one = Hibi_Entries::get( $to, '2026-10-01' );
		$this->assertSame( "# 見出し\n本文 \"引用\"", $one['content'] );
		$this->assertSame( 4, $one['mood'] );
		$this->assertEqualSets( array( '家族', '散歩' ), $one['tags'] );
		$this->assertCount( 1, Hibi_Entries::get( $to, '2026-10-02' )['photos'] );

		$again = Hibi_Export::import( $to, $zip );
		$this->assertSame( 0, $again['imported'], 'Importing twice changes nothing.' );
		$this->assertSame( 2, $again['skipped'] );
		unlink( $zip );
	}

	public function test_markdown_has_yaml_header() {
		$md = Hibi_Export::markdown(
			array(
				'date'    => '2026-10-01',
				'content' => '本文',
				'mood'    => 3,
				'tags'    => array( 'a"b' ),
				'weather' => null,
				'photos'  => array( array( 'file' => 'photos/2026-10-01-5.jpg' ) ),
			)
		);
		$this->assertStringStartsWith( "---\ndate: 2026-10-01\nmood: 3\ntags: [\"a\\\"b\"]\n---\n\n本文", $md );
		$this->assertStringContainsString( '![](../../photos/2026-10-01-5.jpg)', $md );
	}

	public function test_zip_paths_cannot_escape() {
		$user = self::factory()->user->create();
		$zip  = new ZipArchive();
		$path = wp_tempnam( 'evil.zip' );
		$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( '../../evil.jpg', file_get_contents( Test_Hibi_Photos::jpeg_with_gps() ) );
		$zip->addFromString(
			'entries.json',
			wp_json_encode(
				array(
					'schema_version' => 1,
					'entries'        => array(
						array(
							'date'    => '2026-10-01',
							'content' => 'x',
							'photos'  => array( array( 'file' => '../../evil.jpg' ), array( 'file' => 'photos/../../evil.jpg' ) ),
						),
					),
				)
			)
		);
		$zip->close();

		$result = Hibi_Export::import( $user, $path );
		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( 0, $result['photos'] );
		$this->assertFileDoesNotExist( dirname( $path, 2 ) . '/evil.jpg' );
		unlink( $path );
	}

	public function test_rejects_other_files_and_newer_versions() {
		$user = self::factory()->user->create();

		$path = wp_tempnam( 'x.json' );
		file_put_contents( $path, '{"hello":"world"}' );
		$this->assertSame( 'hibi_import_format', Hibi_Export::import( $user, $path )->get_error_code() );

		file_put_contents(
			$path,
			wp_json_encode(
				array(
					'schema_version' => 99,
					'entries'        => array(),
				)
			)
		);
		$this->assertSame( 'hibi_import_version', Hibi_Export::import( $user, $path )->get_error_code() );
		unlink( $path );
	}
}
