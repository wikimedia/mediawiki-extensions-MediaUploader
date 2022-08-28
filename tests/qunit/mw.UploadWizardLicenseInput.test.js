QUnit.module( 'ext.uploadWizardLicenseInput', QUnit.newMwEnvironment( {
	beforeEach: function () {
		mw.UploadWizard.config = {
			licenses: {
				'cc-by-sa-3.0': {
					msg: 'mediauploader-license-cc-by-sa-3.0',
					icons: [ 'cc-by', 'cc-sa' ],
					url: '//creativecommons.org/licenses/by-sa/3.0/',
					languageCodePrefix: 'deed.'
				}
			}
		};
	}
} ) );

QUnit.test( 'Smoke test', function ( assert ) {
	var config = { type: 'radio', licenses: [] },
		$fixture = $( '<div>' ),
		uwLicenseInput;

	uwLicenseInput = new mw.UploadWizardLicenseInput( config );
	$fixture.append( uwLicenseInput.$element );
	assert.ok( uwLicenseInput, 'LicenseInput object created !' );
} );

QUnit.test( 'createInputs()', function ( assert ) {
	var config = { type: 'radio', licenses: [ 'cc-by-sa-3.0' ] },
		$fixture = $( '<div>' ),
		uwLicenseInput,
		$input,
		$label;

	uwLicenseInput = new mw.UploadWizardLicenseInput( config );
	$fixture.append( uwLicenseInput.$element );

	// Check radio button is there
	$input = $fixture.find( '.oo-ui-radioInputWidget .oo-ui-inputWidget-input[value="cc-by-sa-3.0"]' );
	assert.strictEqual( $input.length, 1, 'Radio button created.' );

	// Check label is there
	$label = $input.closest( '.oo-ui-radioOptionWidget' ).find( '.oo-ui-labelElement-label' );
	assert.strictEqual( $label.length, 1, 'Label created.' );
} );

QUnit.test( 'createGroupedInputs()', function ( assert ) {
	var config = {
			type: 'checkbox',
			licenseGroups: [
				{
					head: 'mediauploader-license-cc-head',
					subhead: 'mediauploader-license-cc-subhead',
					licenses: [ 'cc-by-sa-3.0' ]
				}
			]
		},
		$fixture = $( '<div>' ),
		uwLicenseInput;

	uwLicenseInput = new mw.UploadWizardLicenseInput( config );
	$fixture.append( uwLicenseInput.$element );

	// Check license group is there
	assert.strictEqual( $fixture.find( '.mediauploader-deed-license-group' ).length, 1, 'License group created.' );

	// Check subheader is there
	assert.strictEqual( $fixture.find( '.mediauploader-deed-license-group-subhead' ).length, 1, 'License subheader created.' );

	// Check license is there
	assert.strictEqual( $fixture.find( '.mediauploader-deed-license-group .oo-ui-fieldsetLayout-group' ).length, 1, 'License created.' );
} );
