<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Integration\Api;

use ApiTestCase;
use MediaWiki\Extension\MediaUploader\MediaUploaderServices;

/**
 * @group Upload
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\MediaUploader\Api\QueryAllCampaigns
 */
class QueryAllCampaignsTest extends ApiTestCase {

	public function testAllCampaigns() {
		// valid, enabled
		$idA = $this->editPage( 'Campaign:A', 'enabled: true' )
			->value['revision-record']->getPageId();

		// valid, disabled
		$idB = $this->editPage( 'Campaign:B', 'enabled: false' )
			->value['revision-record']->getPageId();

		// invalid schema
		$idC = $this->editPage(
			'C',
			'enbld: true',
			'',
			NS_CAMPAIGN,
			MediaUploaderServices::getSystemUser()
		)->value['revision-record']->getPageId();

		// Query without filtering
		$result = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'allcampaigns',
		] );
		$data = $result[0]['query']['allcampaigns'];
		$this->assertCount( 3, $data, 'number of returned campaign records' );
		$this->assertCampaign( $data, $idA, 'A', true );
		$this->assertCampaign( $data, $idB, 'B', false );
		$this->assertCampaignError( $data, $idC, 'C' );

		// Enabled campaigns only
		$result = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'allcampaigns',
			'mucenabledonly' => true,
		] );
		$data = $result[0]['query']['allcampaigns'];
		$this->assertCount( 1, $data, 'number of returned campaign records' );
		$this->assertCampaign( $data, $idA, 'A', true );
	}

	private function assertCampaign( array $data, int $id, string $name, bool $enabled ): void {
		$this->assertArrayHasKey( $id, $data, 'campaign present in result' );
		$this->assertArrayEquals(
			[
				'name' => $name,
				'enabled' => $enabled,
				'trackingCategory' => "Uploaded_via_Campaign:$name",
				'totalUploads' => 0,
				'totalContributors' => 0,
			],
			$data[$id],
			false,
			true,
			'campaign record'
		);
	}

	private function assertCampaignError( array $data, int $id, string $name ): void {
		$this->assertArrayHasKey( $id, $data, 'campaign present in result' );
		$this->assertArraySubmapSame(
			[
				'name' => $name,
				'enabled' => false,
			],
			$data[$id],
			'campaign record'
		);
		$this->assertArrayHasKey( 'error', $data[$id], 'campaign record' );
	}
}
