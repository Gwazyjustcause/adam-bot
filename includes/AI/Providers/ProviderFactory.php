<?php
/**
 * AI provider factory.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Providers;

use AdamBot\AI\Exceptions\ConfigurationException;
use AdamBot\AI\Settings\AISettings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates providers exclusively from trusted server-side configuration.
 */
final class ProviderFactory {
	/** @var AISettings */
	private $settings;

	/**
	 * Creates the factory.
	 *
	 * @param AISettings $settings Settings repository.
	 */
	public function __construct( AISettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Creates the configured provider adapter.
	 *
	 * @param string $provider Provider identifier read from the database.
	 * @return AIProviderInterface
	 * @throws ConfigurationException When provider configuration is invalid.
	 */
	public function create( string $provider ): AIProviderInterface {
		if ( 'openai' !== $provider ) {
			throw new ConfigurationException( 'Unsupported AI provider: ' . sanitize_key( $provider ) );
		}

		$settings = $this->settings->all();
		$api_key  = (string) $settings['openai_api_key'];

		if ( ! $this->settings->isValidApiKey( $api_key ) ) {
			throw new ConfigurationException( 'The OpenAI API key is missing or invalid.' );
		}

		return new OpenAIProvider(
			$api_key,
			(string) $settings['model'],
			(float) $settings['temperature'],
			(int) $settings['max_tokens'],
			(int) $settings['timeout']
		);
	}
}
