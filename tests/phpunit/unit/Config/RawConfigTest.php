<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Config;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MediaUploader\Config\RawConfig;
use UploadBase;

/**
 * @group Upload
 * @covers \MediaWiki\Extension\MediaUploader\Config\RawConfig
 */
class RawConfigTest extends ConfigUnitTestCase {

	public function provideConfig(): iterable {
		yield 'no overrides' => [
			[
				'CheckFileExtensions' => true,
				'FileExtensions' => [ 'dummy' ],
				'MediaUploaderConfig' => [
					'someKey' => 'value'
				],
				'UploadWizardConfig' => [],
				'PersistDuringRequest' => false,
				'FileMaxUploadSize' => 1000,
			],
			[
				'fileExtensions' => [ 'dummy' ],
				'maxPhpUploadSize' => UploadBase::getMaxPhpUploadSize(),
				'maxMwUploadSize' => 1000,
				'someKey' => 'value',
				'chunkSize' => 5 * 1024 * 1024, // default setting
			]
		];

		yield 'with overrides' => [
			[
				'CheckFileExtensions' => true,
				'FileExtensions' => [ 'dummy' ],
				'MediaUploaderConfig' => [
					'someKey' => 'value',
					'maxMwUploadSize' => 123,
					'chunkSize' => 1024 * 1024,
				],
				'UploadWizardConfig' => [],
				'PersistDuringRequest' => false,
				'FileMaxUploadSize' => 1000,
			],
			[
				'fileExtensions' => [ 'dummy' ],
				'maxPhpUploadSize' => UploadBase::getMaxPhpUploadSize(),
				'maxMwUploadSize' => 123,
				'someKey' => 'value',
				'chunkSize' => 1024 * 1024,
			]
		];

		yield 'with legacy overrides' => [
			[
				'CheckFileExtensions' => true,
				'FileExtensions' => [ 'dummy' ],
				'MediaUploaderConfig' => [],
				'UploadWizardConfig' => [
					'maxMwUploadSize' => 123,
					'chunkSize' => 1024,
					'key2' => 'value',
				],
				'PersistDuringRequest' => false,
				'FileMaxUploadSize' => 1000,
			],
			[
				'fileExtensions' => [ 'dummy' ],
				'maxPhpUploadSize' => UploadBase::getMaxPhpUploadSize(),
				'maxMwUploadSize' => 123,
				'chunkSize' => 1024,
				'key2' => 'value',
			]
		];

		yield 'with mixed overrides' => [
			[
				'CheckFileExtensions' => true,
				'FileExtensions' => [ 'dummy' ],
				'MediaUploaderConfig' => [
					'someKey' => 'value',
					'maxMwUploadSize' => 123,
					'chunkSize' => 1024 * 1024,
				],
				'UploadWizardConfig' => [
					'maxMwUploadSize' => 321,
					'chunkSize' => 1024,
					'key2' => 'value',
				],
				'PersistDuringRequest' => false,
				'FileMaxUploadSize' => 1000,
			],
			[
				'fileExtensions' => [ 'dummy' ],
				'maxPhpUploadSize' => UploadBase::getMaxPhpUploadSize(),
				'maxMwUploadSize' => 123,
				'chunkSize' => 1024 * 1024,
				'someKey' => 'value',
			]
		];
	}

	/**
	 * @param array $options
	 * @param array $expectedSubmap
	 * @dataProvider provideConfig
	 */
	public function testGetConfigArray( array $options, array $expectedSubmap ) {
		$options = new ServiceOptions(
			RawConfig::CONSTRUCTOR_OPTIONS,
			$options
		);

		$rawConfig = new RawConfig( $options );

		$this->assertConfigSubmap(
			$expectedSubmap,
			$rawConfig->getConfigArray()
		);
	}

	public function testGetConfigArrayWithAdditionalDefaults() {
		$options = new ServiceOptions(
			RawConfig::CONSTRUCTOR_OPTIONS,
			[
				'CheckFileExtensions' => true,
				'FileExtensions' => [ 'dummy' ],
				'MediaUploaderConfig' => [
					'someKey' => 'value',
					'chunkSize' => 1024 * 1024,
				],
				'UploadWizardConfig' => [],
				'PersistDuringRequest' => false,
				'FileMaxUploadSize' => 1000,
			]
		);

		$additionalDefaults = [
			'default1' => 'default',
			'someKey' => 'default',
			'altUploadForm' => 'default',
		];

		$rawConfig = new RawConfig( $options );

		$this->assertConfigSubmap(
			[
				'fileExtensions' => [ 'dummy' ],
				'maxPhpUploadSize' => UploadBase::getMaxPhpUploadSize(),
				'maxMwUploadSize' => 1000,
				'someKey' => 'value',
				'chunkSize' => 1024 * 1024,
				'default1' => 'default',
				'altUploadForm' => 'default',
			],
			$rawConfig->getConfigWithAdditionalDefaults( $additionalDefaults )
		);
	}
}
