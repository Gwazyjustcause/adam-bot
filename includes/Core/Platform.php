<?php
/**
 * Public ADAM BOT service facade.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Core;

use AdamBot\Knowledge\Dynamic\DynamicProviderRegistry;

defined( 'ABSPATH' ) || exit;

/** Stable public entry point exposed by adam_bot(). */
final class Platform {
	/** @var self|null */
	private static $instance;

	/** @var DynamicProviderRegistry */
	private $providers;

	/** @var bool */
	private $booted = false;

	private function __construct() {
		$this->providers = new DynamicProviderRegistry();
	}

	public static function instance(): self {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function providers(): DynamicProviderRegistry {
		return $this->providers;
	}

	/** @return void */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		( new Plugin( $this->providers ) )->run();
	}
}
