<?php
/**
 * Lazy dynamic-provider registry.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic;

defined( 'ABSPATH' ) || exit;

/** Discovers, validates, and lazily instantiates live providers. */
final class DynamicProviderRegistry {
	/** @var array<string, array<string, mixed>> */
	private $providers = array();

	/** @var bool */
	private $discovered = false;

	/** @return void */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'discover' ), 5 );
		add_filter( 'adam_bot_knowledge_provider_registry', array( $this, 'addProviderLabels' ) );
	}

	public function register( DynamicProviderInterface $provider ): bool {
		try {
			$key      = sanitize_key( $provider->getKey() );
			$priority = max( 0, min( 100, $provider->getPriority() ) );
		} catch ( \Throwable $exception ) {
			do_action( 'adam_bot_dynamic_provider_error', '', $exception );
			return false;
		}
		if ( '' === $key ) {
			return false;
		}
		$this->providers[ $key ] = array(
			'key'      => $key,
			'label'    => '',
			'intents'  => array(),
			'priority' => $priority,
			'factory'  => null,
			'instance' => $provider,
		);
		return true;
	}

	/** @param array<int,string> $intents Declared intent keys. */
	public function registerFactory( string $key, string $label, array $intents, int $priority, callable $factory ): bool {
		$key = sanitize_key( $key );
		if ( '' === $key || isset( $this->providers[ $key ] ) ) {
			return false;
		}
		$this->providers[ $key ] = array(
			'key'      => $key,
			'label'    => sanitize_text_field( $label ),
			'intents'  => array_values( array_unique( array_filter( array_map( 'sanitize_key', $intents ) ) ) ),
			'priority' => max( 0, min( 100, $priority ) ),
			'factory'  => $factory,
			'instance' => null,
		);
		return true;
	}

	/** @return array<int, DynamicProviderInterface> */
	public function getProvidersForIntent( string $intent ): array {
		$this->discover();
		$intent = sanitize_key( $intent );
		$items  = $this->providers;
		uasort( $items, static function ( array $left, array $right ): int { return (int) $right['priority'] <=> (int) $left['priority']; } );
		$matched = array();
		foreach ( $items as $key => $descriptor ) {
			$declared = $descriptor['intents'];
			if ( ! empty( $declared ) && ! in_array( $intent, $declared, true ) && ! in_array( '*', $declared, true ) ) {
				continue;
			}
			$provider = $this->provider( (string) $key );
			if ( ! $provider ) {
				continue;
			}
			try {
				if ( ! $provider->supportsIntent( $intent ) || ! $provider->isAvailable() ) {
					continue;
				}
			} catch ( \Throwable $exception ) {
				do_action( 'adam_bot_dynamic_provider_error', $key, $exception );
				continue;
			}
			$matched[] = $provider;
		}
		return $matched;
	}

	/** Invites installed plugins to register without creating a core dependency. */
	public function discover(): void {
		if ( $this->discovered ) {
			return;
		}
		$this->discovered = true;
		do_action( 'adam_bot_register_dynamic_providers', $this );
		$providers = apply_filters( 'adam_bot_dynamic_providers', array(), $this );
		if ( is_array( $providers ) ) {
			foreach ( $providers as $provider ) {
				if ( $provider instanceof DynamicProviderInterface ) {
					$this->register( $provider );
				}
			}
		}
	}

	/** @param array<string,string> $labels Existing labels. @return array<string,string> */
	public function addProviderLabels( array $labels ): array {
		foreach ( $this->providers as $key => $descriptor ) {
			$labels[ $key ] = $this->resolveLabel( (string) $key, $descriptor );
		}
		return $labels;
	}

	/** @return array<string,string> */
	public function labels(): array {
		return $this->addProviderLabels( array() );
	}

	/**
	 * Stable cache namespace that changes as providers register or disappear.
	 * No factory is instantiated while building the signature.
	 */
	public function getCacheSignature(): string {
		$this->discover();
		$signature = array();
		foreach ( $this->providers as $key => $descriptor ) {
			$signature[ $key ] = array(
				'priority' => (int) $descriptor['priority'],
				'intents'  => array_values( (array) $descriptor['intents'] ),
			);
		}
		ksort( $signature );
		return md5( (string) wp_json_encode( $signature ) );
	}

	/**
	 * Returns administrator-facing provider diagnostics. Factories are only
	 * instantiated when this explicit inspector method is requested.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function inspect(): array {
		$this->discover();
		$rows = array();
		foreach ( $this->providers as $key => $descriptor ) {
			$provider = $this->provider( (string) $key );
			$available = false;
			if ( $provider ) {
				try {
					$available = $provider->isAvailable();
				} catch ( \Throwable $exception ) {
					do_action( 'adam_bot_dynamic_provider_error', $key, $exception );
				}
			}
			$rows[] = array(
				'key'       => (string) $key,
				'label'     => $this->resolveLabel( (string) $key, $descriptor ),
				'priority'  => (int) $descriptor['priority'],
				'intents'   => array_values( (array) $descriptor['intents'] ),
				'loaded'    => $provider instanceof DynamicProviderInterface,
				'available' => $available,
				'indexed'   => max( 0, (int) apply_filters( 'adam_bot_dynamic_provider_indexed_count', 0, (string) $key, $provider ) ),
				'updated'   => sanitize_text_field( (string) apply_filters( 'adam_bot_dynamic_provider_last_update', '', (string) $key, $provider ) ),
			);
		}
		usort( $rows, static function ( array $left, array $right ): int { return (int) $right['priority'] <=> (int) $left['priority']; } );
		return $rows;
	}

	private function provider( string $key ): ?DynamicProviderInterface {
		if ( ! isset( $this->providers[ $key ] ) ) {
			return null;
		}
		$descriptor = $this->providers[ $key ];
		if ( $descriptor['instance'] instanceof DynamicProviderInterface ) {
			return $descriptor['instance'];
		}
		if ( ! is_callable( $descriptor['factory'] ) ) {
			return null;
		}
		try {
			$instance = call_user_func( $descriptor['factory'] );
		} catch ( \Throwable $exception ) {
			do_action( 'adam_bot_dynamic_provider_error', $key, $exception );
			return null;
		}
		if ( ! $instance instanceof DynamicProviderInterface ) {
			return null;
		}
		try {
			if ( $key !== sanitize_key( $instance->getKey() ) ) {
				throw new \UnexpectedValueException( 'Dynamic provider factory returned a different provider key.' );
			}
		} catch ( \Throwable $exception ) {
			do_action( 'adam_bot_dynamic_provider_error', $key, $exception );
			return null;
		}
		$this->providers[ $key ]['instance'] = $instance;
		return $instance;
	}

	/** Resolves instance labels only after init so integrations may translate safely. */
	private function resolveLabel( string $key, array $descriptor ): string {
		$label = sanitize_text_field( (string) ( $descriptor['label'] ?? '' ) );
		if ( '' !== $label ) { return $label; }
		$init_started = function_exists( 'did_action' ) && did_action( 'init' ) > 0;
		$init_running = function_exists( 'doing_action' ) && doing_action( 'init' );
		if ( ! $init_started && ! $init_running ) { return $key; }
		$provider = $descriptor['instance'] ?? null;
		if ( ! $provider instanceof DynamicProviderInterface ) { return $key; }
		try {
			$label = sanitize_text_field( $provider->getLabel() );
		} catch ( \Throwable $exception ) {
			do_action( 'adam_bot_dynamic_provider_error', $key, $exception );
			return $key;
		}
		return '' !== $label ? $label : $key;
	}
}
