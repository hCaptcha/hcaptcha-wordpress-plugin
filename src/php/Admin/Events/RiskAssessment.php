<?php
/**
 * The RiskAssessment class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Admin\Events;

/**
 * Events risk assessment helper.
 */
class RiskAssessment {
	/**
	 * Low risk level.
	 */
	public const LEVEL_LOW = 'low';

	/**
	 * Elevated risk level.
	 */
	public const LEVEL_ELEVATED = 'elevated';

	/**
	 * High risk level.
	 */
	public const LEVEL_HIGH = 'high';

	/**
	 * Critical risk level.
	 */
	public const LEVEL_CRITICAL = 'critical';

	/**
	 * Assess event risk.
	 *
	 * @param array $stats Risk stats.
	 *
	 * @return array
	 */
	public static function assess( array $stats ): array {
		$total = (int) ( $stats['total'] ?? 0 );

		if ( ! $total ) {
			return self::get_empty();
		}

		$spike_ratio = self::get_spike_ratio( $stats, $total );
		$components  = self::get_components( $stats, $total, $spike_ratio );
		$score       = self::get_score( $components, $spike_ratio );

		return [
			'score'      => (int) round( $score ),
			'level'      => self::get_level( $score ),
			'components' => $components,
		];
	}

	/**
	 * Get an empty risk assessment.
	 *
	 * @return array
	 */
	public static function get_empty(): array {
		return [
			'score'      => 0,
			'level'      => self::LEVEL_LOW,
			'components' => [
				'failed_rate'            => 0,
				'spike_ratio'            => 0,
				'ip_repeat_rate'         => 0,
				'user_agent_repeat_rate' => 0,
				'error_concentration'    => 0,
			],
		];
	}

	/**
	 * Convert risk level to Abilities attack likelihood.
	 *
	 * @param string $level Risk level.
	 *
	 * @return string
	 */
	public static function get_attack_likelihood( string $level ): string {
		if ( self::LEVEL_CRITICAL === $level ) {
			return self::LEVEL_CRITICAL;
		}

		if ( self::LEVEL_HIGH === $level ) {
			return self::LEVEL_HIGH;
		}

		if ( self::LEVEL_ELEVATED === $level ) {
			return 'medium';
		}

		return self::LEVEL_LOW;
	}

	/**
	 * Get dashboard rate.
	 *
	 * @param int $value Value.
	 * @param int $total Total.
	 *
	 * @return float
	 */
	public static function get_rate( int $value, int $total ): float {
		if ( ! $total ) {
			return 0;
		}

		return round( $value / $total * 100, 1 );
	}

	/**
	 * Get risk components.
	 *
	 * @param array $stats       Risk stats.
	 * @param int   $total       Total events.
	 * @param float $spike_ratio Spike ratio.
	 *
	 * @return array
	 */
	private static function get_components( array $stats, int $total, float $spike_ratio ): array {
		$ip_repeat_rate = self::get_repeat_rate(
			(int) ( $stats['ip_total'] ?? 0 ),
			(int) ( $stats['unique_ip'] ?? 0 )
		);
		$ua_repeat_rate = self::get_repeat_rate(
			(int) ( $stats['user_agent_total'] ?? 0 ),
			(int) ( $stats['unique_user_agent'] ?? 0 )
		);

		return [
			'failed_rate'            => self::get_rate( (int) ( $stats['failed'] ?? 0 ), $total ),
			'spike_ratio'            => round( $spike_ratio, 2 ),
			'ip_repeat_rate'         => $ip_repeat_rate,
			'user_agent_repeat_rate' => $ua_repeat_rate,
			'error_concentration'    => self::get_rate(
				(int) ( $stats['top_error_total'] ?? 0 ),
				(int) ( $stats['failed'] ?? 0 )
			),
		];
	}

	/**
	 * Get a risk score.
	 *
	 * @param array $components  Risk components.
	 * @param float $spike_ratio Spike ratio.
	 *
	 * @return float
	 */
	private static function get_score( array $components, float $spike_ratio ): float {
		$spike_score        = max( 0, min( 100, ( $spike_ratio - 1 ) * 40 ) );
		$concentration_rate = max( $components['ip_repeat_rate'], $components['user_agent_repeat_rate'] );

		return min(
			100,
			$components['failed_rate'] * 0.45 +
			$spike_score * 0.25 +
			$concentration_rate * 0.2 +
			$components['error_concentration'] * 0.1
		);
	}

	/**
	 * Get spike ratio.
	 *
	 * @param array $stats       Risk stats.
	 * @param int   $total       Total events.
	 *
	 * @return float
	 */
	private static function get_spike_ratio( array $stats, int $total ): float {
		$bucket_count = (int) ( $stats['bucket_count'] ?? 0 );
		$average      = $bucket_count ? $total / $bucket_count : $total;

		return $average > 0 ? (int) ( $stats['peak_total'] ?? 0 ) / $average : 0;
	}

	/**
	 * Get a repeat rate.
	 *
	 * @param int $total  Total.
	 * @param int $unique Unique.
	 *
	 * @return float
	 */
	private static function get_repeat_rate( int $total, int $unique ): float {
		if ( ! $total || $unique >= $total ) {
			return 0;
		}

		return self::get_rate( $total - $unique, $total );
	}

	/**
	 * Get risk level.
	 *
	 * @param float $score Score.
	 *
	 * @return string
	 */
	private static function get_level( float $score ): string {
		if ( $score >= 75 ) {
			return self::LEVEL_CRITICAL;
		}

		if ( $score >= 55 ) {
			return self::LEVEL_HIGH;
		}

		if ( $score >= 30 ) {
			return self::LEVEL_ELEVATED;
		}

		return self::LEVEL_LOW;
	}
}
