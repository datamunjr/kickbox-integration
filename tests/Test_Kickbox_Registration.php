<?php
/**
 * Unit tests for Kickbox Integration Registration
 *
 * @package Kickbox_Integration
 */

class Test_Kickbox_Registration extends WP_UnitTestCase {

	/**
	 * Test that registration class exists
	 */
	public function test_registration_class_exists() {
		$this->assertTrue( class_exists( 'Kickbox_Integration_Registration' ) );
	}

	/**
	 * Test that registration validation hook is registered
	 */
	public function test_registration_validation_hook_registered() {
		$kickbox = KICKBOX();

		$this->assertNotNull( $kickbox->registration, 'Registration component should be initialized' );

		// Use has_filter to check if registration validation is hooked
		$priority = has_filter( 'woocommerce_process_registration_errors', array(
			$kickbox->registration,
			'validate_registration_email'
		) );

		$this->assertNotFalse( $priority, 'validate_registration_email should be registered on woocommerce_process_registration_errors filter' );
		$this->assertEquals( 10, $priority, 'validate_registration_email should be registered at priority 10' );
	}

	/**
	 * Test validate_registration_email with allow list scenarios
	 *
	 * @dataProvider provideAllowListScenarios
	 */
	public function test_validate_registration_email_allow_list(
		$email,
		$is_in_allow_list,
		$should_call_kickbox
	) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true (registration verification is enabled)
		$verification_mock->method( 'is_verification_enabled' )
		                  ->willReturn( true );

		// Set up spy for verify_email - should only be called if email is NOT in allow list
		if ( $should_call_kickbox ) {
			$verification_mock->expects( $this->once() )
			                  ->method( 'verify_email' )
			                  ->with(
				                  $this->equalTo( $email ),
				                  $this->equalTo( array( 'origin' => 'registration' ) )
			                  )
			                  ->willReturn( array( 'result' => 'deliverable' ) );
		} else {
			$verification_mock->expects( $this->never() )
			                  ->method( 'verify_email' );
		}

		// mock get_allow_list
		$registration = $this->getMockBuilder( Kickbox_Integration_Registration::class )
		                     ->onlyMethods( array( 'get_allow_list' ) )
		                     ->getMock();

		// Mock the allow list
		$allow_list = $is_in_allow_list ? array( $email ) : array();
		$registration->method( 'get_allow_list' )
		             ->willReturn( $allow_list );

		// Mock the verification property (Kickbox_Integration_Verification)
		// Use reflection to access and set the private verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Registration::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $registration, $verification_mock );

		// Call validate_registration_email
		$result = $registration->validate_registration_email(
			new WP_Error(),
			'testuser',
			'password123',
			$email
		);

		// Verify result is a WP_Error object
		$this->assertInstanceOf( 'WP_Error', $result );

		// If email is in allow list, no errors should be added (Kickbox not called)
		// If email is not in allow list, Kickbox is called and result determines errors
		if ( $is_in_allow_list ) {
			$this->assertEmpty( $result->get_error_codes(), 'No errors should be added for allowed emails' );
		}
	}

	/**
	 * Data provider for allow list scenarios
	 *
	 * @return array Test scenarios [email, is_in_allow_list, should_call_kickbox]
	 */
	public function provideAllowListScenarios() {
		return array(
			'email_in_allow_list'     => array(
				'allowed@example.com',
				true,
				false  // Should NOT call Kickbox
			),
			'email_not_in_allow_list' => array(
				'test@example.com',
				false,
				true   // Should call Kickbox
			),
		);
	}

	/**
	 * Test that verify_email is not called when registration verification is disabled
	 */
	public function test_validate_registration_email_when_verification_disabled() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return false (registration verification is DISABLED)
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'registration' )
		                  ->willReturn( false );

		// SPY: verify_email should NEVER be called when verification is disabled
		$verification_mock->expects( $this->never() )
		                  ->method( 'verify_email' );

		// Create registration instance
		$registration = new Kickbox_Integration_Registration();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Registration::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $registration, $verification_mock );

		// Call validate_registration_email
		$validation_error = new WP_Error();
		$result           = $registration->validate_registration_email(
			$validation_error,
			'testuser',
			'password123',
			'test@example.com'
		);

		// Verify result is a WP_Error object with no errors added
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEmpty( $result->get_error_codes(), 'No errors should be added when verification is disabled' );
	}

	/**
	 * Test that verify_email is not called when email is empty or invalid format
	 *
	 * @dataProvider provideInvalidEmailScenarios
	 */
	public function test_validate_registration_email_with_invalid_email( $email, $scenario_description ) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true (verification is enabled)
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'registration' )
		                  ->willReturn( true );

		// SPY: verify_email should NEVER be called for invalid/empty emails
		$verification_mock->expects( $this->never() )
		                  ->method( 'verify_email' );

		// Create registration instance
		$registration = new Kickbox_Integration_Registration();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Registration::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $registration, $verification_mock );

		// Call validate_registration_email
		$validation_error = new WP_Error();
		$result           = $registration->validate_registration_email(
			$validation_error,
			'testuser',
			'password123',
			$email
		);

		// Verify result is a WP_Error object with no errors added
		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEmpty( $result->get_error_codes(), "No errors should be added for $scenario_description" );
	}

	/**
	 * Data provider for invalid email scenarios
	 *
	 * @return array Test scenarios [email, scenario_description]
	 */
	public function provideInvalidEmailScenarios() {
		return array(
			'empty_email'              => array( '', 'empty email' ),
			'invalid_format_no_at'     => array( 'notanemail', 'email without @ symbol' ),
			'invalid_format_no_domain' => array( 'test@', 'email without domain' ),
			'invalid_format_spaces'    => array( 'test @example.com', 'email with spaces' ),
			'invalid_format_no_local'  => array( '@example.com', 'email without local part' ),
		);
	}

	/**
	 * Test validate_registration_email with WP_Error vs valid result from verify_email
	 *
	 * @dataProvider provideVerifyEmailResultScenarios
	 */
	public function test_validate_registration_email_with_verify_result(
		$verify_result,
		$should_add_error,
		$scenario_description
	) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array(
			                          'is_verification_enabled',
			                          'verify_email',
			                          'get_action_for_result'
		                          ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'registration' )
		                  ->willReturn( true );

		// Mock verify_email to return our test result
		$verification_mock->method( 'verify_email' )
		                  ->willReturn( $verify_result );

		// If verify_result is not WP_Error and action is 'block', mock get_action_for_result
		if ( ! is_wp_error( $verify_result ) && $should_add_error ) {
			$verification_mock->method( 'get_action_for_result' )
			                  ->willReturn( 'block' );
		} elseif ( ! is_wp_error( $verify_result ) && ! $should_add_error ) {
			$verification_mock->method( 'get_action_for_result' )
			                  ->willReturn( 'allow' );
		}

		// Create registration mock to spy on add_registration_error
		$registration = $this->getMockBuilder( Kickbox_Integration_Registration::class )
		                     ->onlyMethods( array( 'get_allow_list', 'add_registration_error' ) )
		                     ->getMock();

		// Mock get_allow_list to return empty array (not in allow list)
		$registration->method( 'get_allow_list' )
		             ->willReturn( array() );

		// SPY on add_registration_error
		if ( $should_add_error ) {
			$registration->expects( $this->once() )
			             ->method( 'add_registration_error' );
		} else {
			$registration->expects( $this->never() )
			             ->method( 'add_registration_error' );
		}

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Registration::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $registration, $verification_mock );

		// Call validate_registration_email
		$result = $registration->validate_registration_email(
			new WP_Error(),
			'testuser',
			'password123',
			'test@example.com'
		);

		// Verify result is a WP_Error object
		$this->assertInstanceOf( 'WP_Error', $result, "Result should be WP_Error for $scenario_description" );
	}

	/**
	 * Data provider for verify_email result scenarios
	 *
	 * @return array Test scenarios [verify_result, should_add_error, scenario_description]
	 */
	public function provideVerifyEmailResultScenarios() {
		return array(
			'verify_email_returns_wp_error'            => array(
				new WP_Error( 'api_error', 'API connection failed' ),
				false,  // Should NOT call add_registration_error
				'WP_Error from verify_email'
			),
			'verify_email_returns_deliverable'         => array(
				array( 'result' => 'deliverable' ),
				false,  // Should NOT call add_registration_error (action is allow)
				'deliverable result with allow action'
			),
			'verify_email_returns_undeliverable_block' => array(
				array( 'result' => 'undeliverable' ),
				true,   // Should call add_registration_error (action is block)
				'undeliverable result with block action'
			),
		);
	}

	/**
	 * Test validate_registration_email with admin decision scenarios
	 *
	 * @dataProvider provideAdminDecisionScenarios
	 */
	public function test_validate_registration_email_with_admin_decision(
		$admin_decision_result,
		$should_have_error,
		$expected_error_message,
		$scenario_description
	) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'registration' )
		                  ->willReturn( true );

		// Mock verify_email to return admin decision result
		$verification_mock->method( 'verify_email' )
		                  ->willReturn( $admin_decision_result );

		// Create registration mock - only mock get_allow_list
		$registration = $this->getMockBuilder( Kickbox_Integration_Registration::class )
		                     ->onlyMethods( array( 'get_allow_list' ) )
		                     ->getMock();

		// Mock get_allow_list to return empty array
		$registration->method( 'get_allow_list' )
		             ->willReturn( array() );

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Registration::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $registration, $verification_mock );

		// Call validate_registration_email
		$validation_error = new WP_Error();
		$result           = $registration->validate_registration_email(
			$validation_error,
			'testuser',
			'password123',
			'test@example.com'
		);

		// Verify result is a WP_Error object
		$this->assertInstanceOf( 'WP_Error', $result, "Result should be WP_Error for $scenario_description" );

		// Check if error was added
		if ( $should_have_error ) {
			$this->assertTrue( $result->has_errors(), "Should have errors for $scenario_description" );
			$this->assertContains( 'kickbox_integration_email_verification', $result->get_error_codes(), 'Error code should be kickbox_integration_email_verification' );

			$error_message = $result->get_error_message( 'kickbox_integration_email_verification' );
			$this->assertStringContainsString( $expected_error_message, $error_message, "Error message should contain expected text for $scenario_description" );
		} else {
			$this->assertFalse( $result->has_errors(), "Should not have errors for $scenario_description" );
		}
	}

	/**
	 * Data provider for admin decision scenarios
	 *
	 * @return array Test scenarios [admin_decision_result, should_have_error, expected_error_message, scenario_description]
	 */
	public function provideAdminDecisionScenarios() {
		return array(
			'admin_decision_allow'         => array(
				array( 'result' => 'deliverable', 'reason' => 'admin_decision' ),
				false,  // Should NOT have error
				'',
				'admin decision: allow/deliverable'
			),
			'admin_decision_deliverable'   => array(
				array( 'result' => 'deliverable', 'reason' => 'admin_decision' ),
				false,  // Should NOT have error
				'',
				'admin decision: deliverable'
			),
			'admin_decision_undeliverable' => array(
				array( 'result' => 'undeliverable', 'reason' => 'admin_decision' ),
				true,   // Should have error
				'does not exist or is invalid',
				'admin decision: undeliverable (block)'
			),
		);
	}

	/**
	 * Test validate_registration_email with settings-based action scenarios
	 *
	 * @dataProvider provideSettingsBasedActionScenarios
	 */
	public function test_validate_registration_email_with_settings_based_action(
		$verification_result,
		$action,
		$should_have_error,
		$expected_error_message,
		$scenario_description
	) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array(
			                          'is_verification_enabled',
			                          'verify_email',
			                          'get_action_for_result'
		                          ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'registration' )
		                  ->willReturn( true );

		// Mock verify_email to return verification result (no admin_decision reason)
		$verification_mock->method( 'verify_email' )
		                  ->willReturn( array( 'result' => $verification_result ) );

		// Mock get_action_for_result to return the action
		$verification_mock->method( 'get_action_for_result' )
		                  ->with( $verification_result )
		                  ->willReturn( $action );

		// Create registration mock - only mock get_allow_list
		$registration = $this->getMockBuilder( Kickbox_Integration_Registration::class )
		                     ->onlyMethods( array( 'get_allow_list' ) )
		                     ->getMock();

		// Mock get_allow_list to return empty array
		$registration->method( 'get_allow_list' )
		             ->willReturn( array() );

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Registration::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $registration, $verification_mock );

		// Call validate_registration_email
		$validation_error = new WP_Error();
		$result           = $registration->validate_registration_email(
			$validation_error,
			'testuser',
			'password123',
			'test@example.com'
		);

		// Verify result is a WP_Error object
		$this->assertInstanceOf( 'WP_Error', $result, "Result should be WP_Error for $scenario_description" );

		// Check if error was added
		if ( $should_have_error ) {
			$this->assertTrue( $result->has_errors(), "Should have errors for $scenario_description" );
			$this->assertContains( 'kickbox_integration_email_verification', $result->get_error_codes(), 'Error code should be kickbox_integration_email_verification' );

			$error_message = $result->get_error_message( 'kickbox_integration_email_verification' );
			$this->assertStringContainsString( $expected_error_message, $error_message, "Error message should contain expected text for $scenario_description" );
		} else {
			$this->assertFalse( $result->has_errors(), "Should not have errors for $scenario_description" );
		}
	}

	/**
	 * Data provider for settings-based action scenarios
	 *
	 * @return array Test scenarios [verification_result, action, should_have_error, expected_error_message, scenario_description]
	 */
	public function provideSettingsBasedActionScenarios() {
		return array(
			'deliverable_with_allow_action'    => array(
				'deliverable',
				'allow',
				false,  // Should NOT have error
				'',
				'deliverable result with allow action'
			),
			'undeliverable_with_allow_action'  => array(
				'undeliverable',
				'allow',
				false,  // Should NOT have error (review allows registration)
				'',
				'undeliverable result with allow action'
			),
			'undeliverable_with_review_action' => array(
				'undeliverable',
				'review',
				false,  // Should NOT have error (review allows registration)
				'',
				'undeliverable result with review action'
			),
			'undeliverable_with_block_action'  => array(
				'undeliverable',
				'block',
				true,   // Should have error
				'does not exist or is invalid',
				'undeliverable result with block action'
			),
			'risky_with_allow_action'          => array(
				'risky',
				'allow',
				false,  // Should NOT have error
				'',
				'risky result with allow action'
			),
			'risky_with_review_action'         => array(
				'risky',
				'review',
				false,  // Should NOT have error (review allows registration)
				'',
				'risky result with review action'
			),
			'risky_with_block_action'          => array(
				'risky',
				'block',
				true,   // Should have error
				'quality issues and may result in bounces',
				'risky result with block action'
			),
			'unknown_with_allow_action'        => array(
				'unknown',
				'allow',
				false,   // Should have error
				'',
				'unknown result with allow action'
			),
			'unknown_with_review_action'       => array(
				'unknown',
				'review',
				false,   // Should NOT have error
				'',
				'unknown result with review action'
			),
			'unknown_with_block_action'        => array(
				'unknown',
				'block',
				true,   // Should have error
				'unable to verify this email address due to server timeout',
				'unknown result with block action'
			),
		);
	}
}






