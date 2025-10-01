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
}

