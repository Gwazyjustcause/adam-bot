<?php
/**
 * Central deterministic result ranking.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\Search;

use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\LanguageDetector;

defined( 'ABSPATH' ) || exit;

/**
 * Weighs phrases, titles, keywords, synonyms, categories, priority, and coverage.
 */
final class ResultRanker {
	/** @var KeywordMatcher */
	private $matcher;

	/** @var LanguageDetector */
	private $language_detector;

	/** @param KeywordMatcher $matcher Shared text normalizer. */
	public function __construct( KeywordMatcher $matcher, ?LanguageDetector $language_detector = null ) {
		$this->matcher           = $matcher;
		$this->language_detector = $language_detector ?: new LanguageDetector();
	}

	/**
	 * Returns centrally ranked copies of provider candidates.
	 *
	 * @param string                       $query Resolved search query.
	 * @param array<int, KnowledgeResult>  $candidates Provider candidates.
	 * @param string                       $topic Current session topic.
	 * @param array<int, string>           $recent_result_ids Recently shown result IDs.
	 * @return array<int, KnowledgeResult>
	 */
	public function rank( string $query, array $candidates, string $topic = '', array $recent_result_ids = array() ): array {
		$normalized_query = $this->matcher->normalize( $query );
		$query_terms      = $this->terms( $normalized_query );
		$query_language   = $this->language_detector->detect( $query );
		$ranked           = array();

		foreach ( $candidates as $candidate ) {
			if ( ! $candidate instanceof KnowledgeResult ) {
				continue;
			}

			$title    = $this->matcher->normalize( $candidate->getTitle() );
			$content  = $this->matcher->normalize( $candidate->getContent() );
			$category = $this->matcher->normalize( $candidate->getCategory() );
			$keywords = $this->matcher->normalize( implode( ' ', $candidate->getKeywords() ) );
			$synonyms = $this->matcher->normalize( implode( ' ', $candidate->getSynonyms() ) );
			$score    = 0;
			$matched  = array();

			if ( strlen( $normalized_query ) >= 4 ) {
				$score += false !== strpos( $title, $normalized_query ) ? 30 : 0;
				$score += false !== strpos( $category, $normalized_query ) ? 20 : 0;
				$score += false !== strpos( $content, $normalized_query ) ? 14 : 0;
			}

			foreach ( $query_terms as $term ) {
				$term_score = $this->scoreTerm( $term, $title, $content, $category );
				$term_score += $this->containsWord( $keywords, $term ) ? 18 : ( false !== strpos( $keywords, $term ) ? 9 : 0 );
				$term_score += $this->containsWord( $synonyms, $term ) ? 14 : ( false !== strpos( $synonyms, $term ) ? 7 : 0 );

				if ( $term_score > 0 ) {
					$score    += $term_score;
					$matched[] = $term;
				}
			}

			if ( ! empty( $matched ) ) {
				$coverage = count( array_unique( $matched ) ) / max( 1, count( $query_terms ) );
				$score   += (int) round( $coverage * 18 );
				$score   += min( 12, count( array_unique( $matched ) ) * 3 );
				$score   += (int) round( $candidate->getPriority() * 0.15 );
			}

			if ( '' !== $topic && $this->matchesTopic( $topic, $title . ' ' . $category . ' ' . $keywords . ' ' . $synonyms . ' ' . $candidate->getSource() ) ) {
				$score += 10;
			}

			if ( in_array( $candidate->getId(), $recent_result_ids, true ) && $score > 0 ) {
				$score += 5;
			}

			if ( $score > 0 && '' !== $candidate->getLanguage() ) {
				$score = $candidate->getLanguage() === $query_language ? $score + 12 : (int) round( $score * 0.55 );
			}

			$score    = (int) round( $score * $candidate->getSearchWeight() / 100 );
			$ranked[] = $candidate->withRank( min( 100, $score ), array_values( array_unique( $matched ) ) );
		}

		usort(
			$ranked,
			static function ( KnowledgeResult $left, KnowledgeResult $right ): int {
				if ( $left->getScore() === $right->getScore() ) {
					return strcmp( $left->getTitle(), $right->getTitle() );
				}

				return $right->getScore() <=> $left->getScore();
			}
		);

		return $ranked;
	}

	/** @return int */
	public function countMeaningfulTerms( string $query ): int {
		return count( $this->terms( $this->matcher->normalize( $query ) ) );
	}

	/** @return array<int, string> */
	public function getTopicTerms( string $topic ): array {
		$terms = preg_split( '/[_-]+/', sanitize_key( $topic ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $terms ) ? $terms : array();
	}

	/** @return int */
	private function scoreTerm( string $term, string $title, string $content, string $category ): int {
		$score = 0;
		$score += $this->containsWord( $title, $term ) ? 15 : ( false !== strpos( $title, $term ) ? 8 : 0 );
		$score += $this->containsWord( $category, $term ) ? 11 : ( false !== strpos( $category, $term ) ? 6 : 0 );
		$score += $this->containsWord( $content, $term ) ? 6 : ( false !== strpos( $content, $term ) ? 3 : 0 );

		return $score;
	}

	/** @return bool */
	private function matchesTopic( string $topic, string $candidate ): bool {
		$candidate = $this->matcher->normalize( $candidate );

		foreach ( $this->getTopicTerms( $topic ) as $term ) {
			if ( $this->containsWord( $candidate, $term ) ) {
				return true;
			}
		}

		return false;
	}

	/** @return array<int, string> */
	private function terms( string $normalized ): array {
		$stop_words = array(
			'a', 'ao', 'aos', 'as', 'com', 'como', 'da', 'das', 'de', 'do', 'dos', 'e', 'em',
			'eu', 'me', 'na', 'nas', 'no', 'nos', 'o', 'os', 'ou', 'para', 'por', 'que', 'qual',
			'quais', 'se', 'tem', 'um', 'uma', 'and', 'are', 'for', 'how', 'is', 'of', 'on',
			'the', 'to', 'what', 'when', 'where', 'which', 'with', 'next', 'upcoming', 'proximo', 'proximos',
		);
		$terms = preg_split( '/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$terms = is_array( $terms ) ? $terms : array();

		return array_values(
			array_unique(
				array_filter(
					$terms,
					static function ( string $term ) use ( $stop_words ): bool {
						return strlen( $term ) >= 3 && ! in_array( $term, $stop_words, true );
					}
				)
			)
		);
	}

	/** @return bool */
	private function containsWord( string $haystack, string $word ): bool {
		return 1 === preg_match( '/(?:^|\s)' . preg_quote( $word, '/' ) . '(?:$|\s)/u', $haystack );
	}
}
