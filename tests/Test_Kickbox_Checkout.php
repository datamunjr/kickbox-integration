<?php
/**
 * Test Kickbox_Integration_Checkout class
 *
 * @package Kickbox_Integration
 */

class Test_Kickbox_Checkout extends WP_UnitTestCase {

	/**
	 * Test that the checkout validation hook is registered
	 */
	public function test_checkout_validation_hook_registered() {
		$this->assertTrue( has_action( 'woocommerce_blocks_validate_location_address_fields' ), 'Checkout validation hook should be registered' );
	}

	/**
	 * Test validate_email_in_address_fields with non-billing group
	 *
	 * @dataProvider provideNonBillingGroups
	 */
	public function test_validate_email_in_address_fields_non_billing_group( $group ) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( true );

		// SPY on verify_email - should NEVER be called for non-billing groups
		$verification_mock->expects( $this->never() )
		                  ->method( 'verify_email' );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields with non-billing group
		$errors = new WP_Error();
		$fields = array( 'email' => 'test@example.com' );

		$checkout->validate_email_in_address_fields( $errors, $fields, $group );

		// Verify no errors were added
		$this->assertFalse( $errors->has_errors(), "Should not have errors for non-billing group: $group" );
	}

	/**
	 * Data provider for non-billing groups
	 *
	 * @return array Test scenarios [group]
	 */
	public function provideNonBillingGroups() {
		return array(
			'shipping_group' => array( 'shipping' ),
			'contact_group'  => array( 'contact' ),
			'empty_group'    => array( '' ),
			'null_group'     => array( null ),
		);
	}

	/**
	 * Test validate_email_in_address_fields with billing group when verification is disabled
	 */
	public function test_validate_email_in_address_fields_verification_disabled() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return false
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( false );

		// SPY on verify_email - should NEVER be called when verification is disabled
		$verification_mock->expects( $this->never() )
		                  ->method( 'verify_email' );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields with billing group
		$errors = new WP_Error();
		$fields = array( 'email' => 'test@example.com' );

		$checkout->validate_email_in_address_fields( $errors, $fields, 'billing' );

		// Verify no errors were added
		$this->assertFalse( $errors->has_errors(), 'Should not have errors when verification is disabled' );
	}

	/**
	 * Test validate_email_in_address_fields with billing group when email is empty
	 */
	public function test_validate_email_in_address_fields_empty_email() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( true );

		// SPY on verify_email - should NEVER be called when email is empty
		$verification_mock->expects( $this->never() )
		                  ->method( 'verify_email' );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields with empty email
		$errors = new WP_Error();
		$fields = array( 'email' => '' );

		$checkout->validate_email_in_address_fields( $errors, $fields, 'billing' );

		// Verify no errors were added
		$this->assertFalse( $errors->has_errors(), 'Should not have errors when email is empty' );
	}

	/**
	 * Test validate_email_in_address_fields with billing group and valid email
	 *
	 * @dataProvider provideValidEmailScenarios
	 */
	public function test_validate_email_in_address_fields_valid_email(
		$email,
		$verification_result,
		$should_cache_result,
		$scenario_description
	) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( true );

		// SPY on verify_email - should be called exactly once for billing group
		$verification_mock->expects( $this->once() )
		                  ->method( 'verify_email' )
		                  ->with(
			                  $this->equalTo( $email ),
			                  $this->callback( function ( $args ) {
				                  return isset( $args['origin'] ) && $args['origin'] === 'checkout';
			                  } )
		                  )
		                  ->willReturn( $verification_result );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields with billing group
		$errors = new WP_Error();
		$fields = array( 'email' => $email );

		$checkout->validate_email_in_address_fields( $errors, $fields, 'billing' );

		// Verify no errors were added to the errors object (errors are added in add_verification_errors_to_contact_field)
		$this->assertFalse( $errors->has_errors(), "Should not have errors in validate_email_in_address_fields for $scenario_description" );

		// Check if result was cached
		if ( $should_cache_result ) {
			$cached_result_property = $reflection->getProperty( 'cached_result' );
			$cached_result_property->setAccessible( true );
			$cached_result = $cached_result_property->getValue( $checkout );

			$this->assertNotNull( $cached_result, "Cached result should not be null for $scenario_description" );
			$this->assertEquals( $verification_result, $cached_result, "Cached result should match verification result for $scenario_description" );
		}
	}

	/**
	 * Data provider for valid email scenarios
	 *
	 * @return array Test scenarios [email, verification_result, should_cache_result, scenario_description]
	 */
	public function provideValidEmailScenarios() {
		return array(
			'deliverable_result'   => array(
				'test@example.com',
				array( 'result' => 'deliverable', 'reason' => 'accepted_email' ),
				true,  // Should cache result
				'deliverable result'
			),
			'undeliverable_result' => array(
				'invalid@example.com',
				array( 'result' => 'undeliverable', 'reason' => 'invalid_smtp' ),
				true,  // Should cache result
				'undeliverable result'
			),
			'risky_result'         => array(
				'risky@example.com',
				array( 'result' => 'risky', 'reason' => 'low_quality' ),
				true,  // Should cache result
				'risky result'
			),
			'unknown_result'       => array(
				'unknown@example.com',
				array( 'result' => 'unknown', 'reason' => 'low_quality' ),
				true,  // Should cache result
				'unknown result'
			),
			'wp_error_result'      => array(
				'error@example.com',
				new WP_Error( 'api_error', 'API connection failed' ),
				false,  // Should NOT cache WP_Error
				'WP_Error result'
			),
		);
	}

	/**
	 * Test validate_email_in_address_fields with user ID when email belongs to existing user
	 */
	public function test_validate_email_in_address_fields_with_existing_user() {
		// Create a test user
		$user_id = $this->factory->user->create( array( 'user_email' => 'existing@example.com' ) );

		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( true );

		// SPY on verify_email - should be called with user_id
		$verification_mock->expects( $this->once() )
		                  ->method( 'verify_email' )
		                  ->with(
			                  $this->equalTo( 'existing@example.com' ),
			                  $this->callback( function ( $args ) use ( $user_id ) {
				                  return isset( $args['origin'] ) && $args['origin'] === 'checkout' &&
				                         isset( $args['user_id'] ) && $args['user_id'] === $user_id;
			                  } )
		                  )
		                  ->willReturn( array( 'result' => 'deliverable', 'reason' => 'api' ) );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields with existing user email
		$errors = new WP_Error();
		$fields = array( 'email' => 'existing@example.com' );

		$checkout->validate_email_in_address_fields( $errors, $fields, 'billing' );

		// Verify no errors were added
		$this->assertFalse( $errors->has_errors(), 'Should not have errors for existing user email' );

		// Check if result was cached
		$cached_result_property = $reflection->getProperty( 'cached_result' );
		$cached_result_property->setAccessible( true );
		$cached_result = $cached_result_property->getValue( $checkout );

		$this->assertNotNull( $cached_result, 'Cached result should not be null for existing user' );
		$this->assertEquals( 'deliverable', $cached_result['result'], 'Cached result should be deliverable' );
	}

	/**
	 * Test validate_email_in_address_fields with WP_Error from verify_email
	 */
	public function test_validate_email_in_address_fields_with_wp_error() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( true );

		// Mock verify_email to return WP_Error
		$wp_error = new WP_Error( 'api_error', 'API connection failed' );
		$verification_mock->method( 'verify_email' )
		                  ->willReturn( $wp_error );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields
		$errors = new WP_Error();
		$fields = array( 'email' => 'test@example.com' );

		$checkout->validate_email_in_address_fields( $errors, $fields, 'billing' );

		// Verify no errors were added to the errors object
		$this->assertFalse( $errors->has_errors(), 'Should not have errors when verify_email returns WP_Error' );

		// Check that result was NOT cached (WP_Error should not be cached)
		$cached_result_property = $reflection->getProperty( 'cached_result' );
		$cached_result_property->setAccessible( true );
		$cached_result = $cached_result_property->getValue( $checkout );

		$this->assertNull( $cached_result, 'Cached result should be null when verify_email returns WP_Error' );
	}

	/**
	 * Test that cached_email is set correctly
	 */
	public function test_cached_email_is_set() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled', 'verify_email' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return true
		$verification_mock->method( 'is_verification_enabled' )
		                  ->with( 'checkout' )
		                  ->willReturn( true );

		// Mock verify_email to return a result
		$verification_mock->method( 'verify_email' )
		                  ->willReturn( array( 'result' => 'deliverable', 'reason' => 'accepted_email' ) );

		// Create checkout mock
		$checkout = $this->getMockBuilder( Kickbox_Integration_Checkout::class )
		                 ->onlyMethods( array() )
		                 ->getMock();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Call validate_email_in_address_fields
		$errors = new WP_Error();
		$fields = array( 'email' => 'test@example.com' );

		$checkout->validate_email_in_address_fields( $errors, $fields, 'billing' );

		// Check that cached_email is set correctly
		$cached_email_property = $reflection->getProperty( 'cached_email' );
		$cached_email_property->setAccessible( true );
		$cached_email = $cached_email_property->getValue( $checkout );

		$this->assertEquals( 'test@example.com', $cached_email, 'Cached email should be set correctly' );
	}

	/**
	 * Test add_verification_errors_to_contact_field with admin decision scenarios
	 *
	 * @dataProvider provideAdminDecisionScenarios
	 */
	public function test_add_verification_errors_to_contact_field_admin_decision(
		$verification_result,
		$should_have_error,
		$expected_error_message,
		$scenario_description
	) {
		// Create checkout instance
		$checkout = new Kickbox_Integration_Checkout();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );

		// Directly set cached_result with admin decision
		$cached_result_property = $reflection->getProperty( 'cached_result' );
		$cached_result_property->setAccessible( true );
		$cached_result_property->setValue( $checkout, array(
			'result' => $verification_result,
			'reason' => 'admin_decision'
		) );

		// Call add_verification_errors_to_contact_field
		$errors = new WP_Error();
		$fields = array();
		$group  = 'contact';

		$checkout->add_verification_errors_to_contact_field( $errors, $fields, $group );

		// Check if error was added
		if ( $should_have_error ) {
			$this->assertTrue( $errors->has_errors(), "Should have errors for $scenario_description" );
			$this->assertContains( 'kickbox_integration_email_verification', $errors->get_error_codes(), 'Error code should be kickbox_integration_email_verification' );

			$error_message = $errors->get_error_message( 'kickbox_integration_email_verification' );
			$this->assertStringContainsString( $expected_error_message, $error_message, "Error message should contain expected text for $scenario_description" );
		} else {
			$this->assertFalse( $errors->has_errors(), "Should not have errors for $scenario_description" );
		}
	}

	/**
	 * Data provider for admin decision scenarios
	 *
	 * @return array Test scenarios [verification_result, should_have_error, expected_error_message, scenario_description]
	 */
	public function provideAdminDecisionScenarios() {
		return array(
			'admin_decision_undeliverable' => array(
				'undeliverable',
				true,   // Should have error
				'does not exist or is invalid',
				'admin decision: undeliverable (should block)'
			),
			'admin_decision_deliverable'   => array(
				'deliverable',
				false,  // Should NOT have error
				'',
				'admin decision: deliverable (should allow)'
			),
		);
	}

	/**
	 * Test add_verification_errors_to_contact_field with settings-based action scenarios
	 *
	 * @dataProvider provideSettingsBasedActionScenarios
	 */
	public function test_add_verification_errors_to_contact_field_settings_based_action(
		$verification_result,
		$reason,
		$action,
		$should_have_error,
		$expected_error_message,
		$scenario_description
	) {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_action_for_result' ) )
		                          ->getMock();

		// SPY on get_action_for_result - should be called for non-admin decisions
		$verification_mock->expects( $this->once() )
		                  ->method( 'get_action_for_result' )
		                  ->with( $verification_result )
		                  ->willReturn( $action );

		// Create checkout instance
		$checkout = new Kickbox_Integration_Checkout();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Directly set cached_result with non-admin decision
		$cached_result_property = $reflection->getProperty( 'cached_result' );
		$cached_result_property->setAccessible( true );
		$cached_result_property->setValue( $checkout, array(
			'result' => $verification_result,
			'reason' => $reason
		) );

		// Call add_verification_errors_to_contact_field
		$errors = new WP_Error();
		$fields = array();
		$group  = 'contact';

		$checkout->add_verification_errors_to_contact_field( $errors, $fields, $group );

		// Check if error was added
		if ( $should_have_error ) {
			$this->assertTrue( $errors->has_errors(), "Should have errors for $scenario_description" );
			$this->assertContains( 'kickbox_integration_email_verification', $errors->get_error_codes(), 'Error code should be kickbox_integration_email_verification' );

			$error_message = $errors->get_error_message( 'kickbox_integration_email_verification' );
			$this->assertStringContainsString( $expected_error_message, $error_message, "Error message should contain expected text for $scenario_description" );
		} else {
			$this->assertFalse( $errors->has_errors(), "Should not have errors for $scenario_description" );
		}
	}

	/**
	 * Data provider for settings-based action scenarios
	 *
	 * @return array Test scenarios [verification_result, reason, action, should_have_error, expected_error_message, scenario_description]
	 */
	public function provideSettingsBasedActionScenarios() {
		return array(
			'deliverable_with_allow_action'    => array(
				'deliverable',
				'accepted_email',
				'allow',
				false,  // Should NOT have error
				'',
				'deliverable result with allow action'
			),
			'undeliverable_with_allow_action'  => array(
				'undeliverable',
				'invalid_smtp',
				'allow',
				false,  // Should NOT have error
				'',
				'undeliverable result with allow action'
			),
			'undeliverable_with_review_action' => array(
				'undeliverable',
				'rejected_email',
				'review',
				false,  // Should NOT have error
				'',
				'undeliverable result with review action'
			),
			'undeliverable_with_block_action'  => array(
				'undeliverable',
				'invalid_smtp',
				'block',
				true,   // Should have error
				'does not exist or is invalid',
				'undeliverable result with block action'
			),
			'risky_with_allow_action'          => array(
				'risky',
				'low_quality',
				'allow',
				false,  // Should NOT have error
				'',
				'risky result with allow action'
			),
			'risky_with_review_action'         => array(
				'risky',
				'low_quality',
				'review',
				false,  // Should NOT have error
				'',
				'risky result with review action'
			),
			'risky_with_block_action'          => array(
				'risky',
				'low_quality',
				'block',
				true,   // Should have error
				'quality issues and may result in bounces',
				'risky result with block action'
			),
			'unknown_with_allow_action'        => array(
				'unknown',
				'low_quality',
				'allow',
				false,  // Should NOT have error
				'',
				'unknown result with allow action'
			),
			'unknown_with_review_action'       => array(
				'unknown',
				'low_quality',
				'review',
				false,  // Should NOT have error
				'',
				'unknown result with review action'
			),
			'unknown_with_block_action'        => array(
				'unknown',
				'low_quality',
				'block',
				true,   // Should have error
				'unable to verify this email address due to unknown issue',
				'unknown result with block action'
			),
		);
	}

	/**
	 * Test add_verification_errors_to_contact_field with no cached result
	 */
	public function test_add_verification_errors_to_contact_field_no_cached_result() {
		// Create checkout instance
		$checkout = new Kickbox_Integration_Checkout();

		// Don't set cached_result (simulates verification disabled or no validation occurred)

		// Call add_verification_errors_to_contact_field
		$errors = new WP_Error();
		$fields = array();
		$group  = 'contact';

		$checkout->add_verification_errors_to_contact_field( $errors, $fields, $group );

		// Should not have errors when no cached result (verification disabled)
		$this->assertFalse( $errors->has_errors(), 'Should not have errors when no cached result (verification disabled)' );
	}

	/**
	 * Test that scripts are not enqueued when verification is disabled
	 */
	public function test_scripts_not_enqueued_when_verification_disabled() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return false
		$verification_mock->method( 'is_verification_enabled' )
		                  ->willReturn( false );

		// Create checkout instance
		$checkout = new Kickbox_Integration_Checkout();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Mock WordPress query functions
		$wp_query_mock = $this->getMockBuilder( 'stdClass' )
		                      ->addMethods( array( 'is_checkout', 'is_page' ) )
		                      ->getMock();
		$wp_query_mock->method( 'is_checkout' )->willReturn( true );
		$wp_query_mock->method( 'is_page' )->willReturn( true );
		$GLOBALS['wp_query'] = $wp_query_mock;

		// Call enqueue_checkout_scripts
		$checkout->enqueue_checkout_scripts();

		// Verify scripts are not enqueued
		$this->assertFalse( wp_script_is( 'kickbox-integration-checkout', 'enqueued' ), 'Checkout script should not be enqueued when verification is disabled' );
		$this->assertFalse( wp_style_is( 'kickbox-integration-checkout', 'enqueued' ), 'Checkout style should not be enqueued when verification is disabled' );
	}

	/**
	 * Test that blocks checkout support is not added when verification is disabled
	 */
	public function test_blocks_checkout_support_not_added_when_verification_disabled() {
		// Create a mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'is_verification_enabled' ) )
		                          ->getMock();

		// Mock is_verification_enabled to return false
		$verification_mock->method( 'is_verification_enabled' )
		                  ->willReturn( false );

		// Create checkout instance
		$checkout = new Kickbox_Integration_Checkout();

		// Use reflection to set the verification property
		$reflection = new ReflectionClass( Kickbox_Integration_Checkout::class );
		$property   = $reflection->getProperty( 'verification' );
		$property->setAccessible( true );
		$property->setValue( $checkout, $verification_mock );

		// Mock WordPress query functions
		$wp_query_mock = $this->getMockBuilder( 'stdClass' )
		                      ->addMethods( array( 'is_checkout', 'is_page' ) )
		                      ->getMock();
		$wp_query_mock->method( 'is_checkout' )->willReturn( true );
		$wp_query_mock->method( 'is_page' )->willReturn( true );
		$GLOBALS['wp_query'] = $wp_query_mock;

		// Capture output
		ob_start();
		$checkout->add_blocks_checkout_support();
		$output = ob_get_clean();

		// Verify no JavaScript is output
		$this->assertEmpty( $output, 'No JavaScript should be output when verification is disabled' );
	}
}
