<?php
/**
 * SignupNativeStagesTest class file.
 *
 * @package HCaptcha\Tests
 */

namespace HCaptcha\Tests\Integration\WP;

use HCaptcha\Helpers\HCaptcha;
use HCaptcha\Tests\Integration\HCaptchaWPTestCase;
use HCaptcha\WP\Signup;
use WP_Error;

/**
 * Test the native multisite signup validation stages.
 *
 * @group wp-signup-native-stages
 * @group wp-signup
 * @group wp
 */
class SignupNativeStagesTest extends HCaptchaWPTestCase {

	/**
	 * Native signup form identity.
	 */
	private const ID = [
		'source'  => [ 'WordPress' ],
		'form_id' => 'signup',
	];

	/**
	 * Module definitions that existed before the test.
	 *
	 * @var array
	 */
	private array $previous_modules = [];

	/**
	 * Integration under test.
	 *
	 * @var Signup|null
	 */
	private ?Signup $subject = null;

	/**
	 * Output buffer depth that existed before the test.
	 *
	 * @var int
	 */
	private int $buffer_level = 0;

	/**
	 * Server-selected identities observed at completed API verification.
	 *
	 * @var array
	 */
	private array $verification_ids = [];

	/**
	 * Number of outgoing siteverify requests.
	 *
	 * @var int
	 */
	private int $siteverify_requests = 0;

	/**
	 * Register the native multisite module identity in the single-site fixture.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->previous_modules = hcaptcha()->modules;
		$this->buffer_level     = ob_get_level();

		hcaptcha()->modules['Signup Form'] = [ [ 'wp_status', 'signup' ], '', Signup::class ];
		wp_set_current_user( 0 );
		add_filter( 'hcap_verify_request', [ $this, 'observe_verification' ], PHP_INT_MAX, 3 );
		add_filter( 'pre_http_request', [ $this, 'observe_siteverify' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Restore native signup hooks, ownership, modules, and output buffers.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( null !== $this->subject ) {
			remove_action( 'before_signup_form', [ $this->subject, 'before_signup_form' ] );
			remove_action( 'after_signup_form', [ $this->subject, 'add_captcha' ] );
			remove_filter( 'wpmu_validate_user_signup', [ $this->subject, 'verify' ] );
			remove_filter( 'wpmu_validate_blog_signup', [ $this->subject, 'verify' ] );
		}

		while ( ob_get_level() > $this->buffer_level ) {
			ob_end_clean();
		}

		unset( $GLOBALS['wp_actions']['before_signup_form'] );
		remove_filter( 'hcap_verify_request', [ $this, 'observe_verification' ], PHP_INT_MAX );
		remove_filter( 'pre_http_request', [ $this, 'observe_siteverify' ], PHP_INT_MAX );
		hcaptcha()->modules = $this->previous_modules;
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * An authenticated Create Site request must carry the signed native widget.
	 *
	 * @return void
	 */
	public function test_another_site_rejects_missing_widget(): void {
		wp_set_current_user( $this->factory()->user->create() );
		$_POST['stage'] = 'gimmeanotherblog';
		$this->native_subject();

		$result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( 'Bad hCaptcha signature!', $result['errors']->get_error_message( 'bad-signature' ) );
	}

	/**
	 * A signed authenticated Create Site form still requires a challenge token.
	 *
	 * @return void
	 */
	public function test_another_site_rejects_missing_token(): void {
		wp_set_current_user( $this->factory()->user->create() );
		$_POST['stage'] = 'gimmeanotherblog';
		$this->prepare_verify_post( 'hcaptcha_signup_nonce', 'hcaptcha_signup' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::ID );
		unset( $_POST['h-captcha-response'] );
		$this->native_subject();

		$result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( 'Please complete the hCaptcha.', $result['errors']->get_error_message( 'empty' ) );
	}

	/**
	 * All native stages accept a valid challenge bound to the native signup form.
	 *
	 * @param string $stage     Native submitted stage.
	 * @param string $filter    Native final validation filter.
	 * @param bool   $logged_in Whether the native flow requires an existing user.
	 *
	 * @return void
	 * @dataProvider dp_native_stages
	 */
	public function test_native_stages_accept_valid_challenge( string $stage, string $filter, bool $logged_in ): void {
		if ( $logged_in ) {
			wp_set_current_user( $this->factory()->user->create() );
		}

		$this->prepare_native_post( $stage );
		$subject = $this->native_subject();
		$input   = $this->native_result( $filter );
		$result  = apply_filters( $filter, $input );

		self::assertSame( $input, $result );
		self::assertFalse( $result['errors']->has_errors() );
		self::assertSame( [ self::ID ], $this->verification_ids );
		self::assertSame( 1, $this->siteverify_requests );
		self::assertSame( '', $subject->get_error_html() );
	}

	/**
	 * Get the native stages and their final validation filters.
	 *
	 * @return array
	 */
	public function dp_native_stages(): array {
		return [
			'user signup'   => [ 'validate-user-signup', 'wpmu_validate_user_signup', false ],
			'user and site' => [ 'validate-blog-signup', 'wpmu_validate_blog_signup', false ],
			'another site'  => [ 'gimmeanotherblog', 'wpmu_validate_blog_signup', true ],
		];
	}

	/**
	 * Combined signup checks the validated user email at the final blog stage.
	 *
	 * @param bool $blocked Whether the validated address is disposable.
	 *
	 * @return void
	 * @dataProvider dp_email_policy
	 */
	public function test_combined_signup_checks_validated_email( bool $blocked ): void {
		$email = $blocked ? 'native@blocked.test' : 'native@allowed.test';
		$this->prepare_native_post( 'validate-blog-signup' );
		$this->native_subject();
		hcaptcha()->settings()->set( 'disposable_email', [ 'on' ] );
		$_POST['user_email']      = 'forged@allowed.test';
		$user_input               = $this->native_result( 'wpmu_validate_user_signup' );
		$user_input['user_email'] = $email;
		$checked                  = [];

		add_filter(
			'hcap_is_disposable_email',
			static function ( $disposable, string $address ) use ( &$checked ): bool {
				$checked[] = $address;

				return 'native@blocked.test' === $address;
			},
			10,
			2
		);
		self::assertSame( $user_input, apply_filters( 'wpmu_validate_user_signup', $user_input ) );
		$result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( $blocked, $result['errors']->has_errors() );
		self::assertSame( $blocked ? 'Please use a permanent email address.' : '', $result['errors']->get_error_message( 'disposable-email' ) );
		self::assertSame( [ $email ], $checked );
		self::assertSame( $blocked ? 0 : 1, $this->siteverify_requests );
	}

	/**
	 * Provide native email policy outcomes.
	 *
	 * @return array
	 */
	public function dp_email_policy(): array {
		return [
			'blocked address' => [ true ],
			'allowed address' => [ false ],
		];
	}

	/**
	 * Another-site requests reject missing, invalid, or foreign signed metadata.
	 *
	 * @param array|string $widget_id Submitted widget identity or invalid value.
	 *
	 * @return void
	 * @dataProvider dp_invalid_widget_ids
	 */
	public function test_another_site_rejects_invalid_widget( $widget_id ): void {
		wp_set_current_user( $this->factory()->user->create() );
		$this->prepare_native_post( 'gimmeanotherblog' );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = is_array( $widget_id ) ? HCaptcha::widget_id_value( $widget_id ) : $widget_id;
		$this->native_subject();

		$result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( 'Bad hCaptcha signature!', $result['errors']->get_error_message( 'bad-signature' ) );
		self::assertSame( [ self::ID ], $this->verification_ids );
		self::assertSame( 0, $this->siteverify_requests );
	}

	/**
	 * Get invalid widget metadata cases.
	 *
	 * @return array
	 */
	public function dp_invalid_widget_ids(): array {
		return [
			'missing widget'      => [ '' ],
			'invalid signature'   => [ 'invalid-widget-signature' ],
			'foreign source'      => [
				[
					'source'  => [ 'woocommerce/woocommerce.php' ],
					'form_id' => 'signup',
				],
			],
			'foreign native form' => [
				[
					'source'  => [ 'WordPress' ],
					'form_id' => 'register',
				],
			],
		];
	}

	/**
	 * Another-site requests reject challenges rejected by siteverify.
	 *
	 * @return void
	 */
	public function test_another_site_rejects_invalid_token(): void {
		wp_set_current_user( $this->factory()->user->create() );
		$this->prepare_native_post( 'gimmeanotherblog', false );
		$this->native_subject();

		$result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( 'The hCaptcha is invalid.', $result['errors']->get_error_message( 'fail' ) );
		self::assertSame( [ self::ID ], $this->verification_ids );
		self::assertSame( 1, $this->siteverify_requests );
	}

	/**
	 * Existing-user site creation requires the nonce for that user and form.
	 *
	 * @param bool $missing Whether the nonce is absent instead of incorrect.
	 *
	 * @return void
	 * @dataProvider dp_invalid_nonces
	 */
	public function test_another_site_rejects_invalid_logged_in_nonce( bool $missing ): void {
		wp_set_current_user( $this->factory()->user->create() );
		$this->prepare_native_post( 'gimmeanotherblog' );

		if ( $missing ) {
			unset( $_POST['hcaptcha_signup_nonce'] );
		} else {
			$_POST['hcaptcha_signup_nonce'] = wp_create_nonce( 'hcaptcha_registration' );
		}

		$this->native_subject();
		$result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( 'Bad hCaptcha nonce!', $result['errors']->get_error_message( 'bad-nonce' ) );
		self::assertSame( [ self::ID ], $this->verification_ids );
		self::assertSame( 0, $this->siteverify_requests );
	}

	/**
	 * Get invalid nonce cases.
	 *
	 * @return array
	 */
	public function dp_invalid_nonces(): array {
		return [
			'missing nonce'      => [ true ],
			'another form nonce' => [ false ],
		];
	}

	/**
	 * Anonymous combined signup verifies only at the final blog validation hook.
	 *
	 * @param bool $success Whether siteverify accepts the challenge.
	 *
	 * @return void
	 * @dataProvider dp_challenge_results
	 */
	public function test_combined_signup_verifies_once_at_final_blog_filter( bool $success ): void {
		$this->prepare_native_post( 'validate-blog-signup', $success );
		$this->native_subject();
		$user_input = $this->native_result( 'wpmu_validate_user_signup' );

		$user_result = apply_filters( 'wpmu_validate_user_signup', $user_input );

		self::assertSame( $user_input, $user_result );
		self::assertSame( [], $this->verification_ids );
		self::assertSame( 0, $this->siteverify_requests );

		$blog_result = apply_filters( 'wpmu_validate_blog_signup', $this->native_result() );

		self::assertSame( ! $success, $blog_result['errors']->has_errors() );
		self::assertSame( $success ? '' : 'The hCaptcha is invalid.', $blog_result['errors']->get_error_message( 'fail' ) );
		self::assertSame( [ self::ID ], $this->verification_ids );
		self::assertSame( 1, $this->siteverify_requests );
	}

	/**
	 * Native errors and accepted site data survive both challenge outcomes.
	 *
	 * @param bool $success Whether siteverify accepts the challenge.
	 *
	 * @return void
	 * @dataProvider dp_challenge_results
	 */
	public function test_another_site_preserves_native_errors_and_site_data( bool $success ): void {
		wp_set_current_user( $this->factory()->user->create() );
		$this->prepare_native_post( 'gimmeanotherblog', $success );
		$this->native_subject();
		$input = $this->native_result();
		$input['errors']->add( 'blogname', 'This site name is already taken.' );

		$result = apply_filters( 'wpmu_validate_blog_signup', $input );

		self::assertSame( $input, $result );
		self::assertSame( 'This site name is already taken.', $result['errors']->get_error_message( 'blogname' ) );
		self::assertSame( $success ? [ 'blogname' ] : [ 'blogname', 'fail' ], $result['errors']->get_error_codes() );
		self::assertSame( [ self::ID ], $this->verification_ids );
	}

	/**
	 * Get challenge outcomes.
	 *
	 * @return array
	 */
	public function dp_challenge_results(): array {
		return [
			'valid challenge'   => [ true ],
			'invalid challenge' => [ false ],
		];
	}

	/**
	 * Trusted native form policy can exempt a matching signed signup identity.
	 *
	 * @return void
	 */
	public function test_trusted_native_form_exemption_is_preserved(): void {
		wp_set_current_user( $this->factory()->user->create() );
		$this->prepare_native_post( 'gimmeanotherblog' );
		unset( $_POST['hcaptcha_signup_nonce'], $_POST['h-captcha-response'] );
		add_filter(
			'hcap_protect_form',
			static function ( $protect, array $source, $form_id ) {
				return self::ID['source'] === $source && self::ID['form_id'] === $form_id ? false : $protect;
			},
			PHP_INT_MAX,
			3
		);
		$this->native_subject();
		$input  = $this->native_result();
		$result = apply_filters( 'wpmu_validate_blog_signup', $input );

		self::assertSame( $input, $result );
		self::assertFalse( $result['errors']->has_errors() );
		self::assertSame( [ self::ID ], $this->verification_ids );
		self::assertSame( 0, $this->siteverify_requests );
	}

	/**
	 * Foreign ownership, malformed stages, and unrelated filters remain untouched.
	 *
	 * @param array|string|null $stage Submitted stage, or null when absent.
	 * @param string            $filter Validation filter.
	 * @param bool              $owned Whether the native signup entrypoint ran.
	 *
	 * @return void
	 * @dataProvider dp_unrelated_requests
	 */
	public function test_unrelated_requests_skip_verification( $stage, string $filter, bool $owned ): void {
		if ( null !== $stage ) {
			$_POST['stage'] = $stage;
		}

		if ( $owned ) {
			$this->native_subject();
		} else {
			$this->subject = new Signup();
		}

		$input = $this->native_result( $filter );
		$input['errors']->add( 'native_error', 'Existing native error.' );
		$result = apply_filters( $filter, $input );

		self::assertSame( $input, $result );
		self::assertSame( [ 'native_error' ], $result['errors']->get_error_codes() );
		self::assertSame( [], $this->verification_ids );
		self::assertSame( 0, $this->siteverify_requests );
		self::assertSame( '', $this->subject->get_error_html() );
	}

	/**
	 * Get requests outside native final-stage validation.
	 *
	 * @return array
	 */
	public function dp_unrelated_requests(): array {
		return [
			'missing stage'        => [ null, 'wpmu_validate_user_signup', true ],
			'empty stage'          => [ '', 'wpmu_validate_blog_signup', true ],
			'unknown stage'        => [ 'custom-signup', 'wpmu_validate_blog_signup', true ],
			'partial stage'        => [ 'signup', 'wpmu_validate_blog_signup', true ],
			'empty array stage'    => [ [], 'wpmu_validate_blog_signup', true ],
			'array stage'          => [ [ 'gimmeanotherblog' ], 'wpmu_validate_blog_signup', true ],
			'form context'         => [ 'create-another-site', 'wpmu_validate_blog_signup', true ],
			'site context'         => [ 'validate-site', 'wpmu_validate_blog_signup', true ],
			'user at blog filter'  => [ 'validate-user-signup', 'wpmu_validate_blog_signup', true ],
			'blog at user filter'  => [ 'validate-blog-signup', 'wpmu_validate_user_signup', true ],
			'site at user filter'  => [ 'gimmeanotherblog', 'wpmu_validate_user_signup', true ],
			'foreign user form'    => [ 'validate-user-signup', 'wpmu_validate_user_signup', false ],
			'foreign blog form'    => [ 'validate-blog-signup', 'wpmu_validate_blog_signup', false ],
			'foreign another site' => [ 'gimmeanotherblog', 'wpmu_validate_blog_signup', false ],
		];
	}

	/**
	 * Observe API verification without replacing its validation result.
	 *
	 * @param string|null $result     Verification result.
	 * @param array       $codes      Verification error codes.
	 * @param object      $error_info Server-selected identity and verification data.
	 *
	 * @return string|null
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function observe_verification( ?string $result, array $codes, object $error_info ): ?string {
		$this->verification_ids[] = $error_info->expected_id;

		return $result;
	}

	/**
	 * Observe remote verification while retaining the local HTTP fixture response.
	 *
	 * @param mixed  $preempt HTTP fixture response.
	 * @param array  $args    HTTP request arguments.
	 * @param string $url     Requested URL.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function observe_siteverify( $preempt, array $args, string $url ) {
		if ( 'https://api.hcaptcha.com/siteverify' === $url ) {
			++$this->siteverify_requests;
		}

		return $preempt;
	}

	/**
	 * Prepare the native fields and a locally answered challenge request.
	 *
	 * @param string $stage   Native submitted stage.
	 * @param bool   $success Whether siteverify accepts the challenge.
	 *
	 * @return void
	 */
	private function prepare_native_post( string $stage, bool $success = true ): void {
		$_POST['stage']       = $stage;
		$_POST['user_name']   = 'nativeuser';
		$_POST['user_email']  = 'native@example.org';
		$_POST['blogname']    = 'native-stage';
		$_POST['blog_title']  = 'Native stage site';
		$_POST['blog_public'] = '0';

		$this->prepare_verify_post( 'hcaptcha_signup_nonce', 'hcaptcha_signup', $success );
		$_POST[ HCaptcha::HCAPTCHA_WIDGET_ID ] = HCaptcha::widget_id_value( self::ID );
	}

	/**
	 * Record native entrypoint ownership before registering the buffering hooks.
	 *
	 * @return Signup
	 */
	private function native_subject(): Signup {
		do_action( 'before_signup_form' );
		$this->subject = new Signup();

		self::assertSame( self::ID['source'], HCaptcha::get_class_source( Signup::class ) );

		return $this->subject;
	}

	/**
	 * Get an otherwise accepted native validation result.
	 *
	 * @param string $filter Native validation filter.
	 *
	 * @return array
	 */
	private function native_result( string $filter = 'wpmu_validate_blog_signup' ): array {
		if ( 'wpmu_validate_user_signup' === $filter ) {
			return [
				'user_name'     => 'nativeuser',
				'orig_username' => 'nativeuser',
				'user_email'    => 'native@example.org',
				'errors'        => new WP_Error(),
			];
		}

		return [
			'domain'     => 'example.org',
			'path'       => '/native-stage/',
			'blogname'   => 'native-stage',
			'blog_title' => 'Native stage site',
			'errors'     => new WP_Error(),
		];
	}
}
