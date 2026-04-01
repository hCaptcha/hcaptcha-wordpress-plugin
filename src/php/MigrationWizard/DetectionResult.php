<?php
/**
 * DetectionResult class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\MigrationWizard;

/**
 * Class DetectionResult.
 *
 * Represents a single detected CAPTCHA surface.
 */
class DetectionResult {

	/**
	 * Confidence levels.
	 */
	public const CONFIDENCE_HIGH   = 'high';
	public const CONFIDENCE_MEDIUM = 'medium';
	public const CONFIDENCE_LOW    = 'low';

	/**
	 * Support statuses.
	 */
	public const STATUS_SUPPORTED   = 'supported';
	public const STATUS_UNSUPPORTED = 'unsupported';
	public const STATUS_UNKNOWN     = 'unknown';

	/**
	 * Provider identifier (recaptcha or turnstile).
	 *
	 * @var string
	 */
	private string $provider;

	/**
	 * Source plugin slug.
	 *
	 * @var string
	 */
	private string $source_plugin;

	/**
	 * Source plugin display name.
	 *
	 * @var string
	 */
	private string $source_name;

	/**
	 * Normalized surface type identifier.
	 *
	 * @var string
	 */
	private string $surface;

	/**
	 * Human-readable surface label.
	 *
	 * @var string
	 */
	private string $surface_label;

	/**
	 * Confidence level.
	 *
	 * @var string
	 */
	private string $confidence;

	/**
	 * HCaptcha support status.
	 *
	 * @var string
	 */
	private string $support_status;

	/**
	 * HCaptcha target setting key.
	 *
	 * @var string
	 */
	private string $hcaptcha_option_key;

	/**
	 * HCaptcha target setting sub-key.
	 *
	 * @var string
	 */
	private string $hcaptcha_option_value;

	/**
	 * Optional notes or warnings.
	 *
	 * @var string
	 */
	private string $notes;

	/**
	 * Constructor.
	 *
	 * @param array $args Detection result arguments.
	 */
	public function __construct( array $args = [] ) {
		$defaults = [
			'provider'              => '',
			'source_plugin'         => '',
			'source_name'           => '',
			'surface'               => '',
			'surface_label'         => '',
			'confidence'            => self::CONFIDENCE_LOW,
			'support_status'        => self::STATUS_UNKNOWN,
			'hcaptcha_option_key'   => '',
			'hcaptcha_option_value' => '',
			'notes'                 => '',
		];

		$args = array_merge( $defaults, $args );

		$this->provider              = $args['provider'];
		$this->source_plugin         = $args['source_plugin'];
		$this->source_name           = $args['source_name'];
		$this->surface               = $args['surface'];
		$this->surface_label         = $args['surface_label'];
		$this->confidence            = $args['confidence'];
		$this->support_status        = $args['support_status'];
		$this->hcaptcha_option_key   = $args['hcaptcha_option_key'];
		$this->hcaptcha_option_value = $args['hcaptcha_option_value'];
		$this->notes                 = $args['notes'];
	}

	/**
	 * Get provider.
	 *
	 * @return string
	 */
	public function get_provider(): string {
		return $this->provider;
	}

	/**
	 * Get source plugin slug.
	 *
	 * @return string
	 */
	public function get_source_plugin(): string {
		return $this->source_plugin;
	}

	/**
	 * Get source plugin display name.
	 *
	 * @return string
	 */
	public function get_source_name(): string {
		return $this->source_name;
	}

	/**
	 * Get surface identifier.
	 *
	 * @return string
	 */
	public function get_surface(): string {
		return $this->surface;
	}

	/**
	 * Get surface label.
	 *
	 * @return string
	 */
	public function get_surface_label(): string {
		return $this->surface_label;
	}

	/**
	 * Get confidence level.
	 *
	 * @return string
	 */
	public function get_confidence(): string {
		return $this->confidence;
	}

	/**
	 * Get support status.
	 *
	 * @return string
	 */
	public function get_support_status(): string {
		return $this->support_status;
	}

	/**
	 * Get hCaptcha option key.
	 *
	 * @return string
	 */
	public function get_hcaptcha_option_key(): string {
		return $this->hcaptcha_option_key;
	}

	/**
	 * Get hCaptcha option value (sub-key).
	 *
	 * @return string
	 */
	public function get_hcaptcha_option_value(): string {
		return $this->hcaptcha_option_value;
	}

	/**
	 * Get notes.
	 *
	 * @return string
	 */
	public function get_notes(): string {
		return $this->notes;
	}

	/**
	 * Whether this result is migratable.
	 *
	 * @return bool
	 */
	public function is_migratable(): bool {
		return self::STATUS_SUPPORTED === $this->support_status
			&& '' !== $this->hcaptcha_option_key
			&& '' !== $this->hcaptcha_option_value;
	}

	/**
	 * Convert to array for JSON serialization.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'provider'              => $this->provider,
			'source_plugin'         => $this->source_plugin,
			'source_name'           => $this->source_name,
			'surface'               => $this->surface,
			'surface_label'         => $this->surface_label,
			'confidence'            => $this->confidence,
			'support_status'        => $this->support_status,
			'hcaptcha_option_key'   => $this->hcaptcha_option_key,
			'hcaptcha_option_value' => $this->hcaptcha_option_value,
			'notes'                 => $this->notes,
			'is_migratable'         => $this->is_migratable(),
		];
	}

	/**
	 * Create from an array.
	 *
	 * @param array $data Data array.
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self( $data );
	}
}
