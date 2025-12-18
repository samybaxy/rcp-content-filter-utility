<?php
/**
 * Class Test_LearnPress_Fix
 *
 * Tests for LearnPress + Elementor course context fix
 *
 * @package RCP_Content_Filter
 */

class Test_LearnPress_Fix extends WP_UnitTestCase {

	/**
	 * Test class initialization
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( 'RCF_LearnPress_Elementor_Fix' ) );
	}

	/**
	 * Test singleton pattern
	 */
	public function test_singleton() {
		$instance1 = RCF_LearnPress_Elementor_Fix::get_instance();
		$instance2 = RCF_LearnPress_Elementor_Fix::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test course context URL detection
	 */
	public function test_is_course_context() {
		$instance = RCF_LearnPress_Elementor_Fix::get_instance();

		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'is_course_context' );
		$method->setAccessible( true );

		// Simulate course context URL
		$_SERVER['REQUEST_URI'] = '/courses/my-course/lessons/lesson-1/';
		$this->assertTrue( $method->invoke( $instance ) );

		// Direct lesson URL (not course context)
		$_SERVER['REQUEST_URI'] = '/lessons/lesson-1/';
		$this->assertFalse( $method->invoke( $instance ) );

		// Regular page URL
		$_SERVER['REQUEST_URI'] = '/about-us/';
		$this->assertFalse( $method->invoke( $instance ) );
	}

	/**
	 * Test lesson ID extraction from URL
	 */
	public function test_get_lesson_id_from_url() {
		$instance = RCF_LearnPress_Elementor_Fix::get_instance();

		$reflection = new ReflectionClass( $instance );
		$method = $reflection->getMethod( 'get_lesson_id_from_url' );
		$method->setAccessible( true );

		// Create test lesson
		$lesson_id = $this->factory->post->create( array(
			'post_type'   => 'lp_lesson',
			'post_title'  => 'Test Lesson',
			'post_name'   => 'test-lesson',
			'post_status' => 'publish',
		) );

		// Simulate course context URL
		$_SERVER['REQUEST_URI'] = '/courses/my-course/lessons/test-lesson/';

		$found_id = $method->invoke( $instance );

		$this->assertEquals( $lesson_id, $found_id );

		// Clean up
		wp_delete_post( $lesson_id, true );
	}

	/**
	 * Test retake count removal from button text
	 */
	public function test_remove_retake_count() {
		$instance = RCF_LearnPress_Elementor_Fix::get_instance();

		// Test various formats
		$tests = array(
			'Retake Course (942)'       => 'Retake Course',
			'Retake Course ( 942 )'     => 'Retake Course',
			'Finish Course (123)'       => 'Finish Course',
			'Complete Course  (  45  )' => 'Complete Course',
			'Continue Learning'         => 'Continue Learning', // No change
		);

		foreach ( $tests as $input => $expected ) {
			$result = $instance->remove_retake_count_from_button( $input );
			$this->assertEquals( $expected, trim( $result ), "Failed for input: {$input}" );
		}
	}

	/**
	 * Test retake count removal from HTML
	 */
	public function test_remove_retake_count_from_html() {
		$instance = RCF_LearnPress_Elementor_Fix::get_instance();

		$html = '<button class="button-retake-course">Retake Course (942)</button>';
		$expected = '<button class="button-retake-course">Retake Course</button>';

		$result = $instance->remove_retake_count_from_html( $html );

		$this->assertEquals( $expected, trim( $result ) );
	}

	/**
	 * Test status information
	 */
	public function test_get_status() {
		$status = RCF_LearnPress_Elementor_Fix::get_status();

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'learnpress_active', $status );
		$this->assertArrayHasKey( 'elementor_active', $status );
		$this->assertArrayHasKey( 'elementor_pro_active', $status );
	}

	/**
	 * Test template redirect hook
	 */
	public function test_template_redirect_hook() {
		$this->assertTrue( has_action( 'template_redirect' ) !== false );
	}

	/**
	 * Test content filter hooks
	 */
	public function test_content_filter_hooks() {
		$this->assertTrue( has_filter( 'learn-press/course-button-text' ) !== false );
		$this->assertTrue( has_filter( 'the_content' ) !== false );
	}

	/**
	 * Test URL pattern matching
	 */
	public function test_url_pattern_matching() {
		$patterns = array(
			'/courses/abc/lessons/xyz/'           => true,
			'/courses/my-course/lessons/lesson-1' => true,
			'/lessons/standalone-lesson/'         => false,
			'/courses/abc/'                       => false,
			'/regular-page/'                      => false,
		);

		foreach ( $patterns as $url => $expected ) {
			$_SERVER['REQUEST_URI'] = $url;

			$instance = RCF_LearnPress_Elementor_Fix::get_instance();
			$reflection = new ReflectionClass( $instance );
			$method = $reflection->getMethod( 'is_course_context' );
			$method->setAccessible( true );

			$result = $method->invoke( $instance );

			$this->assertEquals( $expected, $result, "Failed for URL: {$url}" );
		}
	}
}
