<?php
/**
 * Lightweight intent recognition.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Dynamic;

use AdamBot\Knowledge\Search\KeywordMatcher;

defined( 'ABSPATH' ) || exit;

/** Detects a bounded intent without an external service or plugin dependency. */
final class IntentDetector {
	/** @var KeywordMatcher */
	private $matcher;

	public function __construct( KeywordMatcher $matcher ) {
		$this->matcher = $matcher;
	}

	/** @return array{intent:string,confidence:int,matched_terms:array<int,string>} */
	public function detect( string $query ): array {
		$normalized = $this->matcher->normalize( $query );
		$patterns   = apply_filters( 'adam_bot_intent_patterns', $this->patterns(), $query );
		$patterns   = is_array( $patterns ) ? $patterns : $this->patterns();
		$best       = array( 'intent' => Intent::KNOWLEDGE, 'confidence' => 10, 'matched_terms' => array() );

		foreach ( $patterns as $intent => $terms ) {
			if ( ! is_array( $terms ) ) {
				continue;
			}
			// Navigation language describes how to reach content, but a concrete
			// domain intent (for example renewal) should still choose its provider.
			if ( Intent::WEBSITE === $intent && Intent::KNOWLEDGE !== $best['intent'] ) {
				continue;
			}
			$score   = 0;
			$matched = array();
			foreach ( $terms as $term ) {
				$term = $this->matcher->normalize( is_scalar( $term ) ? (string) $term : '' );
				if ( '' === $term || ! $this->containsTerm( $normalized, $term ) ) {
					continue;
				}
				$matched[] = $term;
				$score    += false !== strpos( $term, ' ' ) ? 36 : 22;
			}
			$score += max( 0, count( $matched ) - 1 ) * 8;
			if ( $score > $best['confidence'] ) {
				$best = array(
					'intent'        => sanitize_key( (string) $intent ),
					'confidence'    => min( 100, $score ),
					'matched_terms' => array_values( array_unique( $matched ) ),
				);
			}
		}

		return $best;
	}

	private function containsTerm( string $query, string $term ): bool {
		return 1 === preg_match( '/(?:^|\s)' . preg_quote( $term, '/' ) . '(?:$|\s)/u', $query );
	}

	/** @return array<string, array<int, string>> */
	private function patterns(): array {
		return array(
			Intent::EVENTS => array( 'event', 'events', 'evento', 'eventos', 'jogo', 'jogos', 'agenda', 'this month', 'este mes', 'deadline', 'deadlines', 'registration deadline', 'registration deadlines', 'prazo de inscricao', 'prazos de inscricao' ),
			Intent::TEAMS => array( 'team', 'teams', 'equipa', 'equipas', 'clube', 'clubes', 'team near me', 'equipa perto' ),
			Intent::FIELDS => array( 'field', 'fields', 'campo', 'campos', 'cqb', 'outdoor', 'indoor', 'municipality', 'municipio' ),
			Intent::PARTNERS => array( 'partner', 'partners', 'parceiro', 'parceiros', 'discount', 'discounts', 'desconto', 'descontos', 'sponsor', 'sponsors', 'shop', 'shops', 'loja', 'lojas' ),
			Intent::NEWS => array( 'latest news', 'recent news', 'news', 'noticias', 'announcement', 'announcements', 'anuncio', 'anuncios', 'association update', 'association updates', 'new partnership', 'new partnerships', 'nova parceria', 'novas parcerias', 'novidades' ),
			Intent::DOCUMENTS => array( 'document', 'documents', 'documento', 'documentos', 'statutes', 'estatutos', 'regulation', 'regulations', 'regulamento', 'regulamentos', 'membership rules', 'regras de socio', 'download', 'downloads' ),
			Intent::MEMBERSHIP => array( 'membership', 'member', 'members', 'membro', 'membros', 'socio', 'socios', 'quota', 'renew', 'renewal', 'renovar', 'renovacao', 'join', 'join adam', 'become a member', 'tornar me socio', 'membership types', 'membership price', 'membership prices', 'membership benefits', 'registration' ),
			Intent::WEBSITE => array( 'open page', 'go to', 'where can i', 'onde posso', 'website', 'site', 'page', 'pagina', 'navigate', 'navegar' ),
		);
	}
}
