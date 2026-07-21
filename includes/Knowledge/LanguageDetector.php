<?php
/**
 * Lightweight Portuguese/English language detection.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

defined( 'ABSPATH' ) || exit;

/** Detects the visitor language without an external service. */
final class LanguageDetector {
	public function detect( string $text ): string {
		$normalized = function_exists( 'remove_accents' ) ? remove_accents( strtolower( $text ) ) : strtolower( $text );
		$tokens     = preg_split( '/[^a-z]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$tokens     = is_array( $tokens ) ? $tokens : array();
		$english    = array( 'the', 'what', 'where', 'when', 'who', 'why', 'how', 'can', 'does', 'join', 'member', 'membership', 'renew', 'events', 'fields', 'partners', 'contact' );
		$portuguese = array( 'que', 'qual', 'quais', 'onde', 'quando', 'quem', 'como', 'posso', 'pode', 'socio', 'associado', 'quota', 'renovar', 'eventos', 'campos', 'parceiros', 'contactar' );
		$en_score   = count( array_intersect( $tokens, $english ) );
		$pt_score   = count( array_intersect( $tokens, $portuguese ) );

		if ( $en_score > $pt_score ) {
			return 'en';
		}
		if ( $pt_score > $en_score || 1 === preg_match( '/[ãõáéíóúâêôç]/iu', $text ) ) {
			return 'pt';
		}

		$locale = function_exists( 'determine_locale' ) ? determine_locale() : 'pt_PT';
		return 0 === strpos( strtolower( (string) $locale ), 'en' ) ? 'en' : 'pt';
	}
}
