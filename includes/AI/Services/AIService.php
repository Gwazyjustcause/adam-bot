<?php
/**
 * AI orchestration service.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\AI\Services;

use AdamBot\AI\DTO\ChatRequest;
use AdamBot\AI\DTO\ChatResponse;
use AdamBot\AI\Providers\ProviderFactory;
use AdamBot\AI\Settings\AISettings;
use AdamBot\Helpers\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Selects providers, prepares prompts, handles errors, and logs metadata.
 */
final class AIService {
	/** @var AISettings */
	private $settings;

	/** @var ProviderFactory */
	private $provider_factory;

	/** @var PromptBuilder */
	private $prompt_builder;

	/** @var Logger */
	private $logger;

	/**
	 * Creates the service.
	 *
	 * @param AISettings     $settings Settings repository.
	 * @param ProviderFactory $provider_factory Provider factory.
	 * @param PromptBuilder   $prompt_builder Prompt builder.
	 * @param Logger          $logger Internal logger.
	 */
	public function __construct(
		AISettings $settings,
		ProviderFactory $provider_factory,
		PromptBuilder $prompt_builder,
		Logger $logger
	) {
		$this->settings         = $settings;
		$this->provider_factory = $provider_factory;
		$this->prompt_builder   = $prompt_builder;
		$this->logger           = $logger;
	}

	/**
	 * Generates a provider-neutral response and contains all provider failures.
	 *
	 * @param ChatRequest $request Sanitized request DTO.
	 * @return ChatResponse
	 */
	public function generateResponse( ChatRequest $request ): ChatResponse {
		$settings      = $this->settings->all();
		$provider_name = (string) $settings['provider'];
		$started_at    = microtime( true );

		try {
			$prepared = $this->prompt_builder->build( $request );
			$provider = $this->provider_factory->create( $provider_name );
			$response = $provider->generateResponse( $prepared );
			$elapsed  = $this->elapsedMilliseconds( $started_at );

			$this->logger->info(
				'AI request completed.',
				array(
					'provider'         => $provider_name,
					'response_time_ms' => $elapsed,
					'token_usage'      => $response->getTokenUsage(),
				)
			);

			return $response;
		} catch ( \Throwable $exception ) {
			$this->logger->error(
				'AI request failed.',
				array(
					'provider'         => $provider_name,
					'response_time_ms' => $this->elapsedMilliseconds( $started_at ),
					'error_type'       => get_class( $exception ),
					'error'            => $exception->getMessage(),
				)
			);

			return new ChatResponse(
				false,
				__( 'Desculpe, o serviço de IA está temporariamente indisponível. Tente novamente dentro de alguns instantes.', 'adam-bot' ),
				$provider_name
			);
		}
	}

	/**
	 * Calculates elapsed time for structured logs.
	 *
	 * @param float $started_at Start timestamp.
	 * @return int
	 */
	private function elapsedMilliseconds( float $started_at ): int {
		return (int) round( ( microtime( true ) - $started_at ) * 1000 );
	}
}
