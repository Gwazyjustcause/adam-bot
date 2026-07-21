<?php
/**
 * Deterministic conversational response formatting.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Response;

use AdamBot\Knowledge\DTO\KnowledgeResponse;
use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\DTO\SearchResultSet;
use AdamBot\Knowledge\Search\KeywordMatcher;
use AdamBot\Knowledge\LanguageDetector;

defined( 'ABSPATH' ) || exit;

/** Converts ranked provider content into the public response contract. */
final class ResponseFormatter {
	/** @var KeywordMatcher */
	private $matcher;

	/** @var LanguageDetector */
	private $language_detector;

	public function __construct( KeywordMatcher $matcher, ?LanguageDetector $language_detector = null ) {
		$this->matcher           = $matcher;
		$this->language_detector = $language_detector ?: new LanguageDetector();
	}

	public function format( SearchResultSet $search, string $question ): KnowledgeResponse {
		$level      = $search->getConfidenceLevel();
		$top        = $search->getTopResult();
		$links      = array();
		$cards      = array();
		$language   = $this->language_detector->detect( $question );

		if ( in_array( $level, array( 'high', 'medium' ), true ) && $top instanceof KnowledgeResult ) {
			$cards = $this->buildComponentCards( $search->getResults() );
			if ( empty( $cards ) && 'event' === $top->getSource() ) {
				$cards = $this->buildEventCards( $search->getResults() );
			}
			if ( ! empty( $cards ) ) {
				$message = $this->formatAnswer( $top );
				$message = '' !== $message ? $message : $top->getTitle();
				$links   = $this->buildComponentLinks( $top );
			} else {
				$answer  = $this->formatAnswer( $top );
				$message = 'medium' === $level ? ( 'en' === $language ? "I found an answer that may help.\n\n" : "Encontrei uma resposta que poderá ajudá-lo.\n\n" ) . $answer : $answer;
				$links = array_slice( array_merge( $this->buildComponentLinks( $top ), $this->buildBlockLinks( $top ), $this->buildLinks( array( $top ) ) ), 0, 4 );
			}
		} elseif ( 'low' === $level ) {
			$message = 'en' === $language ? 'I found some related pages that may help.' : 'Encontrei algumas páginas relacionadas que poderão ajudar.';
			$links   = $this->buildLinks( $search->getFallbackResults() );
		} else {
			$message = 'en' === $language ? "I could not find an answer to that question.\n\nTry rephrasing it or view the pages below." : "Não encontrei uma resposta para essa questão.\n\nExperimente reformular a pergunta ou consulte as páginas abaixo.";
			$links = $this->buildLinks( $search->getFallbackResults() );
		}

		$shown_results = ! empty( $search->getResults() ) ? $search->getResults() : $search->getFallbackResults();

		return new KnowledgeResponse(
			$message,
			$this->buildSuggestions( $search, $question ),
			$links,
			$cards,
			array(
				'topic'           => $search->getTopic(),
				'recentResultIds' => array_map(
					static function ( KnowledgeResult $result ): string { return $result->getId(); },
					array_slice( $shown_results, 0, 5 )
				),
			),
			$level,
			$search->getResponseTimeMs()
		);
	}

	private function formatAnswer( KnowledgeResult $result ): string {
		$component_message = $this->formatComponentMessages( $result );
		if ( '' !== $component_message ) {
			return $component_message;
		}

		if ( ! empty( $result->getResponseBlocks() ) ) {
			return $this->formatBlocks( $result->getResponseBlocks() );
		}

		$content = preg_replace( '#https?://\S+#iu', '', $result->getContent() ) ?? $result->getContent();
		$lines   = preg_split( '/\R+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
		$lines   = is_array( $lines ) ? array_values( array_filter( array_map( 'trim', $lines ) ) ) : array();
		$details = array();
		$body    = array();
		$list    = array();
		$table   = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/^(date|data|location|local|price|preço|valor):\s*(.+)$/iu', $line, $matches ) ) {
				$details[] = '**' . ucfirst( $matches[1] ) . ':** ' . trim( $matches[2] );
			} elseif ( preg_match( '/^[-*+]\s+(.+)$/u', $line, $matches ) ) {
				$list[] = '- ' . $this->truncate( trim( $matches[1] ), 180 );
			} elseif ( preg_match( '/^(\d+)[.)]\s+(.+)$/u', $line, $matches ) ) {
				$list[] = $matches[1] . '. ' . $this->truncate( trim( $matches[2] ), 180 );
			} elseif ( substr_count( $line, '|' ) >= 2 ) {
				$table[] = $this->truncate( $line, 240 );
			} else {
				$body[] = $line;
			}
		}

		$has_table = count( $table ) >= 2 && 1 === preg_match( '/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $table[1] );
		if ( ! $has_table ) {
			$body  = array_merge( $body, $table );
			$table = array();
		}

		return implode(
			"\n\n",
			array_filter(
				array(
					$this->boundedExcerpt( implode( ' ', $body ), $result->getMatchedKeywords() ),
					! empty( $list ) ? implode( "\n", array_slice( $list, 0, 8 ) ) : '',
					! empty( $table ) ? implode( "\n", array_slice( $table, 0, 10 ) ) : '',
					! empty( $details ) ? implode( "\n", array_map( static function ( string $detail ): string { return '- ' . $detail; }, $details ) ) : '',
				)
			)
		);
	}

	/** @param array<int, array<string, mixed>> $blocks Structured answer blocks. */
	private function formatBlocks( array $blocks ): string {
		$parts = array();
		foreach ( $blocks as $block ) {
			$type = sanitize_key( (string) ( $block['type'] ?? '' ) );
			$text = trim( sanitize_textarea_field( (string) ( $block['text'] ?? '' ) ) );
			if ( '' === $text || in_array( $type, array( 'button', 'link' ), true ) ) {
				continue;
			}
			switch ( $type ) {
				case 'heading':
					$parts[] = '### ' . $text;
					break;
				case 'bullet_list':
				case 'numbered_list':
					$items = preg_split( '/\R+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: array();
					$lines = array();
					foreach ( array_slice( $items, 0, 12 ) as $index => $item ) {
						$lines[] = ( 'numbered_list' === $type ? ( $index + 1 ) . '. ' : '- ' ) . ltrim( trim( $item ), '-*+0123456789.) ' );
					}
					$parts[] = implode( "\n", $lines );
					break;
				case 'warning':
					$parts[] = '[!warning] ' . ( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
					break;
				case 'information':
					$parts[] = '[!information] ' . ( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
					break;
				case 'success':
					$parts[] = '[!success] ' . ( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
					break;
				default:
					$parts[] = $text;
			}
		}

		return implode( "\n\n", $parts );
	}

	private function boundedExcerpt( string $content, array $matched_keywords ): string {
		$content   = trim( preg_replace( '/\s+/u', ' ', $content ) ?? $content );
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = is_array( $sentences ) ? $sentences : array( $content );
		$selected  = array();
		foreach ( $sentences as $sentence ) {
			$normalized = $this->matcher->normalize( $sentence );
			foreach ( $matched_keywords as $keyword ) {
				if ( '' !== $keyword && false !== strpos( $normalized, $keyword ) ) {
					$selected[] = trim( $sentence );
					break;
				}
			}
			if ( 3 === count( $selected ) ) {
				break;
			}
		}
		if ( empty( $selected ) ) {
			$selected = array_slice( $sentences, 0, 2 );
		}
		$excerpt = trim( implode( ' ', $selected ) );
		if ( $this->length( $excerpt ) <= 520 ) {
			return $excerpt;
		}
		$excerpt = $this->substring( $excerpt, 0, 519 );
		$space   = strrpos( $excerpt, ' ' );
		return rtrim( false === $space ? $excerpt : substr( $excerpt, 0, $space ) ) . '…';
	}

	/** @return array<int, array<string, string>> */
	private function buildLinks( array $results ): array {
		$links = array();
		$seen  = array();
		foreach ( $results as $result ) {
			if ( ! $result instanceof KnowledgeResult || '' === $result->getUrl() || isset( $seen[ $result->getUrl() ] ) ) {
				continue;
			}
			$scheme = strtolower( (string) parse_url( $result->getUrl(), PHP_URL_SCHEME ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) && 0 !== strpos( $result->getUrl(), '/' ) ) {
				continue;
			}
			$seen[ $result->getUrl() ] = true;
			$links[] = array(
				'title' => $result->getTitle(),
				'label' => '' !== $result->getButtonLabel() ? $result->getButtonLabel() : $result->getTitle(),
				'url'   => $result->getUrl(),
				'kind'  => 'button',
			);
			if ( 3 === count( $links ) ) {
				break;
			}
		}
		return $links;
	}

	/** @return array<int, array<string, string>> */
	private function buildBlockLinks( KnowledgeResult $result ): array {
		$links = array();
		foreach ( $result->getResponseBlocks() as $block ) {
			$type = sanitize_key( (string) ( $block['type'] ?? '' ) );
			$url  = esc_url_raw( (string) ( $block['url'] ?? '' ) );
			$text = sanitize_text_field( (string) ( $block['text'] ?? '' ) );
			if ( ! in_array( $type, array( 'button', 'link' ), true ) || '' === $url || '' === $text ) {
				continue;
			}
			$links[] = array( 'title' => $result->getTitle(), 'label' => $text, 'url' => $url, 'kind' => $type );
			if ( 3 === count( $links ) ) {
				break;
			}
		}
		return $links;
	}

	/** Converts provider information/warning components into safe callouts. */
	private function formatComponentMessages( KnowledgeResult $result ): string {
		$parts = array();
		foreach ( $result->getComponents() as $component ) {
			$type = sanitize_key( (string) ( $component['component'] ?? '' ) );
			$text = $this->truncate( sanitize_textarea_field( (string) ( $component['text'] ?? '' ) ), 800 );
			if ( '' === $text ) { continue; }
			if ( 'information_box' === $type ) { $parts[] = '[!information] ' . $text; }
			if ( 'warning_box' === $type ) { $parts[] = '[!warning] ' . $text; }
		}
		return implode( "\n\n", $parts );
	}

	/** @return array<int,array<string,string>> */
	private function buildComponentLinks( KnowledgeResult $result ): array {
		$links = array();
		foreach ( $result->getComponents() as $component ) {
			if ( 'button_group' !== sanitize_key( (string) ( $component['component'] ?? '' ) ) || ! isset( $component['buttons'] ) || ! is_array( $component['buttons'] ) ) { continue; }
			foreach ( $component['buttons'] as $button ) {
				if ( ! is_array( $button ) ) { continue; }
				$label = sanitize_text_field( (string) ( $button['label'] ?? '' ) );
				$url = esc_url_raw( (string) ( $button['url'] ?? '' ) );
				if ( '' !== $label && '' !== $url ) { $links[] = array( 'title' => $result->getTitle(), 'label' => $label, 'url' => $url, 'kind' => 'button' ); }
			}
		}
		return array_slice( $links, 0, 4 );
	}

	/** @return array<int,array<string,mixed>> */
	private function buildComponentCards( array $results ): array {
		$cards = array();
		foreach ( $results as $result ) {
			if ( ! $result instanceof KnowledgeResult ) { continue; }
			foreach ( $result->getComponents() as $component ) {
				if ( 'card' !== sanitize_key( (string) ( $component['component'] ?? '' ) ) ) { continue; }
				$cards[] = array(
					'type'        => sanitize_key( (string) ( $component['type'] ?? 'result' ) ),
					'groupLabel'  => $this->truncate( sanitize_text_field( (string) ( $component['group_label'] ?? __( 'Resultados', 'adam-bot' ) ) ), 80 ),
					'image'       => esc_url_raw( (string) ( $component['image'] ?? '' ) ),
					'title'       => $this->truncate( sanitize_text_field( (string) ( $component['title'] ?? $result->getTitle() ) ), 140 ),
					'description' => $this->truncate( sanitize_text_field( (string) ( $component['description'] ?? '' ) ), 320 ),
					'meta'        => isset( $component['meta'] ) && is_array( $component['meta'] ) ? array_slice( array_map( function ( $value ): string { return $this->truncate( sanitize_text_field( (string) $value ), 160 ); }, $component['meta'] ), 0, 8 ) : array(),
					'url'         => esc_url_raw( (string) ( $component['url'] ?? '' ) ),
					'actionLabel' => $this->truncate( sanitize_text_field( (string) ( $component['action_label'] ?? __( 'Ver', 'adam-bot' ) ) ), 60 ),
					'actions'     => $this->sanitizeCardActions( $component['actions'] ?? array() ),
					'download'    => ! empty( $component['download'] ),
				);
				if ( 12 === count( $cards ) ) { return $cards; }
			}
		}
		return $cards;
	}

	/** @param mixed $actions Candidate secondary actions. @return array<int,array<string,string>> */
	private function sanitizeCardActions( $actions ): array {
		if ( ! is_array( $actions ) ) { return array(); }
		$clean = array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) { continue; }
			$label = $this->truncate( sanitize_text_field( (string) ( $action['label'] ?? '' ) ), 60 );
			$url   = esc_url_raw( (string) ( $action['url'] ?? '' ) );
			if ( '' !== $label && '' !== $url ) { $clean[] = array( 'label' => $label, 'url' => $url ); }
			if ( 3 === count( $clean ) ) { break; }
		}
		return $clean;
	}

	/** @return array<int, array<string, mixed>> */
	private function buildEventCards( array $results ): array {
		$cards = array();
		foreach ( $results as $result ) {
			if ( ! $result instanceof KnowledgeResult || 'event' !== $result->getSource() ) {
				continue;
			}
			$lines       = preg_split( '/\R+/u', $result->getContent(), -1, PREG_SPLIT_NO_EMPTY );
			$lines       = is_array( $lines ) ? array_map( 'trim', $lines ) : array();
			$description = '';
			$meta        = array();
			foreach ( $lines as $line ) {
				if ( preg_match( '/^(date|data|location|local|price|preço|valor):\s*(.+)$/iu', $line, $matches ) ) {
					$meta[] = ucfirst( $matches[1] ) . ': ' . trim( $matches[2] );
				} elseif ( '' === $description ) {
					$description = $this->truncate( $line, 180 );
				}
			}
			$cards[] = array(
				'title'       => $result->getTitle(),
				'description' => $description,
				'meta'        => array_slice( $meta, 0, 3 ),
				'url'         => $result->getUrl(),
				'actionLabel' => '' !== $result->getButtonLabel() ? $result->getButtonLabel() : __( 'Ver', 'adam-bot' ),
			);
			if ( 3 === count( $cards ) ) {
				break;
			}
		}
		return $cards;
	}

	/** @return array<int, array<string, string>> */
	private function buildSuggestions( SearchResultSet $search, string $question ): array {
		$suggestions = array();
		$seen        = array( $this->matcher->normalize( $question ) => true );
		$top         = $search->getTopResult();
		if ( $top instanceof KnowledgeResult ) {
			foreach ( $top->getRelated() as $related ) {
				$prompt = trim( (string) ( $related['question'] ?? '' ) );
				$label  = trim( (string) ( $related['title'] ?? $prompt ) );
				if ( $this->addSuggestion( $suggestions, $seen, $label, $prompt ) && 4 === count( $suggestions ) ) {
					return $suggestions;
				}
			}
		}

		foreach ( array_slice( $search->getResults(), 1 ) as $related ) {
			$prompt = trim( $related->getTitle() );
			if ( $this->addSuggestion( $suggestions, $seen, $prompt, $prompt ) && 4 === count( $suggestions ) ) {
				break;
			}
		}
		return $suggestions;
	}

	/** @param array<int,array<string,string>> $suggestions @param array<string,bool> $seen */
	private function addSuggestion( array &$suggestions, array &$seen, string $label, string $prompt ): bool {
		$key = $this->matcher->normalize( $prompt );
		if ( '' === $key || isset( $seen[ $key ] ) ) {
			return false;
		}
		$seen[ $key ] = true;
		$suggestions[] = array(
			'label'  => $this->truncate( $label, 70 ),
			'prompt' => $this->truncate( $prompt, 180 ),
			'action' => 'message',
		);
		return true;
	}

	private function truncate( string $value, int $maximum ): string {
		return $this->length( $value ) <= $maximum ? $value : rtrim( $this->substring( $value, 0, $maximum - 1 ) ) . '…';
	}

	private function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	private function substring( string $value, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, $start, $length ) : substr( $value, $start, $length );
	}
}
