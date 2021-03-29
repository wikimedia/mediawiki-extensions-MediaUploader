<?php

namespace MediaWiki\Extension\MediaUploader\Tests\Unit\Campaign;

use MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignContentException;
use MediaWikiUnitTestCase;

/**
 * @ingroup Upload
 * @covers \MediaWiki\Extension\MediaUploader\Campaign\InvalidCampaignContentException
 */
class InvalidCampaignContentExceptionTest extends MediaWikiUnitTestCase {

	public function testException() {
		$campaignName = 'Some campaign';

		$exception = new InvalidCampaignContentException( $campaignName );

		$this->assertSame(
			$campaignName,
			$exception->getCampaignName(),
			'getCampaignName()'
		);

		$this->assertStringContainsString(
			$campaignName,
			$exception->getMessage(),
			'getMessage() contains campaign name'
		);
	}
}
