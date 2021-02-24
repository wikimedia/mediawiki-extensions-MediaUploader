<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use HashBagOStuff;
use Language;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use MediaWiki\Extension\MediaUploader\Config\RequestConfig;
use MediaWiki\Languages\LanguageNameUtils;
use WANObjectCache;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\RequestConfig
 */
class RequestConfigTest extends ConfigUnitTestCase {

	/**
	 * TODO: this completely ignores the closure inside getTemplateLanguages
	 *  We probably should test that as well
	 */
	public function testGetConfigArray() {
		$language = $this->createMock( Language::class );
		$language->expects( $this->once() )
			->method( 'getCode' )
			->willReturn( 'testCode' );

		// We have to jump through all these crazy hoops because...
		// WANObjectCache has its getWithSetCallback marked as final and
		// PHPUnit refuses to mock it.
		// TODO: tidy this up once T231419 is resolved
		$wanCache = new class (
			[ 'cache' => new HashBagOStuff() ],
			$this
		) extends WANObjectCache {
			/** @var ConfigUnitTestCase */
			private $testCase;

			public function __construct( array $params, ConfigUnitTestCase $testCase ) {
				parent::__construct( $params );
				$this->testCase = $testCase;
			}

			public function makeKey( $collection, ...$components ) {
				$this->testCase->assertSame(
					'mediauploader',
					$collection,
					'first argument to makeKey() matches'
				);
				$this->testCase->assertArrayEquals(
					[ 'language-templates', 'testCode' ],
					$components,
					true,
					false,
					'components passed to makeKey() match'
				);
				return 'testKey';
			}
		};
		$wanCache->getWithSetCallback(
			'testKey',
			WANObjectCache::TTL_INDEFINITE,
			function () {
				return [ 'test' => 'Test' ];
			},
			[ 'version' => 1 ]
		);

		$dummyResultConfig = [ 'k' => 'v' ];
		$rawConfig = $this->createMock( RawConfig::class );
		$rawConfig->expects( $this->once() )
			->method( 'getConfigWithAdditionalDefaults' )
			->willReturnCallback( function ( $a ) use ( $dummyResultConfig ) {
				$this->assertConfigSubmap(
					// language map placeholder
					[ 'languages' => [ 'test' => 'Test' ] ],
					$a
				);
				return $dummyResultConfig;
			} );

		$rConfig = new RequestConfig(
			$wanCache,
			$this->createNoOpMock( LanguageNameUtils::class ),
			$this->createNoOpMock( LinkBatchFactory::class ),
			$language,
			$rawConfig
		);

		$this->assertArrayEquals(
			$dummyResultConfig,
			$rConfig->getConfigArray(),
			false,
			true,
			'getConfig()'
		);

		$hash = $rConfig->getConfigHash();

		$this->assertIsString( $hash, 'getConfigHash()' );
		$this->assertGreaterThan( 0, strlen( $hash ), 'strlen( getConfigHash() )' );
	}
}
