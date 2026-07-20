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
	private const MAX_QUESTIONS = 50;

	/**
	 * Returns normalized counters.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$data   = array_merge( $this->defaults(), $stored );

		foreach ( array( 'total_conversations', 'total_messages', 'response_count', 'total_response_time_ms', 'knowledge_hits', 'general_responses', 'mixed_responses' ) as $key ) {
			$data[ $key ] = max( 0, (int) $data[ $key ] );
		}

		$data['questions'] = isset( $data['questions'] ) && is_array( $data['questions'] ) ? $data['questions'] : array();

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
			'general_responses'      => 0,
			'mixed_responses'        => 0,
			'questions'              => array(),
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
	 * @return void
	 */
	public function record(
		string $question,
		bool $new_conversation,
		int $response_time_ms,
		string $classification,
		bool $knowledge_hit,
		bool $count_question = true
	): void {
		$data = $this->all();

		$data['total_conversations'] += $new_conversation ? 1 : 0;
		$data['total_messages']++;
		$data['response_count']++;
		$data['total_response_time_ms'] += max( 0, $response_time_ms );
		$data['knowledge_hits'] += $knowledge_hit ? 1 : 0;

		if ( 'general_ai' === $classification ) {
			$data['general_responses']++;
		} elseif ( 'mixed' === $classification ) {
			$data['mixed_responses']++;
		}

		if ( $count_question ) {
			$this->recordQuestion( $data, $question );
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
	private function recordQuestion( array &$data, string $question ): void {
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
			return;
		}

		$data['questions'][ $key ] = array(
			'question' => $clean,
			'count'    => 1,
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

	/** @return string */
	private function truncate( string $value, int $maximum ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $maximum ) : substr( $value, 0, $maximum );
	}
}
