<?php
/**
 * Privacy-friendly aggregate usage analytics.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Stores counters and scrubbed question aggregates, never conversations.
 */
final class Analytics {
	/** WordPress option name. */
	public const OPTION_KEY = 'adam_bot_analytics';

	/** Maximum distinct aggregate question records. */
	private const MAX_QUESTIONS = 500;

	/**
	 * Returns normalized counters.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored   = get_option( self::OPTION_KEY, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = $this->defaults();
		$data     = array_merge( $defaults, array_intersect_key( $stored, $defaults ) );

		foreach ( array( 'total_conversations', 'total_messages', 'response_count', 'total_response_time_ms', 'knowledge_hits', 'high_confidence', 'medium_confidence', 'low_confidence', 'no_confidence' ) as $key ) {
			$data[ $key ] = max( 0, (int) $data[ $key ] );
		}

		$data['questions'] = isset( $data['questions'] ) && is_array( $data['questions'] ) ? $data['questions'] : array();
		$data['entry_views'] = isset( $data['entry_views'] ) && is_array( $data['entry_views'] ) ? $data['entry_views'] : array();
		$data['total_confidence'] = max( 0, (int) ( $data['total_confidence'] ?? 0 ) );

		return $data;
	}

	/** @return array<string, mixed> */
	public function defaults(): array {
		return array(
			'total_conversations'    => 0,
			'total_messages'         => 0,
			'response_count'         => 0,
			'total_response_time_ms' => 0,
			'knowledge_hits'         => 0,
			'high_confidence'        => 0,
			'medium_confidence'      => 0,
			'low_confidence'         => 0,
			'no_confidence'          => 0,
			'questions'              => array(),
			'entry_views'            => array(),
			'total_confidence'       => 0,
		);
	}

	/**
	 * Records one aggregate exchange without an identifier or transcript.
	 *
	 * @param string $question User question.
	 * @param bool   $new_conversation Whether this is the session's first request.
	 * @param int    $response_time_ms End-to-end response time.
	 * @param string $classification Internal confidence class.
	 * @param bool   $knowledge_hit Whether trusted ADAM context was present.
	 * @param bool   $count_question Whether to add the question to common-question aggregates.
	 * @param int    $confidence Numeric confidence from 0 to 100.
	 * @param int    $entry_id Matched knowledge object ID when available.
	 * @param string $entry_title Matched entry title.
	 * @param string $provider Matched provider key.
	 * @return void
	 */
	public function record(
		string $question,
		bool $new_conversation,
		int $response_time_ms,
		string $classification,
		bool $knowledge_hit,
		bool $count_question = true,
		int $confidence = 0,
		int $entry_id = 0,
		string $entry_title = '',
		string $provider = ''
	): void {
		$data = $this->all();

		$data['total_conversations'] += $new_conversation ? 1 : 0;
		$data['total_messages']++;
		$data['response_count']++;
		$data['total_response_time_ms'] += max( 0, $response_time_ms );
		$data['knowledge_hits'] += $knowledge_hit ? 1 : 0;
		$data['total_confidence'] += max( 0, min( 100, $confidence ) );

		$confidence_key = in_array( $classification, array( 'high', 'medium', 'low' ), true )
			? $classification . '_confidence'
			: 'no_confidence';
		$data[ $confidence_key ]++;

		if ( $count_question ) {
			$this->recordQuestion( $data, $question, $classification, $confidence, $response_time_ms, $entry_id, $entry_title, $provider );
		}

		if ( $knowledge_hit && $entry_id > 0 ) {
			$key = sanitize_key( $provider ) . ':' . $entry_id;
			if ( ! isset( $data['entry_views'][ $key ] ) ) {
				$data['entry_views'][ $key ] = array(
					'entry_id' => $entry_id,
					'title'    => $this->truncate( sanitize_text_field( $entry_title ), 140 ),
					'provider' => sanitize_key( $provider ),
					'count'    => 0,
				);
			}
			$data['entry_views'][ $key ]['count'] = (int) $data['entry_views'][ $key ]['count'] + 1;
			if ( count( $data['entry_views'] ) > 500 ) {
				uasort( $data['entry_views'], static function ( array $left, array $right ): int { return (int) ( $right['count'] ?? 0 ) <=> (int) ( $left['count'] ?? 0 ); } );
				$data['entry_views'] = array_slice( $data['entry_views'], 0, 500, true );
			}
		}

		update_option( self::OPTION_KEY, $data, false );
	}

	/**
	 * Returns the most common scrubbed questions.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, int|string>>
	 */
	public function getCommonQuestions( int $limit = 10 ): array {
		$questions = array_values( $this->all()['questions'] );
		usort(
			$questions,
			static function ( array $left, array $right ): int {
				return (int) ( $right['count'] ?? 0 ) <=> (int) ( $left['count'] ?? 0 );
			}
		);

		return array_slice( $questions, 0, max( 1, $limit ) );
	}

	/** @return array<int, array<string, mixed>> */
	public function getNoAnswerQuestions( int $limit = 10 ): array {
		return $this->filteredQuestions( 'no_answer_count', $limit );
	}

	/** @return array<int, array<string, mixed>> */
	public function getLowConfidenceQuestions( int $limit = 10 ): array {
		return $this->filteredQuestions( 'low_confidence_count', $limit );
	}

	/** @return array<int, array<string, mixed>> */
	public function getMostViewedEntries( int $limit = 10 ): array {
		$rows = array_values( $this->all()['entry_views'] );
		usort( $rows, static function ( array $left, array $right ): int { return (int) ( $right['count'] ?? 0 ) <=> (int) ( $left['count'] ?? 0 ); } );
		return array_slice( $rows, 0, max( 1, $limit ) );
	}

	public function getAverageConfidence(): int {
		$data = $this->all();
		return (int) round( (int) $data['total_confidence'] / max( 1, (int) $data['response_count'] ) );
	}

	public function getAverageResponseTime(): int {
		$data = $this->all();
		return (int) round( (int) $data['total_response_time_ms'] / max( 1, (int) $data['response_count'] ) );
	}

	/** @return void */
	public function ensureDefaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $this->defaults(), '', 'no' );
		}
	}

	/** @return void */
	public static function activate(): void {
		( new self() )->ensureDefaults();
	}

	/**
	 * Adds a PII-scrubbed, bounded standalone question aggregate.
	 *
	 * @param array<string, mixed> $data Analytics data, modified by reference.
	 * @param string               $question Raw question.
	 * @return void
	 */
	private function recordQuestion(
		array &$data,
		string $question,
		string $classification,
		int $confidence,
		int $response_time_ms,
		int $entry_id,
		string $entry_title,
		string $provider
	): void {
		$clean = sanitize_text_field( $question );
		$clean = preg_replace( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[email]', $clean ) ?? $clean;
		$clean = preg_replace( '#https?://\S+#iu', '[link]', $clean ) ?? $clean;
		$clean = preg_replace( '/(?:\+?\d[\d\s().-]{6,}\d)/u', '[number]', $clean ) ?? $clean;
		$clean = preg_replace( '/\s+/u', ' ', trim( $clean ) ) ?? trim( $clean );
		$clean = $this->truncate( $clean, 140 );

		if ( '' === $clean ) {
			return;
		}

		$key = md5( function_exists( 'mb_strtolower' ) ? mb_strtolower( $clean ) : strtolower( $clean ) );

		if ( isset( $data['questions'][ $key ] ) ) {
			$data['questions'][ $key ]['count'] = (int) $data['questions'][ $key ]['count'] + 1;
			$data['questions'][ $key ]['confidence_total'] = (int) ( $data['questions'][ $key ]['confidence_total'] ?? 0 ) + max( 0, min( 100, $confidence ) );
			$data['questions'][ $key ]['response_time_total'] = (int) ( $data['questions'][ $key ]['response_time_total'] ?? 0 ) + max( 0, $response_time_ms );
			$data['questions'][ $key ]['no_answer_count'] = (int) ( $data['questions'][ $key ]['no_answer_count'] ?? 0 ) + ( 'none' === $classification ? 1 : 0 );
			$data['questions'][ $key ]['low_confidence_count'] = (int) ( $data['questions'][ $key ]['low_confidence_count'] ?? 0 ) + ( 'low' === $classification ? 1 : 0 );
			return;
		}

		$data['questions'][ $key ] = array(
			'question' => $clean,
			'count'    => 1,
			'confidence_total'    => max( 0, min( 100, $confidence ) ),
			'response_time_total' => max( 0, $response_time_ms ),
			'no_answer_count'     => 'none' === $classification ? 1 : 0,
			'low_confidence_count'=> 'low' === $classification ? 1 : 0,
			'entry_id'            => max( 0, $entry_id ),
			'entry_title'         => $this->truncate( sanitize_text_field( $entry_title ), 140 ),
			'provider'            => sanitize_key( $provider ),
		);

		if ( count( $data['questions'] ) <= self::MAX_QUESTIONS ) {
			return;
		}

		uasort(
			$data['questions'],
			static function ( array $left, array $right ): int {
				return (int) ( $right['count'] ?? 0 ) <=> (int) ( $left['count'] ?? 0 );
			}
		);
		$data['questions'] = array_slice( $data['questions'], 0, self::MAX_QUESTIONS, true );
	}

	/** @return array<int, array<string, mixed>> */
	private function filteredQuestions( string $metric, int $limit ): array {
		$rows = array_values(
			array_filter(
				$this->all()['questions'],
				static function ( array $row ) use ( $metric ): bool { return (int) ( $row[ $metric ] ?? 0 ) > 0; }
			)
		);
		usort( $rows, static function ( array $left, array $right ) use ( $metric ): int { return (int) ( $right[ $metric ] ?? 0 ) <=> (int) ( $left[ $metric ] ?? 0 ); } );
		return array_slice( $rows, 0, max( 1, $limit ) );
	}

	/** @return string */
	private function truncate( string $value, int $maximum ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum ) : substr( $value, 0, $maximum );
	}
}
