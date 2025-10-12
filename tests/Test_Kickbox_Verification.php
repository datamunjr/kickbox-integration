<?php
/**
 * Test Kickbox_Integration_Verification class
 *
 * @package Kickbox_Integration
 */

/**
 * @codingStandardsIgnoreFile
 */
class Test_Kickbox_Verification extends WP_UnitTestCase {

	/**
	 * Test that the verification class exists
	 */
	public function test_verification_class_exists() {
		$this->assertTrue( class_exists( 'Kickbox_Integration_Verification' ), 'Kickbox_Integration_Verification class should exist' );
	}

	/**
	 * Test verify_email with email in allow list
	 */
	public function test_verify_email_in_allow_list() {
		// Set up API key to avoid no_api_key error
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Set up the allow list in the database
		update_option( 'kickbox_integration_allow_list', array( 'allowed@example.com' ) );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_kickbox_verification_results_for_email' ) )
		                          ->getMock();

		// Ensure get_kickbox_verification_results_for_email is never called for allow list emails
		$verification_mock->expects( $this->never() )
		                  ->method( 'get_kickbox_verification_results_for_email' );

		// Call verify_email
		$result = $verification_mock->verify_email( 'allowed@example.com' );

		// Verify the result
		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEquals( 'deliverable', $result['result'], 'Result should be deliverable' );
		$this->assertEquals( 'allow_list', $result['reason'], 'Reason should be allow_list' );
		$this->assertEquals( 1, $result['sendex'], 'Sendex should be 1' );
		$this->assertEquals( 'example.com', $result['domain'], 'Domain should be extracted correctly' );
		$this->assertEquals( 'allowed', $result['user'], 'User should be extracted correctly' );

		// Clean up
		delete_option( 'kickbox_integration_allow_list' );
		delete_option( 'kickbox_integration_api_key' );
	}

	/**
	 * Test verify_email with admin decisions
	 *
	 * @dataProvider provideAdminDecisionScenarios
	 */
	public function test_verify_email_admin_decision(
		$admin_decision,
		$email,
		$expected_result,
		$expected_reason,
		$expected_sendex,
		$expected_user,
		$scenario_description
	) {
		// Set up API key to avoid no_api_key error
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_kickbox_verification_results_for_email' ) )
		                          ->getMock();

		// Ensure get_kickbox_verification_results_for_email is never called for admin decisions
		$verification_mock->expects( $this->never() )
		                  ->method( 'get_kickbox_verification_results_for_email' );

		// Create a flagged email record with the specified admin decision
		global $wpdb;
		$insert_data = array(
			'email'          => $email,
			'admin_decision' => $admin_decision,
			'flagged_date'   => current_time( 'mysql' )
		);

		// For pending decisions, add cached kickbox result
		if ( $admin_decision === 'pending' ) {
			$cached_kickbox_result         = array(
				'result'       => 'risky',
				'reason'       => 'low_quality',
				'sendex'       => 0.5,
				'role'         => false,
				'free'         => false,
				'disposable'   => false,
				'accept_all'   => false,
				'did_you_mean' => null,
				'domain'       => 'example.com',
				'user'         => 'pending'
			);
			$insert_data['kickbox_result'] = json_encode( $cached_kickbox_result );
		}

		$wpdb->insert(
			$wpdb->prefix . 'kickbox_integration_flagged_emails',
			$insert_data,
			array_fill( 0, count( $insert_data ), '%s' )
		);

		// Call verify_email
		$result = $verification_mock->verify_email( $email );

		// Verify the result
		$this->assertIsArray( $result, "Result should be an array for $scenario_description" );
		$this->assertEquals( $expected_result, $result['result'], "Result should be $expected_result for $scenario_description" );
		$this->assertEquals( $expected_reason, $result['reason'], "Reason should be $expected_reason for $scenario_description" );
		$this->assertEquals( $expected_sendex, $result['sendex'], "Sendex should be $expected_sendex for $scenario_description" );
		$this->assertEquals( 'example.com', $result['domain'], "Domain should be extracted correctly for $scenario_description" );
		$this->assertEquals( $expected_user, $result['user'], "User should be $expected_user for $scenario_description" );

		// Clean up
		delete_option( 'kickbox_integration_api_key' );
		$wpdb->delete(
			$wpdb->prefix . 'kickbox_integration_flagged_emails',
			array( 'email' => $email ),
			array( '%s' )
		);
	}

	/**
	 * Data provider for admin decision scenarios
	 *
	 * @return array Test scenarios [
	 *          admin_decision,
	 *          email,
	 *          expected_result,
	 *          expected_reason,
	 *          expected_sendex,
	 *          expected_user,
	 *          scenario_description
	 *      ]
	 */
	public function provideAdminDecisionScenarios() {
		return array(
			'admin_decision_allow'   => array(
				'allow',
				'admin-allowed@example.com',
				'deliverable',
				'admin_decision',
				1,
				'admin-allowed',
				'admin decision: allow (should return deliverable)'
			),
			'admin_decision_block'   => array(
				'block',
				'admin-blocked@example.com',
				'undeliverable',
				'admin_decision',
				0,
				'admin-blocked',
				'admin decision: block (should return undeliverable)'
			),
			'admin_decision_pending' => array(
				'pending',
				'pending@example.com',
				'risky',
				'low_quality',
				0.5,
				'pending',
				'admin decision: pending (should return cached kickbox result)'
			),
		);
	}

	/**
	 * Test verify_email with no admin decision - should call Kickbox API
	 */
	public function test_verify_email_calls_kickbox_api() {
		// Set up API key
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array(
			                          'get_kickbox_verification_results_for_email',
			                          'update_balance_from_response',
			                          'log_verification',
			                          'check_and_flag_for_review'
		                          ) )
		                          ->getMock();

		// Mock the HTTP call method
		$mock_kickbox_result = array(
			'response' => array( 'code' => 200 ),
			'data'     => array(
				'result'       => 'deliverable',
				'reason'       => 'accepted_email',
				'sendex'       => 1,
				'role'         => false,
				'free'         => false,
				'disposable'   => false,
				'accept_all'   => false,
				'did_you_mean' => null,
				'domain'       => 'example.com',
				'user'         => 'test'
			)
		);

		$verification_mock->expects( $this->once() )
		                  ->method( 'get_kickbox_verification_results_for_email' )
		                  ->with( 'test@example.com' )
		                  ->willReturn( $mock_kickbox_result );

		// SPY on the methods that should be called
		$verification_mock->expects( $this->once() )
		                  ->method( 'update_balance_from_response' )
		                  ->with( $mock_kickbox_result['response'] );

		$verification_mock->expects( $this->once() )
		                  ->method( 'log_verification' )
		                  ->with( 'test@example.com', $mock_kickbox_result['data'], $this->anything(), $this->anything(), $this->anything() );

		$verification_mock->expects( $this->once() )
		                  ->method( 'check_and_flag_for_review' )
		                  ->with( 'test@example.com', $mock_kickbox_result['data'], $this->anything() );

		// Call verify_email
		$result = $verification_mock->verify_email( 'test@example.com' );

		// Verify the result
		$this->assertIsArray( $result, 'Result should be an array' );
		$this->assertEquals( 'deliverable', $result['result'], 'Result should be deliverable' );
		$this->assertEquals( 'accepted_email', $result['reason'], 'Reason should be accepted_email' );
		$this->assertEquals( 1, $result['sendex'], 'Sendex should be 1' );

		// Clean up
		delete_option( 'kickbox_integration_api_key' );
	}


	/**
	 * Test verify_email with invalid email inputs
	 *
	 * @dataProvider provideInvalidEmailScenarios
	 */
	public function test_verify_email_invalid_inputs( $email, $description ) {
		// Set up API key to avoid no_api_key error
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_kickbox_verification_results_for_email' ) )
		                          ->getMock();

		// Ensure get_kickbox_verification_results_for_email is never called for invalid emails
		$verification_mock->expects( $this->never() )
		                  ->method( 'get_kickbox_verification_results_for_email' );

		// Call verify_email with invalid email
		$result = $verification_mock->verify_email( $email );

		// Verify the result is a WP_Error
		$this->assertInstanceOf( 'WP_Error', $result, "Result should be WP_Error for {$description}" );
		$this->assertEquals( 'invalid_email', $result->get_error_code(), "Error code should be invalid_email for {$description}" );

		// Clean up
		delete_option( 'kickbox_integration_api_key' );
	}

	/**
	 * Data provider for invalid email scenarios
	 *
	 * @return array Test scenarios [email, description]
	 */
	public function provideInvalidEmailScenarios() {
		return array(
			'invalid_format' => array(
				'invalid-email',
				'invalid email format'
			),
			'empty_email'    => array(
				'',
				'empty email'
			),
			'null_email'     => array(
				(string) null,
				'null email'
			)
		);
	}

	/**
	 * Test verify_email with no API key
	 */
	public function test_verify_email_no_api_key() {
		// Ensure no API key is set
		delete_option( 'kickbox_integration_api_key' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_kickbox_verification_results_for_email' ) )
		                          ->getMock();

		// Ensure get_kickbox_verification_results_for_email is never called when no API key
		$verification_mock->expects( $this->never() )
		                  ->method( 'get_kickbox_verification_results_for_email' );

		// Call verify_email
		$result = $verification_mock->verify_email( 'test@example.com' );

		// Verify the result is a WP_Error
		$this->assertInstanceOf( 'WP_Error', $result, 'Result should be WP_Error when no API key' );
		$this->assertEquals( 'no_api_key', $result->get_error_code(), 'Error code should be no_api_key' );
	}

	/**
	 * Test verify_email with wp_remote_get error
	 */
	public function test_verify_email_wp_remote_get_error() {
		// Set up API key
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_kickbox_verification_results_for_email' ) )
		                          ->getMock();

		// Mock the HTTP call method to return WP_Error
		$verification_mock->expects( $this->once() )
		                  ->method( 'get_kickbox_verification_results_for_email' )
		                  ->with( 'test@example.com' )
		                  ->willReturn( new WP_Error( 'http_error', 'Connection failed' ) );

		// Call verify_email
		$result = $verification_mock->verify_email( 'test@example.com' );

		// Verify the result is a WP_Error
		$this->assertInstanceOf( 'WP_Error', $result, 'Result should be WP_Error when wp_remote_get fails' );
		$this->assertEquals( 'http_error', $result->get_error_code(), 'Error code should match wp_remote_get error' );

		// Clean up
		delete_option( 'kickbox_integration_api_key' );
	}

	/**
	 * Test verify_email with invalid JSON response
	 */
	public function test_verify_email_invalid_json_response() {
		// Set up API key
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array( 'get_kickbox_verification_results_for_email' ) )
		                          ->getMock();

		// Mock the HTTP call method to return WP_Error for invalid JSON
		$verification_mock->expects( $this->once() )
		                  ->method( 'get_kickbox_verification_results_for_email' )
		                  ->with( 'test@example.com' )
		                  ->willReturn( new WP_Error( 'invalid_response', 'Invalid response from Kickbox API.' ) );

		// Call verify_email
		$result = $verification_mock->verify_email( 'test@example.com' );

		// Verify the result is a WP_Error
		$this->assertInstanceOf( 'WP_Error', $result, 'Result should be WP_Error for invalid JSON' );
		$this->assertEquals( 'invalid_response', $result->get_error_code(), 'Error code should be invalid_response' );

		// Clean up
		delete_option( 'kickbox_integration_api_key' );
	}


	/**
	 * Test verify_email with various API response formats
	 *
	 * @dataProvider provideApiResponseScenarios
	 */
	public function test_verify_email_various_api_responses( $expected_result, $scenario_name ) {
		// Set up API key
		update_option( 'kickbox_integration_api_key', 'test_123456789' );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array(
			                          'get_kickbox_verification_results_for_email',
			                          'update_balance_from_response',
			                          'log_verification',
			                          'check_and_flag_for_review'
		                          ) )
		                          ->getMock();

		// Mock the HTTP call method
		$mock_kickbox_result = array(
			'response' => array( 'code' => 200 ),
			'data'     => $expected_result
		);

		$verification_mock->expects( $this->once() )
		                  ->method( 'get_kickbox_verification_results_for_email' )
		                  ->with( 'test@example.com' )
		                  ->willReturn( $mock_kickbox_result );

		// Mock the other methods
		$verification_mock->expects( $this->once() )
		                  ->method( 'update_balance_from_response' )
		                  ->with( $mock_kickbox_result['response'] );

		$verification_mock->expects( $this->once() )
		                  ->method( 'log_verification' )
		                  ->with( 'test@example.com', $expected_result, $this->anything(), $this->anything(), $this->anything() );

		$verification_mock->expects( $this->once() )
		                  ->method( 'check_and_flag_for_review' )
		                  ->with( 'test@example.com', $expected_result, $this->anything() );

		// Call verify_email
		$result = $verification_mock->verify_email( 'test@example.com' );

		// Verify the result
		$this->assertIsArray( $result, "Result should be an array for {$scenario_name} scenario" );
		$this->assertEquals( $expected_result['result'], $result['result'], "Result should match expected for {$scenario_name} scenario" );
		$this->assertEquals( $expected_result['reason'], $result['reason'], "Reason should match expected for {$scenario_name} scenario" );
		$this->assertEquals( $expected_result['sendex'], $result['sendex'], "Sendex should match expected for {$scenario_name} scenario" );

		// Clean up
		delete_option( 'kickbox_integration_api_key' );
	}

	/**
	 * Data provider for API response scenarios
	 *
	 * @return array Test scenarios [expected_result, scenario_name]
	 */
	public function provideApiResponseScenarios() {
		return array(
			'undeliverable' => array(
				array(
					'result' => 'undeliverable',
					'reason' => 'invalid_smtp',
					'sendex' => 0
				),
				'undeliverable'
			),
			'risky'         => array(
				array(
					'result' => 'risky',
					'reason' => 'low_quality',
					'sendex' => 0.5
				),
				'risky'
			),
			'unknown'       => array(
				array(
					'result' => 'unknown',
					'reason' => 'timeout',
					'sendex' => 0.3
				),
				'unknown'
			)
		);
	}

	/**
	 * Test check_and_flag_for_review method with various result types and action settings
	 *
	 * @dataProvider provideFlagForReviewScenarios
	 */
	public function test_check_and_flag_for_review( $result, $action_setting, $should_flag, $description ) {
		// Set up the action setting for this result type
		update_option( "kickbox_integration_{$result}_action", $action_setting );

		// Create mock verification instance
		$verification_mock = $this->getMockBuilder( Kickbox_Integration_Verification::class )
		                          ->onlyMethods( array() )
		                          ->getMock();

		// Create a reflection to access the protected method
		$reflection = new ReflectionClass( $verification_mock );
		$method     = $reflection->getMethod( 'check_and_flag_for_review' );
		$method->setAccessible( true );

		// Prepare test data
		$kickbox_result = array(
			'result' => $result,
			'reason' => 'test_reason',
			'sendex' => 0.5
		);

		$options = array(
			'order_id' => 123,
			'user_id'  => 456,
			'origin'   => 'checkout'
		);

		// Count existing flagged emails before the test
		global $wpdb;
		$flagged_emails_table = $wpdb->prefix . 'kickbox_integration_flagged_emails';
		$initial_count        = $wpdb->get_var( "SELECT COUNT(*) FROM {$flagged_emails_table}" );

		// Call the check_and_flag_for_review method
		$method->invoke( $verification_mock, 'test@example.com', $kickbox_result, $options );

		// Check if a new flagged email was created
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$flagged_emails_table}" );
		$was_flagged = ( $final_count > $initial_count );

		// Verify the behavior matches expectations
		if ( $should_flag ) {
			$this->assertTrue( $was_flagged, "Email should have been flagged for {$description}" );

			// If flagged, verify the details
			if ( $was_flagged ) {
				$flagged_email = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$flagged_emails_table} WHERE email = %s ORDER BY flagged_date DESC LIMIT 1",
					'test@example.com'
				) );

				$this->assertEquals( $action_setting, $flagged_email->verification_action, 'Verification action should match setting' );
				$this->assertEquals( 'pending', $flagged_email->admin_decision, 'Admin decision should be pending' );
			}
		} else {
			$this->assertFalse( $was_flagged, "Email should not have been flagged for {$description}" );
		}

		// Clean up - remove any flagged emails created during the test
		$wpdb->delete( $flagged_emails_table, array( 'email' => 'test@example.com' ) );
		delete_option( "kickbox_integration_{$result}_action" );
	}

	/**
	 * Data provider for flag for review scenarios
	 *
	 * @return array Test scenarios [result, action_setting, should_flag, description]
	 */
	public function provideFlagForReviewScenarios() {
		return array(
			// Current implementation only handles undeliverable, risky, unknown
			'undeliverable_allow'  => array(
				'undeliverable',
				'allow',
				false,
				'undeliverable with allow action should not flag'
			),
			'undeliverable_block'  => array(
				'undeliverable',
				'block',
				true,
				'undeliverable with block action should flag'
			),
			'undeliverable_review' => array(
				'undeliverable',
				'review',
				true,
				'undeliverable with review action should flag'
			),
			'risky_allow'          => array( 'risky', 'allow', false, 'risky with allow action should not flag' ),
			'risky_block'          => array( 'risky', 'block', true, 'risky with block action should flag' ),
			'risky_review'         => array( 'risky', 'review', true, 'risky with review action should flag' ),
			'unknown_allow'        => array( 'unknown', 'allow', false, 'unknown with allow action should not flag' ),
			'unknown_block'        => array( 'unknown', 'block', true, 'unknown with block action should flag' ),
			'unknown_review'       => array( 'unknown', 'review', true, 'unknown with review action should flag' ),

			// These are now properly handled after the bug fix
			'deliverable_allow'    => array(
				'deliverable',
				'allow',
				false,
				'deliverable with allow action should not flag'
			),
			'deliverable_block'    => array(
				'deliverable',
				'block',
				true,
				'deliverable with block action should flag'
			),
			'deliverable_review'   => array(
				'deliverable',
				'review',
				true,
				'deliverable with review action should flag'
			),
		);
	}

	/**
	 * Test update_balance_from_response method with various header scenarios
	 *
	 * @dataProvider provideBalanceUpdateScenarios
	 */
	public function test_update_balance_from_response( $headers, $expected_balance, $should_update, $description ) {
		// Create an instance of the Verification class
		$verification_instance = new Kickbox_Integration_Verification();

		// Create a reflection to access the protected method
		$reflection = new ReflectionClass( Kickbox_Integration_Verification::class );
		$method     = $reflection->getMethod( 'update_balance_from_response' );
		$method->setAccessible( true );

		// Create mock response with headers
		$mock_response = array( 'headers' => $headers );

		// Get initial values
		$initial_balance = get_option( 'kickbox_integration_api_balance', 0 );
		$initial_updated = get_option( 'kickbox_integration_balance_last_updated', '' );

		// Call the update_balance_from_response method
		$method->invoke( $verification_instance, $mock_response );

		// Check if balance was updated
		$final_balance = get_option( 'kickbox_integration_api_balance', 0 );
		$final_updated = get_option( 'kickbox_integration_balance_last_updated', '' );

		if ( $should_update ) {
			$this->assertEquals( $expected_balance, $final_balance, "Balance should be updated to {$expected_balance} for {$description}" );
			$this->assertNotEquals( $initial_updated, $final_updated, "Last updated timestamp should change for {$description}" );
			$this->assertNotEmpty( $final_updated, "Last updated timestamp should not be empty for {$description}" );
		} else {
			$this->assertEquals( $initial_balance, $final_balance, "Balance should not change for {$description}" );
			$this->assertEquals( $initial_updated, $final_updated, "Last updated timestamp should not change for {$description}" );
		}

		// Clean up - restore initial values
		update_option( 'kickbox_integration_api_balance', $initial_balance );
		update_option( 'kickbox_integration_balance_last_updated', $initial_updated );
	}

	/**
	 * Data provider for balance update scenarios
	 *
	 * @return array Test scenarios [headers, expected_balance, should_update, description]
	 */
	public function provideBalanceUpdateScenarios() {
		return array(
			'valid_balance_header'    => array(
				array( 'x-kickbox-balance' => '1000' ),
				1000,
				true,
				'valid balance header should update balance'
			),
			'zero_balance_header'     => array(
				array( 'x-kickbox-balance' => '0' ),
				0,
				true,
				'zero balance header should update balance'
			),
			'negative_balance_header' => array(
				array( 'x-kickbox-balance' => '-100' ),
				- 100,
				true,
				'negative balance header should update balance'
			),
			'no_balance_header'       => array(
				array( 'content-type' => 'application/json' ),
				0,
				false,
				'response without balance header should not update'
			),
			'empty_headers'           => array(
				array(),
				0,
				false,
				'empty headers should not update'
			),
			'other_headers_only'      => array(
				array(
					'content-type'   => 'application/json',
					'content-length' => '123'
				),
				0,
				false,
				'response with other headers but no balance should not update'
			),
		);
	}

	/**
	 * Test log_verification method with various verification scenarios
	 *
	 * @dataProvider provideLogVerificationScenarios
	 */
	public function test_log_verification( $email, $verification_result, $user_id, $order_id, $origin, $description ) {
		// Create an instance of the Verification class
		$verification_instance = new Kickbox_Integration_Verification();

		// Create a reflection to access the protected method
		$reflection = new ReflectionClass( Kickbox_Integration_Verification::class );
		$method     = $reflection->getMethod( 'log_verification' );
		$method->setAccessible( true );

		// Count existing verification logs before the test
		global $wpdb;
		$verification_log_table = $wpdb->prefix . 'kickbox_integration_verification_log';
		$initial_count          = $wpdb->get_var( "SELECT COUNT(*) FROM {$verification_log_table}" );

		// Call the log_verification method
		$method->invoke( $verification_instance, $email, $verification_result, $user_id, $order_id, $origin );

		// Check if a new verification log was created
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$verification_log_table}" );
		$was_logged  = ( $final_count > $initial_count );

		// Verify that a log entry was created
		$this->assertTrue( $was_logged, "Verification should have been logged for {$description}" );

		// If logged, verify the details
		if ( $was_logged ) {
			$log_entry = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$verification_log_table} WHERE email = %s ORDER BY created_at DESC LIMIT 1",
				$email
			) );

			$this->assertNotNull( $log_entry, "Log entry should exist for {$description}" );
			$this->assertEquals( $email, $log_entry->email, "Email should match for {$description}" );
			$this->assertEquals( $verification_result['result'], $log_entry->verification_result, "Verification result should match for {$description}" );
			$this->assertEquals( $user_id, $log_entry->user_id, "User ID should match for {$description}" );
			$this->assertEquals( $order_id, $log_entry->order_id, "Order ID should match for {$description}" );
			$this->assertEquals( $origin, $log_entry->origin, "Origin should match for {$description}" );
			$this->assertNotEmpty( $log_entry->created_at, "Created at should not be empty for {$description}" );

			// Verify the verification_data JSON
			$verification_data = json_decode( $log_entry->verification_data, true );
			$this->assertIsArray( $verification_data, "Verification data should be valid JSON for {$description}" );
			$this->assertEquals( $verification_result, $verification_data, "Verification data should match for {$description}" );
		}

		// Clean up - remove any verification logs created during the test
		$wpdb->delete( $verification_log_table, array( 'email' => $email ) );
	}

	/**
	 * Data provider for log verification scenarios
	 *
	 * @return array Test scenarios [email, verification_result, user_id, order_id, origin, description]
	 */
	public function provideLogVerificationScenarios() {
		return array(
			'deliverable_checkout'       => array(
				'test@example.com',
				array(
					'result' => 'deliverable',
					'reason' => 'accepted_email',
					'sendex' => 1.0
				),
				123,
				456,
				'checkout',
				'deliverable email from checkout'
			),
			'undeliverable_registration' => array(
				'invalid@example.com',
				array(
					'result' => 'undeliverable',
					'reason' => 'invalid_smtp',
					'sendex' => 0.0
				),
				789,
				null,
				'registration',
				'undeliverable email from registration'
			),
			'risky_checkout'             => array(
				'risky@example.com',
				array(
					'result' => 'risky',
					'reason' => 'low_quality',
					'sendex' => 0.5
				),
				null,
				999,
				'checkout',
				'risky email from checkout'
			),
			'unknown_registration'       => array(
				'unknown@example.com',
				array(
					'result' => 'unknown',
					'reason' => 'timeout',
					'sendex' => 0.3
				),
				555,
				null,
				'registration',
				'unknown email from registration'
			),
			'minimal_data'               => array(
				'minimal@example.com',
				array(
					'result' => 'deliverable',
					'reason' => 'accepted_email'
				),
				null,
				null,
				null,
				'email with minimal data'
			),
		);
	}
}
