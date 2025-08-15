<?php
/*
Plugin Name: TEST TASK
Description: Displays a data entry form via shortcode [tt_form], and sends it to an external handler.
Version: 1.0.0
Author: Razmik Hovhannisyan
*/

if( ! defined( 'ABSPATH' ) ) {
    exit;
}

if( ! class_exists( 'TEST_TASK' ) ) {
    class TEST_TASK {
        const NONCE_ACTION      = 'tt_submit';
        const NONCE_FIELD       = 'tt_nonce';
        const ACTION_SLUG       = 'tt_submit';
        const DEFAULT_TIMEOUT   = 8;
        const OPTION_AUTH_TOKEN = 'tt_auth_token';

        public function __construct() {
            add_shortcode( 'tt_form', [ $this, 'render_form_shortcode' ] );

            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

            add_action( 'wp_ajax_nopriv_' . self::ACTION_SLUG, [ $this, 'handle_submit_ajax' ] );
            add_action( 'wp_ajax_' . self::ACTION_SLUG, [ $this, 'handle_submit_ajax' ] );

            register_activation_hook( __FILE__, [ __CLASS__, 'on_activate' ] );
        }

        public static function on_activate() {
            if( ! get_option( self::OPTION_AUTH_TOKEN ) ) {
                $token = wp_generate_password( 32, false, false );
                add_option( self::OPTION_AUTH_TOKEN, $token );
            }
        }

        private function get_handler_url() {
            return str_replace( 'https://', 'http://', site_url( '/test_task_external_handler/index.php' ) );
        }

        private function get_auth_token() {
            return get_option( self::OPTION_AUTH_TOKEN );
        }

        public function enqueue_assets() {
            wp_enqueue_style( 'test-task-style', plugins_url( 'assets/css/test_task.css', __FILE__ ), [], '1.0.0' );
            wp_enqueue_script( 'test-task-script', plugins_url( 'assets/js/test_task.js', __FILE__ ), [], '1.0.0', true );
        }

        public function render_form_shortcode() {
            ob_start();
            $action_url  = esc_url( admin_url( 'admin-ajax.php' ) );
            $nonce       = wp_create_nonce( self::NONCE_ACTION );
            $handler_url = $this->get_handler_url();

            ?>
            <form method="post" action="<?php echo $action_url; ?>" id="tt-data-form" novalidate>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SLUG ); ?>">
                <input type="hidden" name="<?php echo esc_attr( self::NONCE_FIELD ); ?>" value="<?php echo esc_attr( $nonce ); ?>">
                <input type="hidden" name="start_time" id="tt-start-time" value="">

                <div class="tt-field">
                    <label for="tt-first-name">First Name</label>
                    <input type="text" id="tt-first-name" name="first_name" required>
                </div>
                <div class="tt-field">
                    <label for="tt-last-name">Last Name</label>
                    <input type="text" id="tt-last-name" name="last_name" required>
                </div>
                <div class="tt-field">
                    <label for="tt-email">Email</label>
                    <input type="email" id="tt-email" name="email" required>
                </div>
                <div class="tt-field">
                    <label for="tt-phone">Phone Number</label>
                    <input type="tel" id="tt-phone" name="phone" required>
                </div>
                <div class="tt-field">
                    <label for="tt-address">Address</label>
                    <input type="text" id="tt-address" name="address" required maxlength="255">
                </div>

                <p class="tt-field" style="margin-top:10px;">
                    <button type="submit">Submit</button>
                </p>
                <p class="tt-note">Data will be sent to: <?php echo esc_html( $handler_url ); ?></p>
            </form>
            <?php
            return ob_get_clean();
        }

        private function calc_elapsed_time( $start_time ) {
            $elapsed = 0.0;
            if( $start_time > 0 ) {
                $elapsed = max( 0, microtime( true ) - $start_time );
            }

            return $elapsed;
        }

        public function handle_submit_ajax() {
            if( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
                wp_send_json_error( [ 'message' => 'Security check failed.', 'time' => 0 ], 400 );
            }

            $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
            $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
            $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $phone      = isset( $_POST['phone'] ) ? preg_replace( '/[^0-9+()\-\s]/', '', wp_unslash( $_POST['phone'] ) ) : '';
            $address    = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
            $start_time = isset( $_POST['start_time'] ) ? floatval( $_POST['start_time'] ) : 0.0;

            $errors = [];
            if( $first_name === '' ) $errors[] = 'First name is required.';
            if( $last_name === '' ) $errors[] = 'Last name is required.';
            if( $email === '' || ! is_email( $email ) ) $errors[] = 'Valid email is required.';
            if( $phone === '' || ! preg_match( '/^[+]?[- 0-9()]{7,20}$/', $phone ) ) $errors[] = 'Valid phone is required.';
            if( $address === '' ) $errors[] = 'Address is required.';

            if( ! empty( $errors ) ) {
                $elapsed = $this->calc_elapsed_time( $start_time );
                wp_send_json_error( [ 'message' => 'Validation error: ' . implode( ' ', $errors ), 'time' => number_format( (float) $elapsed, 2 ) ], 422 );
            }

            $payload = [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'address'    => $address,
            ];

            $handler_url = $this->get_handler_url();
            $auth_token  = $this->get_auth_token();

            $json = wp_json_encode( $payload );
            $args = [
                    'method'  => 'POST',
                    'headers' => [
                            'Content-Type' => 'application/json',
                            'X-Auth-Token' => $auth_token,
                    ],
                    'body'    => $json,
                    'timeout' => self::DEFAULT_TIMEOUT,
            ];

            $error_msg = '';
            $sent_ok   = false;
            $response  = wp_remote_post( $handler_url, $args );
            if( is_wp_error( $response ) ) {
                $error_msg = 'Request error: ' . $response->get_error_message();
            } else {
                $http_code     = (int) wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body( $response );
                if( $http_code >= 200 && $http_code < 300 ) {
                    $resp = json_decode( $response_body, true );
                    if( is_array( $resp ) && isset( $resp['status'] ) && $resp['status'] === 'ok' ) {
                        $sent_ok = true;
                    } else {
                        $error_msg = 'Handler returned unexpected response.';
                    }
                } else {
                    $error_msg = 'Handler HTTP error ' . $http_code . '.';
                }
            }

            $elapsed = $this->calc_elapsed_time( $start_time );

            if( $sent_ok ) {
                wp_send_json_success( [ 'message' => 'Thank you! Your information has been submitted successfully.', 'time' => number_format( (float) $elapsed, 2 ) ], 201 );
            } else {
                wp_send_json_error( [ 'message' => 'We are sorry, something went wrong during submission. ' . $error_msg, 'time' => number_format( (float) $elapsed, 2 ) ], 500 );
            }
        }
    }
}

new TEST_TASK();
