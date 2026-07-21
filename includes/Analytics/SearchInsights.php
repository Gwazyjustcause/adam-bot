<?php
/**
 * Search improvement recommendations.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Analytics;

use AdamBot\Knowledge\DuplicateDetector;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Dynamic\IntentDetector;

defined( 'ABSPATH' ) || exit;

/** Groups repeated or semantically related administrator-visible questions. */
final class SearchInsights {
	/** @var DuplicateDetector */ private $duplicates;
	/** @var IntentDetector */ private $intents;

	public function __construct( DuplicateDetector $duplicates, IntentDetector $intents ) {
		$this->duplicates = $duplicates;
		$this->intents    = $intents;
	}

	/** @param array<int,array<string,mixed>> $questions Questions. @return array<int,array<string,mixed>> */
	public function suggestions( array $questions, int $limit = 8 ): array {
		$groups = array();
		$used   = array();
		foreach ( array_values( $questions ) as $index => $row ) {
			if ( isset( $used[ $index ] ) ) { continue; }
			$question = sanitize_text_field( (string) ( $row['question'] ?? '' ) );
			if ( '' === $question ) { continue; }
			$intent = $this->intents->detect( $question )['intent'];
			$items  = array( $question );
			$total  = (int) ( $row['count'] ?? 1 );
			foreach ( array_values( $questions ) as $candidate_index => $candidate ) {
				if ( $candidate_index <= $index || isset( $used[ $candidate_index ] ) ) { continue; }
				$candidate_question = sanitize_text_field( (string) ( $candidate['question'] ?? '' ) );
				$candidate_intent   = $this->intents->detect( $candidate_question )['intent'];
				$similar = $this->duplicates->similarity( $question, $candidate_question ) >= 55;
				$same_intent = Intent::KNOWLEDGE !== $intent && $intent === $candidate_intent;
				if ( ! $similar && ! $same_intent ) { continue; }
				$items[] = $candidate_question;
				$total  += (int) ( $candidate['count'] ?? 1 );
				$used[ $candidate_index ] = true;
				if ( 5 === count( $items ) ) { break; }
			}
			if ( count( $items ) > 1 ) {
				$groups[] = array( 'intent' => $intent, 'questions' => $items, 'count' => $total );
			}
			if ( count( $groups ) >= $limit ) { break; }
		}
		return $groups;
	}
}
