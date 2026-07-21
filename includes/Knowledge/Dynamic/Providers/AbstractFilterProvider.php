<?php
/**
 * Filter-backed dynamic provider base.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic\Providers;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\Dynamic\DynamicProviderInterface;
use AdamBot\Knowledge\Response\Component\ComponentInterface;

defined( 'ABSPATH' ) || exit;

/** Adapts plugin-owned public arrays without depending on the owning plugin. */
abstract class AbstractFilterProvider implements DynamicProviderInterface {
	/** @var string */ protected $key;
	/** @var string */ protected $label;
	/** @var array<int,string> */ protected $intents;
	/** @var int */ protected $priority;
	/** @var string */ protected $hook;
	/** @var int */ protected $cache_ttl;

	/** @param array<int,string> $intents Intent keys. */
	public function __construct( string $key, string $label, array $intents, int $priority, string $hook, int $cache_ttl = 120 ) {
		$this->key       = sanitize_key( $key );
		$this->label     = sanitize_text_field( $label );
		$this->intents   = array_values( array_unique( array_map( 'sanitize_key', $intents ) ) );
		$this->priority  = max( 0, min( 100, $priority ) );
		$this->hook      = sanitize_key( $hook );
		$this->cache_ttl = max( 10, min( 900, $cache_ttl ) );
	}

	public function getKey(): string { return $this->key; }
	public function getLabel(): string { return $this->label; }
	public function getPriority(): int { return $this->priority; }
	public function getCacheTtl(): int { return $this->cache_ttl; }

	public function supportsIntent( string $intent ): bool {
		return in_array( sanitize_key( $intent ), $this->intents, true );
	}

	public function isAvailable(): bool {
		return (bool) apply_filters( 'adam_bot_dynamic_provider_' . $this->key . '_available', true, $this );
	}

	/** @return array<int,KnowledgeResult> */
	public function search( string $query, string $intent ): array {
		$items   = $this->items( $query, $intent );
		$results = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ( isset( $item['public'] ) && ! $item['public'] ) || ( isset( $item['enabled'] ) && ! $item['enabled'] ) ) {
				continue;
			}
			if ( ! $this->itemMatchesQualifiers( $item, $query ) ) {
				continue;
			}
			$result = $this->mapItem( $item, $query, $intent );
			if ( $result instanceof KnowledgeResult ) {
				$results[] = $result;
			}
		}
		return array_slice( $results, 0, 50 );
	}

	/** @return array<int,array<string,string>> */
	public function getSuggestions( string $query, string $intent ): array {
		$value = apply_filters( 'adam_bot_dynamic_' . $this->key . '_suggestions', array(), $query, $intent, $this );
		if ( ! is_array( $value ) ) { return array(); }
		$clean = array();
		foreach ( $value as $suggestion ) {
			if ( ! is_array( $suggestion ) ) { continue; }
			$title = sanitize_text_field( (string) ( $suggestion['title'] ?? $suggestion['label'] ?? '' ) );
			$question = sanitize_text_field( (string) ( $suggestion['question'] ?? $suggestion['prompt'] ?? $title ) );
			if ( '' !== $title && '' !== $question ) { $clean[] = array( 'title' => $title, 'question' => $question ); }
		}
		return array_slice( $clean, 0, 8 );
	}

	/** @return array<int,array<string,mixed>> */
	protected function items( string $query, string $intent ): array {
		$items = apply_filters( $this->hook, array(), $query, $intent, $this );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Broad intent prompts may return a list, while specific qualifiers (place,
	 * type, price, name) must occur in the item supplied by the owning plugin.
	 *
	 * @param array<string,mixed> $item Public item.
	 */
	private function itemMatchesQualifiers( array $item, string $query ): bool {
		if ( ! empty( $item['matched'] ) ) {
			return true;
		}
		$terms = preg_split( '/\s+/u', $this->normalize( $query ), -1, PREG_SPLIT_NO_EMPTY );
		$terms = is_array( $terms ) ? $terms : array();
		$ignored = array_merge(
			array( 'about', 'are', 'can', 'como', 'da', 'das', 'de', 'do', 'dos', 'este', 'esta', 'for', 'how', 'in', 'latest', 'me', 'mostra', 'mostre', 'near', 'next', 'onde', 'offer', 'para', 'please', 'quais', 'qual', 'quanto', 'recent', 'show', 'the', 'this', 'upcoming', 'what', 'where', 'which', 'with' ),
			$this->genericIntentTerms()
		);
		$qualifiers = array_values( array_unique( array_filter( $terms, static function ( string $term ) use ( $ignored ): bool {
			return strlen( $term ) >= 3 && ! in_array( $term, $ignored, true );
		} ) ) );
		if ( empty( $qualifiers ) ) {
			return true;
		}

		$haystack = $this->normalize( (string) wp_json_encode( $item ) );
		foreach ( $qualifiers as $qualifier ) {
			$variants = array( $qualifier );
			if ( strlen( $qualifier ) > 4 && 'ies' === substr( $qualifier, -3 ) ) {
				$variants[] = substr( $qualifier, 0, -3 ) . 'y';
			} elseif ( strlen( $qualifier ) > 3 && 's' === substr( $qualifier, -1 ) ) {
				$variants[] = substr( $qualifier, 0, -1 );
			}
			foreach ( $variants as $variant ) {
				if ( 1 === preg_match( '/(?:^|\s)' . preg_quote( $variant, '/' ) . '(?:$|\s)/u', $haystack ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** @return array<int,string> */
	private function genericIntentTerms(): array {
		$terms = array(
			'event'      => array( 'event', 'events', 'evento', 'eventos', 'game', 'games', 'jogo', 'jogos', 'agenda', 'month', 'mes' ),
			'teams'      => array( 'team', 'teams', 'equipa', 'equipas', 'clube', 'clubes' ),
			'fields'     => array( 'field', 'fields', 'campo', 'campos' ),
			'partners'   => array( 'partner', 'partners', 'parceiro', 'parceiros', 'sponsor', 'sponsors' ),
			'news'       => array( 'news', 'noticia', 'noticias', 'announcement', 'announcements', 'novidades' ),
			'documents'  => array( 'document', 'documents', 'documento', 'documentos', 'download', 'downloads' ),
			'membership' => array( 'membership', 'member', 'members', 'membro', 'membros', 'socio', 'socios', 'adam', 'become', 'join', 'tornar' ),
		);
		return $terms[ $this->key ] ?? array();
	}

	private function normalize( string $value ): string {
		$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/u', ' ', $value ) ?? $value;
		return trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );
	}

	/** @param array<string,mixed> $item Public item. */
	protected function mapItem( array $item, string $query, string $intent ): ?KnowledgeResult {
		unset( $query, $intent );
		$title   = sanitize_text_field( (string) ( $item['title'] ?? $item['name'] ?? '' ) );
		$content = sanitize_textarea_field( (string) ( $item['content'] ?? $item['summary'] ?? $item['description'] ?? '' ) );
		if ( '' === $title ) { return null; }
		if ( '' === $content ) { $content = $title; }
		$component = $this->card( $item );
		return new KnowledgeResult(
			$this->key,
			$this->label,
			$title,
			$content,
			sanitize_text_field( (string) ( $item['category'] ?? $this->label ) ),
			esc_url_raw( (string) ( $item['url'] ?? $item['profile_url'] ?? $item['download_url'] ?? $item['registration_url'] ?? '' ) ),
			0,
			array(),
			max( 0, min( 100, (int) ( $item['priority'] ?? $this->priority ) ) ),
			array(
				'keywords'      => $item['keywords'] ?? array(),
				'synonyms'      => $item['synonyms'] ?? array(),
				'search_weight' => $item['search_weight'] ?? 110,
				'button_label'  => $item['button_text'] ?? '',
				'related'       => $item['related'] ?? array(),
				'object_id'     => $item['object_id'] ?? 0,
				'components'    => $component ? array( $component->toArray() ) : array(),
			)
		);
	}

	/** @param array<string,mixed> $item Public item. */
	abstract protected function card( array $item ): ?ComponentInterface;
}
