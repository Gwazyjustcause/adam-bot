<?php
/**
 * Lightweight weighted keyword matching.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Scores exact, partial, and phrase matches without semantic dependencies.
 */
final class KeywordMatcher {
	/**
	 * Common words that should not influence relevance.
	 *
	 * @var array<int, string>
	 */
	private $stop_words = array(
		'a', 'ao', 'aos', 'as', 'com', 'como', 'da', 'das', 'de', 'do', 'dos',
		'e', 'em', 'esta', 'este', 'eu', 'me', 'na', 'nas', 'no', 'nos', 'o',
		'os', 'ou', 'para', 'por', 'que', 'qual', 'quais', 'se', 'tem', 'um', 'uma',
		'and', 'are', 'for', 'how', 'is', 'of', 'on', 'the', 'to', 'what', 'when',
		'where', 'which', 'with',
	);

	/**
	 * Calculates a bounded relevance score.
	 *
	 * @param string $query User question.
	 * @param string $title Candidate title.
	 * @param string $content Candidate content.
	 * @param string $category Candidate category.
	 * @param int    $source_priority Fixed source boost.
	 * @param int    $entry_priority Per-entry boost.
	 * @return int
	 */
	public function score(
		string $query,
		string $title,
		string $content,
		string $category = '',
		int $source_priority = 0,
		int $entry_priority = 0
	): int {
		$normalized_query = $this->normalize( $query );
		$normalized_title = $this->normalize( $title );
		$normalized_body  = $this->normalize( $content );
		$normalized_group = $this->normalize( $category );
		$tokens           = $this->tokens( $normalized_query );

		if ( '' === $normalized_query || empty( $tokens ) ) {
			return 0;
		}

		$score = 0;

		if ( strlen( $normalized_query ) >= 4 ) {
			if ( false !== strpos( $normalized_title, $normalized_query ) ) {
				$score += 42;
			}

			if ( false !== strpos( $normalized_body, $normalized_query ) ) {
				$score += 28;
			}
		}

		foreach ( $tokens as $token ) {
			if ( $this->containsWord( $normalized_title, $token ) ) {
				$score += 14;
			} elseif ( false !== strpos( $normalized_title, $token ) ) {
				$score += 8;
			}

			if ( $this->containsWord( $normalized_group, $token ) ) {
				$score += 10;
			} elseif ( false !== strpos( $normalized_group, $token ) ) {
				$score += 5;
			}

			if ( $this->containsWord( $normalized_body, $token ) ) {
				$score += 6;
			} elseif ( false !== strpos( $normalized_body, $token ) ) {
				$score += 3;
			}
		}

		if ( 0 === $score ) {
			return 0;
		}

		$score += max( 0, min( 20, $source_priority ) );
		$score += max( 0, min( 15, $entry_priority ) );

		return min( 100, $score );
	}

	/**
	 * Checks whether a query contains any intent term using partial matching.
	 *
	 * @param string             $query User question.
	 * @param array<int, string> $terms Intent terms.
	 * @return bool
	 */
	public function hasIntent( string $query, array $terms ): bool {
		$normalized = $this->normalize( $query );

		foreach ( $terms as $term ) {
			$term = $this->normalize( $term );

			if ( '' !== $term && false !== strpos( $normalized, $term ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes text consistently for matching and cache keys.
	 *
	 * @param string $value Text to normalize.
	 * @return string
	 */
	public function normalize( string $value ): string {
		$value = remove_accents( wp_strip_all_tags( $value ) );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );

		return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	}

	/**
	 * Returns meaningful unique tokens from normalized text.
	 *
	 * @param string $normalized Normalized query.
	 * @return array<int, string>
	 */
	private function tokens( string $normalized ): array {
		$tokens = preg_split( '/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$tokens = is_array( $tokens ) ? $tokens : array();
		$tokens = array_filter(
			$tokens,
			function ( string $token ): bool {
				return strlen( $token ) >= 3 && ! in_array( $token, $this->stop_words, true );
			}
		);

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Tests an exact normalized word boundary.
	 *
	 * @param string $haystack Normalized candidate text.
	 * @param string $word Normalized token.
	 * @return bool
	 */
	private function containsWord( string $haystack, string $word ): bool {
		return 1 === preg_match( '/(?:^|\s)' . preg_quote( $word, '/' ) . '(?:$|\s)/u', $haystack );
	}
}
