<?php
/**
 * Fix LearnPress Course Context URLs to Work with Elementor Templates
 *
 * This solution ensures Elementor templates work with both:
 * - Direct lesson URLs: /lessons/lesson-name/
 * - Course context URLs: /courses/course-name/lessons/lesson-name/
 *
 * @package RCP_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * LearnPress Elementor Course Context Fix
 */
class RCF_LearnPress_Elementor_Fix {

    /**
     * Singleton instance
     *
     * @var RCF_LearnPress_Elementor_Fix
     */
    private static ?self $instance = null;

    /**
     * Current lesson post in course context
     *
     * @var WP_Post|null
     */
    private $current_lesson_post = null;

    /**
     * Current lesson ID in course context
     *
     * @var int|null
     */
    private $current_lesson_id = null;

    /**
     * Matched Elementor template ID
     *
     * @var int|null
     */
    private $matched_template_id = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Only initialize if LearnPress is active
        if ( ! class_exists( 'LearnPress' ) ) {
            return;
        }

        // Hook into Thim Elementor Kit's template system
        // Priority 5 to run before Thim loads templates
        add_action( 'template_redirect', array( $this, 'force_thim_template_in_course_context' ), 5 );

        // Remove retake count from button text - multiple filter points
        add_filter( 'learn-press/course-button-text', array( $this, 'remove_retake_count_from_button' ), 10, 2 );
        add_filter( 'the_content', array( $this, 'remove_retake_count_from_content' ), 999 );

        // Filter the actual button HTML output
        add_filter( 'learn-press/button-retake-html', array( $this, 'remove_retake_count_from_html' ), 999 );
        add_filter( 'learn-press/button-finish-html', array( $this, 'remove_retake_count_from_html' ), 999 );
        add_filter( 'learn-press/button-complete-html', array( $this, 'remove_retake_count_from_html' ), 999 );

        // Filter Thim Elementor Kit output
        add_filter( 'elementor/widget/render_content', array( $this, 'remove_retake_count_from_elementor_widget' ), 999, 2 );

        // Aggressive output buffering as last resort - start early, end late
        add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 999 );
    }

    /**
     * Force Thim Elementor Kit template to load in course context URLs
     *
     * This tells WordPress that we're viewing a singular lp_lesson post,
     * so Thim's template system will load the "single course item" template
     */
    public function force_thim_template_in_course_context() {
        // Check if we're in course context by URL pattern
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( strpos( $uri, '/courses/' ) === false || strpos( $uri, '/lessons/' ) === false ) {
            return;
        }

        // Extract lesson slug from URL
        if ( ! preg_match( '#/courses/[^/]+/lessons/([^/]+)#', $uri, $matches ) ) {
            return;
        }

        $lesson_slug = $matches[1];

        // Find the lesson post
        $lessons = get_posts( array(
            'post_type' => 'lp_lesson',
            'name' => $lesson_slug,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ) );

        if ( empty( $lessons ) ) {
            return;
        }

        $lesson = $lessons[0];

        // Override WordPress query to think this is a singular lesson page
        global $wp_query, $post;

        $post = $lesson;
        setup_postdata( $post );

        $wp_query->is_singular = true;
        $wp_query->is_single = true;
        $wp_query->is_page = false;
        $wp_query->is_404 = false;
        $wp_query->queried_object = $lesson;
        $wp_query->queried_object_id = $lesson->ID;
    }


    /**
     * Override condition post ID for Elementor template matching
     */
    public function override_condition_post_id( $post_id ) {
        if ( $this->current_lesson_id ) {
            return $this->current_lesson_id;
        }
        return $post_id;
    }

    /**
     * Maybe inject Elementor content into the_content
     */
    public function maybe_inject_elementor_content( $content ) {
        if ( ! $this->current_lesson_id || ! class_exists( '\Elementor\Plugin' ) ) {
            return $content;
        }

        // Check if lesson has Elementor content
        if ( ! $this->has_elementor_template( $this->current_lesson_id ) ) {
            return $content;
        }

        // Use the matched template ID if we found one, otherwise use the lesson ID
        $template_id = $this->matched_template_id ? $this->matched_template_id : $this->current_lesson_id;

        // Get and return Elementor content
        $elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id );

        return $elementor_content;
    }

    /**
     * Override Thim Elementor Kit template
     */
    public function override_thim_template( $template, $post_type ) {
        if ( ! $this->current_lesson_id ) {
            return $template;
        }

        // If Thim is trying to load a template for a course item, give it the lesson template
        if ( $post_type === 'lp_lesson' || $post_type === 'lp_course' ) {
            // Find the template for lp_lesson
            if ( class_exists( '\Elementor\Plugin' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
                $conditions_manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();
                $documents = $conditions_manager->get_documents_for_location( 'single' );

                foreach ( $documents as $document ) {
                    $conditions = $document->get_meta( '_elementor_conditions' );

                    if ( $this->check_conditions_match( $conditions, $this->current_lesson_id ) ) {
                        $template_id = $document->get_main_id();
                        return $template_id;
                    }
                }
            }
        }

        return $template;
    }

    /**
     * Maybe override the template file
     */
    public function maybe_override_template( $template ) {
        if ( ! $this->current_lesson_id ) {
            return $template;
        }

        // Check if we have an Elementor template for this lesson
        if ( ! $this->has_elementor_template( $this->current_lesson_id ) ) {
            return $template;
        }

        // Use Elementor's canvas template to render
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $canvas_template = ELEMENTOR_PATH . 'modules/page-templates/templates/canvas.php';
            if ( file_exists( $canvas_template ) ) {
                return $canvas_template;
            }
        }

        return $template;
    }

    /**
     * Override template post ID for Elementor
     */
    public function override_template_post_id( $template_id, $location ) {
        if ( ! $this->current_lesson_id ) {
            return $template_id;
        }

        return $this->current_lesson_id;
    }

    /**
     * Fix Elementor query results
     */
    public function fix_elementor_query( $query_results, $widget ) {
        if ( ! $this->is_course_context() ) {
            return $query_results;
        }

        return $query_results;
    }



    /**
     * Check if we're in course context (not direct lesson URL)
     *
     * @return bool
     */
    private function is_course_context(): bool {
        // Check URL pattern - course context URLs have /courses/ AND /lessons/ in them
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Match pattern: /courses/{course-slug}/lessons/{lesson-slug}
        $has_course_context = (
            strpos( $uri, '/courses/' ) !== false &&
            strpos( $uri, '/lessons/' ) !== false
        );

        return $has_course_context;
    }

    /**
     * Get lesson ID from course context URL
     *
     * @return int|null Lesson ID or null if not found
     */
    private function get_lesson_id_from_url() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Parse URL pattern: /courses/{course-slug}/lessons/{lesson-slug}/
        if ( preg_match( '#/courses/[^/]+/lessons/([^/]+)#', $uri, $matches ) ) {
            $lesson_slug = $matches[1];

            // Find lesson by slug
            $lessons = get_posts( array(
                'post_type' => 'lp_lesson',
                'name' => $lesson_slug,
                'posts_per_page' => 1,
                'post_status' => 'publish',
            ) );

            if ( ! empty( $lessons ) ) {
                $lesson_id = $lessons[0]->ID;
                return $lesson_id;
            }
        }

        return null;
    }



    /**
     * Check if lesson has Elementor template
     *
     * @param int $lesson_id Lesson ID.
     * @return bool
     */
    private function has_elementor_template( int $lesson_id ): bool {
        if ( ! class_exists( '\Elementor\Plugin' ) ) {
            return false;
        }

        // Check if built with Elementor directly
        $document = \Elementor\Plugin::$instance->documents->get( $lesson_id );
        if ( $document && $document->is_built_with_elementor() ) {
            return true;
        }

        // Check for Theme Builder template
        if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
            $conditions_manager = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager();

            // Try multiple locations
            $locations = array( 'single', 'archive', 'header', 'footer' );

            foreach ( $locations as $location ) {
                $documents = $conditions_manager->get_documents_for_location( $location );

                if ( ! empty( $documents ) ) {
                    foreach ( $documents as $document ) {
                        $conditions = $document->get_meta( '_elementor_conditions' );
                        $template_id = $document->get_main_id();

                        if ( $this->check_conditions_match( $conditions, $lesson_id ) ) {
                            // Store the matching template ID for later use
                            $this->matched_template_id = $template_id;
                            return true;
                        }
                    }
                }
            }
        }

        // Check for Thim Elementor Kit templates
        // Query for templates specifically for lp_lesson
        $thim_templates = get_posts( array(
            'post_type' => 'elementor_library',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_elementor_template_type',
                    'value' => 'single-lp_lesson',
                    'compare' => '=',
                ),
            ),
        ) );

        if ( ! empty( $thim_templates ) ) {
            foreach ( $thim_templates as $template ) {
                // Use the first matching template
                $this->matched_template_id = $template->ID;
                return true;
            }
        }

        // Try broader search for any "single" type template
        $single_templates = get_posts( array(
            'post_type' => 'elementor_library',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_elementor_template_type',
                    'value' => 'single',
                    'compare' => '=',
                ),
            ),
        ) );

        if ( ! empty( $single_templates ) ) {
            foreach ( $single_templates as $template ) {
                $conditions = get_post_meta( $template->ID, '_elementor_conditions', true );

                // Check if conditions include lp_lesson
                if ( is_array( $conditions ) ) {
                    foreach ( $conditions as $condition ) {
                        if ( strpos( $condition, 'lp_lesson' ) !== false ) {
                            $this->matched_template_id = $template->ID;
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if conditions match the lesson
     *
     * @param array $conditions Template conditions.
     * @param int   $lesson_id  Lesson ID.
     * @return bool
     */
    private function check_conditions_match( $conditions, int $lesson_id ): bool {
        if ( empty( $conditions ) ) {
            return false;
        }

        foreach ( $conditions as $condition ) {
            // Check for lesson post type conditions
            if ( in_array( $condition, array( 'singular/lp_lesson', 'singular/lesson' ), true ) ) {
                return true;
            }

            // Check for specific lesson ID
            if ( 'singular/lp_lesson/' . $lesson_id === $condition ) {
                return true;
            }
        }

        return false;
    }




    /**
     * Remove retake count from button text
     *
     * Removes the parentheses with retake count like "(942)" from button text
     *
     * @param string $text Button text
     * @param string $button_type Type of button (optional)
     * @return string Modified button text
     */
    public function remove_retake_count_from_button( $text, $button_type = '' ) {
        // Remove pattern like " (942)" or "(942)" from the text
        // Matches space + opening parenthesis + digits + closing parenthesis
        $text = preg_replace( '/\s*\(\s*\d+\s*\)\s*/', '', $text );

        return $text;
    }

    /**
     * Remove retake count from HTML output
     *
     * Filters the actual HTML of LearnPress buttons
     *
     * @param string $html Button HTML
     * @return string Modified HTML
     */
    public function remove_retake_count_from_html( $html ) {
        // Remove pattern from any button HTML
        $html = preg_replace( '/\s*\(\s*\d+\s*\)\s*/', '', $html );
        return $html;
    }

    /**
     * Remove retake count from Elementor widget content
     *
     * Specifically targets Thim Elementor Kit course buttons widget
     *
     * @param string $content Widget HTML content
     * @param object $widget Widget instance
     * @return string Modified content
     */
    public function remove_retake_count_from_elementor_widget( $content, $widget ) {
        // Only process Thim course buttons widget
        if ( strpos( $widget->get_name(), 'thim-ekits-course-buttons' ) !== false ) {
            // Remove retake count from button text
            $content = preg_replace( '/\s*\(\s*\d+\s*\)\s*/', '', $content );
        }

        return $content;
    }

    /**
     * Start output buffering to filter the entire page
     *
     * This is the most aggressive approach - captures entire page output
     */
    public function start_output_buffer() {
        // Only on LearnPress pages
        if ( ! is_singular( array( 'lp_course', 'lp_lesson', 'lp_quiz' ) ) ) {
            return;
        }

        ob_start( array( $this, 'filter_output_buffer' ) );
    }

    /**
     * Filter the entire page output buffer
     *
     * @param string $buffer Page HTML
     * @return string Modified HTML
     */
    public function filter_output_buffer( $buffer ) {
        // Remove retake count from all course button instances
        $buffer = preg_replace(
            '/(button-retake-course[^>]*>)\s*(?:Retake|Finish|Complete)\s+(?:course|Course)\s*\(\s*\d+\s*\)/i',
            '$1Retake course',
            $buffer
        );

        // More specific pattern for the exact HTML structure you showed
        $buffer = preg_replace(
            '/(<button[^>]*button-retake-course[^>]*>)\s*(.*?)\s*\(\s*\d+\s*\)\s*(<\/button>)/is',
            '$1$2$3',
            $buffer
        );

        return $buffer;
    }

    /**
     * Remove retake count from button text in content
     *
     * This catches any button text that might be rendered directly in content
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public function remove_retake_count_from_content( $content ) {
        // Only process on LearnPress course or lesson pages
        if ( ! is_singular( array( 'lp_course', 'lp_lesson', 'lp_quiz' ) ) ) {
            return $content;
        }

        // Pattern 1: Match course buttons in button tags
        // Handles: "Finish Course (942)", "Retake Course ( 942 )", "Complete Course (123)"
        $content = preg_replace(
            '/(<button[^>]*>)((?:Finish|Retake|Complete)\s+(?:Course|course))\s*\(\s*\d+\s*\)([^<]*<\/button>)/i',
            '$1$2$3',
            $content
        );

        // Pattern 2: Match course buttons in link tags
        $content = preg_replace(
            '/(<a[^>]*>)((?:Finish|Retake|Complete)\s+(?:Course|course))\s*\(\s*\d+\s*\)([^<]*<\/a>)/i',
            '$1$2$3',
            $content
        );

        // Pattern 3: Final catch-all for any remaining instances in plain text
        $content = preg_replace(
            '/((?:Finish|Retake|Complete)\s+(?:Course|course))\s*\(\s*\d+\s*\)/i',
            '$1',
            $content
        );

        return $content;
    }

    /**
     * Check if the fix is working properly
     *
     * @return array Status information
     */
    public static function get_status(): array {
        $status = array(
            'learnpress_active' => class_exists( 'LearnPress' ),
            'elementor_active' => class_exists( '\Elementor\Plugin' ),
            'elementor_pro_active' => class_exists( '\ElementorPro\Plugin' ),
            'lp_global_available' => class_exists( 'LP_Global' ),
            'hooks_registered' => false,
        );

        // Check if hooks are registered
        if ( self::$instance !== null ) {
            $status['hooks_registered'] = has_action( 'learn-press/before-single-item-summary' ) || has_filter( 'the_content' );
        }

        return $status;
    }
}
