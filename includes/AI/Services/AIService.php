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
use AdamBot\Knowledge\DTO\KnowledgeContext;

defined( 'ABSPATH' ) || exit;

/**
 * Selects providers, prepares prompts, adds UX metadata, and contains failures.
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
	 * @param AISettings      $settings Settings repository.
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
		$context       = new KnowledgeContext();

		try {
			$context = $this->prompt_builder->findKnowledge( $request );

			if ( ! $context->hasResults() && ! $request->allowsGeneralKnowledge() ) {
				$response = new ChatResponse( true, $this->getNoKnowledgeMessage( $request->getUserMessage() ) );

				return $response->withExperience(
					ChatResponse::CLASSIFICATION_GENERAL,
					$this->buildSuggestions( $request->getUserMessage(), $context, true ),
					array(),
					false,
					$this->elapsedMilliseconds( $started_at ),
					true
				);
			}

			$prepared       = $this->prompt_builder->buildWithContext( $request, $context );
			$provider       = $this->provider_factory->create( $provider_name );
			$response       = $provider->generateResponse( $prepared );
			$elapsed        = $this->elapsedMilliseconds( $started_at );
			$classification = $this->classify( $context, $request->allowsGeneralKnowledge() );

			$this->logger->info(
				'AI request completed.',
				array(
					'provider'              => $provider_name,
					'response_time_ms'       => $elapsed,
					'token_usage'           => $response->getTokenUsage(),
					'answer_classification' => $classification,
				)
			);

			return $response->withExperience(
				$classification,
				$this->buildSuggestions( $request->getUserMessage(), $context ),
				$this->buildLinks( $context ),
				$context->hasResults(),
				$elapsed
			);
		} catch ( \Throwable $exception ) {
			$elapsed = $this->elapsedMilliseconds( $started_at );

			$this->logger->error(
				'AI request failed.',
				array(
					'provider'         => $provider_name,
					'response_time_ms' => $elapsed,
					'error_type'       => get_class( $exception ),
					'error'            => $exception->getMessage(),
				)
			);

			$response = new ChatResponse(
				false,
				__( 'Desculpe, o serviço de IA está temporariamente indisponível. Tente novamente dentro de alguns instantes.', 'adam-bot' ),
				$provider_name
			);

			return $response->withExperience(
				$this->classify( $context, $request->allowsGeneralKnowledge() ),
				array(),
				$this->buildLinks( $context ),
				$context->hasResults(),
				$elapsed
			);
		}
	}

	/** @return string */
	private function classify( KnowledgeContext $context, bool $general_allowed ): string {
		if ( $context->hasResults() && $general_allowed ) {
			return ChatResponse::CLASSIFICATION_MIXED;
		}

		return $context->hasResults()
			? ChatResponse::CLASSIFICATION_OFFICIAL
			: ChatResponse::CLASSIFICATION_GENERAL;
	}

	/** @return string */
	private function getNoKnowledgeMessage( string $question ): string {
		if ( preg_match( '/\b(what|how|where|when|why|who|can|could|does|do|is|are)\b/i', $question ) ) {
			return "I couldn't find official ADAM information about that.\n\nWould you like me to answer using general knowledge instead?";
		}

		return "Não encontrei informação oficial da ADAM sobre esse tema.\n\nQuer que responda com base em conhecimento geral?";
	}

	/**
	 * Maps the current topic to concise contextual follow-ups.
	 *
	 * @param string           $question Current question.
	 * @param KnowledgeContext $context Trusted results.
	 * @param bool             $needs_consent Whether to prioritize the consent action.
	 * @return array<int, array<string, string>>
	 */
	private function buildSuggestions( string $question, KnowledgeContext $context, bool $needs_consent = false ): array {
		if ( $needs_consent ) {
			return array(
				array(
					'label'  => __( 'Responder com conhecimento geral', 'adam-bot' ),
					'prompt' => $question,
					'action' => 'general',
				),
				array(
					'label'  => __( 'Como posso contactar a ADAM?', 'adam-bot' ),
					'prompt' => __( 'Como posso contactar a ADAM?', 'adam-bot' ),
					'action' => 'message',
				),
			);
		}

		$topic       = $this->detectTopic( $question, $context );
		$suggestions = array(
			'membership' => array(
				array( 'label' => __( 'Quanto custa?', 'adam-bot' ), 'prompt' => __( 'Quanto custa ser sócio?', 'adam-bot' ) ),
				array( 'label' => __( 'Quais são os benefícios?', 'adam-bot' ), 'prompt' => __( 'Quais são os benefícios de ser sócio?', 'adam-bot' ) ),
				array( 'label' => __( 'Como funcionam as renovações?', 'adam-bot' ), 'prompt' => __( 'Como funciona a renovação da quota?', 'adam-bot' ) ),
				array( 'label' => __( 'Onde me posso inscrever?', 'adam-bot' ), 'prompt' => __( 'Onde me posso inscrever como sócio?', 'adam-bot' ) ),
			),
			'events' => array(
				array( 'label' => __( 'Qual é o próximo evento?', 'adam-bot' ), 'prompt' => __( 'Qual é o próximo evento da ADAM?', 'adam-bot' ) ),
				array( 'label' => __( 'Como me inscrevo?', 'adam-bot' ), 'prompt' => __( 'Como me inscrevo num evento?', 'adam-bot' ) ),
				array( 'label' => __( 'Onde se realizam?', 'adam-bot' ), 'prompt' => __( 'Onde se realizam os eventos?', 'adam-bot' ) ),
			),
			'rules' => array(
				array( 'label' => __( 'Quais são as regras de segurança?', 'adam-bot' ), 'prompt' => __( 'Quais são as regras de segurança no Airsoft?', 'adam-bot' ) ),
				array( 'label' => __( 'Que equipamento é obrigatório?', 'adam-bot' ), 'prompt' => __( 'Que equipamento é obrigatório para jogar?', 'adam-bot' ) ),
				array( 'label' => __( 'Quais são os limites de potência?', 'adam-bot' ), 'prompt' => __( 'Quais são os limites de potência no Airsoft?', 'adam-bot' ) ),
			),
			'contact' => array(
				array( 'label' => __( 'Onde fica a ADAM?', 'adam-bot' ), 'prompt' => __( 'Onde fica a ADAM?', 'adam-bot' ) ),
				array( 'label' => __( 'Como posso tornar-me sócio?', 'adam-bot' ), 'prompt' => __( 'Como posso tornar-me sócio?', 'adam-bot' ) ),
			),
			'about' => array(
				array( 'label' => __( 'O que faz a ADAM?', 'adam-bot' ), 'prompt' => __( 'Que atividades organiza a ADAM?', 'adam-bot' ) ),
				array( 'label' => __( 'Como posso participar?', 'adam-bot' ), 'prompt' => __( 'Como posso participar nas atividades da ADAM?', 'adam-bot' ) ),
				array( 'label' => __( 'Ver próximos eventos', 'adam-bot' ), 'prompt' => __( 'Quais são os próximos eventos?', 'adam-bot' ) ),
			),
		);

		$selected = $suggestions[ $topic ] ?? array(
			array( 'label' => __( 'O que é a ADAM?', 'adam-bot' ), 'prompt' => __( 'O que é a ADAM?', 'adam-bot' ) ),
			array( 'label' => __( 'Ver próximos eventos', 'adam-bot' ), 'prompt' => __( 'Quais são os próximos eventos?', 'adam-bot' ) ),
			array( 'label' => __( 'Como posso tornar-me sócio?', 'adam-bot' ), 'prompt' => __( 'Como posso tornar-me sócio?', 'adam-bot' ) ),
		);

		return array_map(
			static function ( array $suggestion ): array {
				$suggestion['action'] = 'message';
				return $suggestion;
			},
			array_slice( $selected, 0, 4 )
		);
	}

	/**
	 * Builds deduplicated navigation buttons from trusted result URLs.
	 *
	 * @param KnowledgeContext $context Trusted results.
	 * @return array<int, array<string, string>>
	 */
	private function buildLinks( KnowledgeContext $context ): array {
		$links = array();
		$seen  = array();

		foreach ( $context->getResults() as $result ) {
			$url    = $result->getUrl();
			$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

			if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) || isset( $seen[ $url ] ) ) {
				continue;
			}

			$seen[ $url ] = true;
			$links[]      = array(
				'title' => $result->getTitle(),
				'label' => __( 'Saber mais', 'adam-bot' ),
				'url'   => $url,
			);

			if ( 3 === count( $links ) ) {
				break;
			}
		}

		return $links;
	}

	/**
	 * Infers a broad public-information topic without user profiling.
	 *
	 * @param string           $question Current question.
	 * @param KnowledgeContext $context Trusted results.
	 * @return string
	 */
	private function detectTopic( string $question, KnowledgeContext $context ): string {
		$parts = array( $question );

		foreach ( $context->getResults() as $result ) {
			$parts[] = $result->getSource();
			$parts[] = $result->getCategory();
			$parts[] = $result->getTitle();
		}

		$value = strtolower( remove_accents( implode( ' ', $parts ) ) );
		$topics = array(
			'membership' => '/\b(socio|socios|membership|member|quota|quotas|renew|renewal|renovar|inscri)/',
			'events'     => '/\b(event|events|evento|eventos|jogo|jogos|agenda)/',
			'rules'      => '/\b(rule|rules|regra|regras|airsoft|safety|seguranca|joule|potencia|equipamento)/',
			'contact'    => '/\b(contact|contacto|contactar|telefone|email|morada)/',
			'about'      => '/\b(about|adam|associacao|quem somos)/',
		);

		foreach ( $topics as $topic => $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return $topic;
			}
		}

		return '';
	}

	/** @return int */
	private function elapsedMilliseconds( float $started_at ): int {
		return (int) round( ( microtime( true ) - $started_at ) * 1000 );
	}
}
