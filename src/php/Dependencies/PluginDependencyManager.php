<?php
/**
 * The PluginDependencyManager class file.
 *
 * @package hcaptcha-wp
 */

namespace HCaptcha\Dependencies;

/**
 * Resolve plugin dependencies and prepare safe deactivation plans.
 *
 * The additional dependency map is deliberately injected so the class can be
 * reused by projects that need to supplement the Requires Plugins header.
 */
class PluginDependencyManager {

	/**
	 * Installed plugins, keyed by the plugin file.
	 *
	 * @var array[]
	 */
	private array $plugins;

	/**
	 * Additional dependencies, keyed by a plugin file or another consumer id.
	 *
	 * @var array
	 */
	private array $additional_dependencies;

	/**
	 * Optional plugin data loader.
	 *
	 * @var callable|null
	 */
	private $plugin_data_loader;

	/**
	 * Constructor.
	 *
	 * @param array         $plugins                 Installed plugins, keyed by plugin file.
	 * @param array         $additional_dependencies Additional dependency map.
	 * @param callable|null $plugin_data_loader      Optional plugin data loader.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function __construct(
		array $plugins,
		array $additional_dependencies = [],
		$plugin_data_loader = null
	) {
		$this->plugins                 = $plugins;
		$this->additional_dependencies = $additional_dependencies;
		$this->plugin_data_loader      = is_callable( $plugin_data_loader ) ? $plugin_data_loader : null;
	}

	/**
	 * Get all declared dependencies for a plugin.
	 *
	 * @param string $plugin Plugin file.
	 *
	 * @return string[]
	 */
	public function get_dependencies( string $plugin ): array {
		$plugin_data      = $this->get_plugin_data( $plugin );
		$requires_plugins = $plugin_data['RequiresPlugins'] ?? '';
		$header_slugs     = is_array( $requires_plugins )
			? $requires_plugins
			: explode( ',', (string) $requires_plugins );
		$dependencies     = $this->plugin_dirs_to_files( $header_slugs );

		return array_values(
			array_unique(
				array_merge( $dependencies, $this->get_additional_dependencies( $plugin ) )
			)
		);
	}

	/**
	 * Get configured dependencies for any consumer.
	 *
	 * @param string $consumer Consumer id.
	 *
	 * @return string[]
	 */
	public function get_additional_dependencies( string $consumer ): array {
		$dependencies = (array) ( $this->additional_dependencies[ $consumer ] ?? [] );

		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $dependencies )
				)
			)
		);
	}

	/**
	 * Get a plugin display name.
	 *
	 * @param string $plugin Plugin file.
	 *
	 * @return string
	 */
	public function get_plugin_name( string $plugin ): string {
		$plugin_data = $this->get_plugin_data( $plugin );
		$name        = $plugin_data['Name'] ?? '';

		return $name ?: basename( $plugin, '.php' );
	}

	/**
	 * Build a plan of dependencies that will be activated with the roots.
	 *
	 * @param string[] $roots          Root plugin files.
	 * @param string[] $active_plugins Active plugin files.
	 * @param bool     $include_roots  Include roots as dependency items.
	 *
	 * @return array
	 */
	public function get_activation_plan(
		array $roots,
		array $active_plugins,
		bool $include_roots = false
	): array {
		$roots          = $this->normalize_plugins( $roots );
		$active_plugins = $this->normalize_plugins( $active_plugins );
		$depths         = $this->get_dependency_depths( $roots );
		$candidates     = $include_roots ? array_keys( $depths ) : array_diff( array_keys( $depths ), $roots );
		$candidates     = array_values( array_diff( $candidates, $active_plugins ) );
		$items          = [];

		foreach ( $candidates as $plugin ) {
			$depth   = $depths[ $plugin ] ?? 0;
			$items[] = [
				'plugin' => $plugin,
				'name'   => $this->get_plugin_name( $plugin ),
				'depth'  => $include_roots ? $depth : max( 0, $depth - 1 ),
			];
		}

		return [ 'items' => $items ];
	}

	/**
	 * Build a safe deactivation plan.
	 *
	 * @param string[] $roots              Root plugin files.
	 * @param string[] $active_plugins     Active plugin files.
	 * @param bool     $include_roots      Include roots as selectable plan items.
	 * @param array    $external_consumers Additional active consumers and their dependencies.
	 *
	 * @return array
	 */
	public function get_deactivation_plan(
		array $roots,
		array $active_plugins,
		bool $include_roots = false,
		array $external_consumers = []
	): array {
		$roots          = $this->normalize_plugins( $roots );
		$active_plugins = $this->normalize_plugins( $active_plugins );

		if ( ! $include_roots ) {
			$roots = array_values( array_intersect( $roots, $active_plugins ) );
		}

		$depths        = $this->get_dependency_depths( $roots );
		$tree_plugins  = array_keys( $depths );
		$candidates    = $include_roots ? $tree_plugins : array_diff( $tree_plugins, $roots );
		$candidates    = array_values( array_intersect( $candidates, $active_plugins ) );
		$outside       = array_values( array_diff( $active_plugins, $tree_plugins ) );
		$blockers      = $this->find_blockers( $candidates, $outside, $external_consumers );
		$root_blockers = $include_roots
			? []
			: $this->find_blockers( array_intersect( $roots, $active_plugins ), $outside, $external_consumers );
		$items         = [];

		foreach ( $candidates as $plugin ) {
			$required_by = array_values( array_unique( $blockers[ $plugin ] ?? [] ) );
			$depth       = $depths[ $plugin ] ?? 0;

			$items[] = [
				'plugin'     => $plugin,
				'name'       => $this->get_plugin_name( $plugin ),
				'depth'      => $include_roots ? $depth : max( 0, $depth - 1 ),
				'disabled'   => (bool) $required_by,
				'requiredBy' => $required_by,
			];
		}

		return [
			'roots'         => $include_roots ? [] : array_values( array_intersect( $roots, $active_plugins ) ),
			'items'         => $items,
			'rootBlockedBy' => array_values(
				array_unique( array_merge( [], ...array_values( $root_blockers ?: [ [] ] ) ) )
			),
		];
	}

	/**
	 * Get a safe-ordered list of plugins to deactivate.
	 *
	 * @param array    $plan               Deactivation plan.
	 * @param string[] $selected           Selected dependency plugin files.
	 * @param string[] $active_plugins     Active plugin files.
	 * @param array    $external_consumers Additional active consumers and their dependencies.
	 *
	 * @return string[]
	 */
	public function get_safe_deactivation_plugins(
		array $plan,
		array $selected,
		array $active_plugins,
		array $external_consumers = []
	): array {
		$roots   = $this->normalize_plugins( $plan['roots'] ?? [] );
		$allowed = [];

		foreach ( (array) ( $plan['items'] ?? [] ) as $item ) {
			if ( empty( $item['disabled'] ) && ! empty( $item['plugin'] ) ) {
				$allowed[] = (string) $item['plugin'];
			}
		}

		$selected = array_values(
			array_intersect( $this->normalize_plugins( $selected ), $allowed )
		);

		do {
			$previous  = $selected;
			$requested = array_values( array_unique( array_merge( $roots, $selected ) ) );
			$remaining = array_values( array_diff( $active_plugins, $requested ) );
			$blockers  = $this->find_blockers( $selected, $remaining, $external_consumers );

			$selected = array_values(
				array_filter(
					$selected,
					static function ( string $plugin ) use ( $blockers ): bool {
						return empty( $blockers[ $plugin ] );
					}
				)
			);
		} while ( $previous !== $selected );

		return array_values( array_unique( array_merge( $roots, $selected ) ) );
	}

	/**
	 * Find consumers that require target plugins.
	 *
	 * @param string[] $targets            Target plugin files.
	 * @param string[] $plugin_consumers   Active plugin consumers.
	 * @param array    $external_consumers Additional active consumers and their dependencies.
	 *
	 * @return array
	 */
	private function find_blockers(
		array $targets,
		array $plugin_consumers,
		array $external_consumers
	): array {
		$blockers = array_fill_keys( $targets, [] );

		foreach ( $plugin_consumers as $consumer ) {
			$this->add_consumer_blockers(
				$blockers,
				$this->get_plugin_name( $consumer ),
				$this->get_dependencies( $consumer )
			);
		}

		foreach ( $external_consumers as $consumer => $dependencies ) {
			$this->add_consumer_blockers( $blockers, (string) $consumer, (array) $dependencies );
		}

		return array_filter( $blockers );
	}

	/**
	 * Add blockers from a consumer dependency closure.
	 *
	 * @param array    $blockers     Blockers indexed by plugin file.
	 * @param string   $consumer     Consumer display name.
	 * @param string[] $dependencies Consumer dependencies.
	 *
	 * @return void
	 */
	private function add_consumer_blockers( array &$blockers, string $consumer, array $dependencies ): void {
		$dependency_plugins = array_keys( $this->get_dependency_depths( $dependencies ) );

		foreach ( array_intersect( array_keys( $blockers ), $dependency_plugins ) as $plugin ) {
			$blockers[ $plugin ][] = $consumer;
		}
	}

	/**
	 * Get dependency depths in stable, parent-first order.
	 *
	 * @param string[] $roots Root plugin files.
	 *
	 * @return array
	 * @noinspection SuspiciousAssignmentsInspection
	 */
	private function get_dependency_depths( array $roots ): array {
		$depths = [];
		$queue  = [];

		foreach ( $this->normalize_plugins( $roots ) as $root ) {
			$queue[] = [ $root, 0 ];
		}

		while ( $queue ) {
			[ $plugin, $depth ] = array_shift( $queue );

			if ( isset( $depths[ $plugin ] ) && $depths[ $plugin ] <= $depth ) {
				continue;
			}

			$depths[ $plugin ] = $depth;

			foreach ( $this->get_dependencies( $plugin ) as $dependency ) {
				$queue[] = [ $dependency, $depth + 1 ];
			}
		}

		return $depths;
	}

	/**
	 * Convert dependency directory slugs to installed plugin files.
	 *
	 * @param array $directories Plugin directory slugs.
	 *
	 * @return string[]
	 */
	private function plugin_dirs_to_files( array $directories ): array {
		$plugin_files = array_keys( $this->plugins );
		$converted    = [];

		foreach ( $directories as $directory ) {
			$directory = trim( (string) $directory );

			if ( '' === $directory ) {
				continue;
			}

			if ( in_array( $directory, $plugin_files, true ) ) {
				$converted[] = $directory;

				continue;
			}

			$prefix = $directory . '/';

			foreach ( $plugin_files as $plugin_file ) {
				if ( 0 === strpos( $plugin_file, $prefix ) ) {
					$converted[] = $plugin_file;

					break;
				}
			}
		}

		return array_values( array_unique( $converted ) );
	}

	/**
	 * Get plugin data.
	 *
	 * @param string $plugin Plugin file.
	 *
	 * @return array
	 */
	private function get_plugin_data( string $plugin ): array {
		if ( $this->plugin_data_loader ) {
			$data = call_user_func( $this->plugin_data_loader, $plugin );

			return is_array( $data ) ? $data : [];
		}

		return (array) ( $this->plugins[ $plugin ] ?? [] );
	}

	/**
	 * Normalize a plugin list.
	 *
	 * @param array $plugins Plugin files.
	 *
	 * @return string[]
	 */
	private function normalize_plugins( array $plugins ): array {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'strval', $plugins )
				)
			)
		);
	}
}
