<?php
/**
 * Scored knowledge result DTO.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge\DTO;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable, provider-neutral knowledge result with a 0-100 relevance score.
 */
final class KnowledgeResult {
	/** @var string */
	private $source;

	/** @var string */
	private $source_label;

	/** @var string */
	private $title;

	/** @var string */
	private $content;

	/** @var string */
	private $category;

	/** @var string */
	private $url;

	/** @var int */
	private $score;

	/** @var int */
	private $priority;

	/** @var array<int, string> */
	private $matched_keywords;

	/** @var array<int, string> */
	private $keywords;

	/** @var array<int, string> */
	private $synonyms;

	/** @var int */
	private $search_weight;

	/** @var string */
	private $button_label;

	/** @var array<int, array<string, mixed>> */
	private $response_blocks;

	/** @var array<int, array<string, string>> */
	private $related;

	/** @var int */
	private $object_id;

	/** @var array<int, array<string, mixed>> */
	private $components;

	/** @var string */
	private $language;

	/**
	 * Creates a knowledge result.
	 *
	 * @param string $source Source key.
	 * @param string $source_label Human-readable attribution label.
	 * @param string $title Result title.
	 * @param string $content Result content.
	 * @param string $category Optional category.
	 * @param string $url Optional canonical URL.
	 * @param int                $score Relevance score from 0 to 100.
	 * @param array<int, string> $matched_keywords Terms that contributed to the central rank.
	 * @param int                $priority Provider-owned editorial priority from 0 to 100.
	 * @param array<string,mixed> $attributes Optional provider-neutral display and search metadata.
	 */
	public function __construct(
		string $source,
		string $source_label,
		string $title,
		string $content,
		string $category = '',
		string $url = '',
		int $score = 0,
		array $matched_keywords = array(),
		int $priority = 0,
		array $attributes = array()
	) {
		$this->source           = sanitize_key( $source );
		$this->source_label     = trim( $source_label );
		$this->title            = trim( $title );
		$this->content          = trim( $content );
		$this->category         = trim( $category );
		$this->url              = esc_url_raw( $url );
		$this->score            = max( 0, min( 100, $score ) );
		$this->priority         = max( 0, min( 100, $priority ) );
		$this->matched_keywords = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $matched_keywords )
				)
			)
		);
		$this->keywords         = $this->sanitizeTerms( $attributes['keywords'] ?? array() );
		$this->synonyms         = $this->sanitizeTerms( $attributes['synonyms'] ?? array() );
		$this->search_weight    = max( 0, min( 200, (int) ( $attributes['search_weight'] ?? 100 ) ) );
		$this->button_label     = sanitize_text_field( (string) ( $attributes['button_label'] ?? '' ) );
		$this->response_blocks  = isset( $attributes['response_blocks'] ) && is_array( $attributes['response_blocks'] ) ? $attributes['response_blocks'] : array();
		$this->related          = $this->sanitizeRelated( $attributes['related'] ?? array() );
		$this->object_id        = max( 0, (int) ( $attributes['object_id'] ?? 0 ) );
		$this->components       = isset( $attributes['components'] ) && is_array( $attributes['components'] ) ? array_values( array_filter( $attributes['components'], 'is_array' ) ) : array();
		$this->language         = in_array( sanitize_key( (string) ( $attributes['language'] ?? '' ) ), array( 'pt', 'en' ), true ) ? sanitize_key( (string) $attributes['language'] ) : '';
	}

	/** @return string */
	public function getSource(): string {
		return $this->source;
	}

	/** @return string */
	public function getSourceLabel(): string {
		return $this->source_label;
	}

	/** @return string */
	public function getTitle(): string {
		return $this->title;
	}

	/** @return string */
	public function getContent(): string {
		return $this->content;
	}

	/** @return string */
	public function getCategory(): string {
		return $this->category;
	}

	/** @return string */
	public function getUrl(): string {
		return $this->url;
	}

	/** @return int */
	public function getScore(): int {
		return $this->score;
	}

	/** @return int */
	public function getPriority(): int {
		return $this->priority;
	}

	/** @return array<int, string> */
	public function getMatchedKeywords(): array {
		return $this->matched_keywords;
	}

	/** @return array<int, string> */
	public function getKeywords(): array {
		return $this->keywords;
	}

	/** @return array<int, string> */
	public function getSynonyms(): array {
		return $this->synonyms;
	}

	/** @return int */
	public function getSearchWeight(): int {
		return $this->search_weight;
	}

	/** @return string */
	public function getButtonLabel(): string {
		return $this->button_label;
	}

	/** @return array<int, array<string, mixed>> */
	public function getResponseBlocks(): array {
		return $this->response_blocks;
	}

	/** @return array<int, array<string, string>> */
	public function getRelated(): array {
		return $this->related;
	}

	/** @return int */
	public function getObjectId(): int {
		return $this->object_id;
	}

	/** @return array<int, array<string, mixed>> */
	public function getComponents(): array {
		return $this->components;
	}

	public function getLanguage(): string {
		return $this->language;
	}

	/** @return string */
	public function getId(): string {
		return md5( $this->source . '|' . $this->language . '|' . $this->title . '|' . $this->url );
	}

	/**
	 * Returns a ranked copy while preserving normalized provider data.
	 *
	 * @param int                $score Central relevance score.
	 * @param array<int, string> $matched_keywords Matched normalized terms.
	 * @return self
	 */
	public function withRank( int $score, array $matched_keywords ): self {
		return new self(
			$this->source,
			$this->source_label,
			$this->title,
			$this->content,
			$this->category,
			$this->url,
			$score,
			$matched_keywords,
			$this->priority,
			$this->attributes()
		);
	}

	/** @param array<int,array<string,string>> $related Provider follow-up questions. */
	public function withRelated( array $related ): self {
		$attributes            = $this->attributes();
		$attributes['related'] = $related;
		return new self( $this->source, $this->source_label, $this->title, $this->content, $this->category, $this->url, $this->score, $this->matched_keywords, $this->priority, $attributes );
	}

	/**
	 * Returns a serializable cache representation.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'source'           => $this->source,
			'source_label'     => $this->source_label,
			'title'            => $this->title,
			'content'          => $this->content,
			'category'         => $this->category,
			'url'              => $this->url,
			'score'            => $this->score,
			'priority'         => $this->priority,
			'matched_keywords' => $this->matched_keywords,
			'attributes'       => $this->attributes(),
		);
	}

	/**
	 * Restores a result from a cache representation.
	 *
	 * @param array<string, mixed> $data Cached data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		return new self(
			(string) ( $data['source'] ?? '' ),
			(string) ( $data['source_label'] ?? '' ),
			(string) ( $data['title'] ?? '' ),
			(string) ( $data['content'] ?? '' ),
			(string) ( $data['category'] ?? '' ),
			(string) ( $data['url'] ?? '' ),
			(int) ( $data['score'] ?? 0 ),
			isset( $data['matched_keywords'] ) && is_array( $data['matched_keywords'] ) ? $data['matched_keywords'] : array(),
			(int) ( $data['priority'] ?? 0 ),
			isset( $data['attributes'] ) && is_array( $data['attributes'] ) ? $data['attributes'] : array()
		);
	}

	/** @return array<string, mixed> */
	private function attributes(): array {
		return array(
			'keywords'        => $this->keywords,
			'synonyms'        => $this->synonyms,
			'search_weight'   => $this->search_weight,
			'button_label'    => $this->button_label,
			'response_blocks' => $this->response_blocks,
			'related'         => $this->related,
			'object_id'       => $this->object_id,
			'components'      => $this->components,
			'language'        => $this->language,
		);
	}

	/** @param mixed $terms Candidate terms. @return array<int, string> */
	private function sanitizeTerms( $terms ): array {
		if ( is_string( $terms ) ) {
			$terms = preg_split( '/[,\r\n]+/u', $terms, -1, PREG_SPLIT_NO_EMPTY );
		}

		if ( ! is_array( $terms ) ) {
			return array();
		}

		return array_slice(
			array_values(
				array_unique(
					array_filter( array_map( static function ( $term ): string { return sanitize_text_field( (string) $term ); }, $terms ) )
				)
			),
			0,
			100
		);
	}

	/** @param mixed $related Candidate related entries. @return array<int, array<string, string>> */
	private function sanitizeRelated( $related ): array {
		if ( ! is_array( $related ) ) {
			return array();
		}

		$clean = array();
		foreach ( $related as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title    = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$question = sanitize_text_field( (string) ( $item['question'] ?? $title ) );
			if ( '' !== $title && '' !== $question ) {
				$clean[] = array( 'title' => $title, 'question' => $question );
			}
			if ( 12 === count( $clean ) ) {
				break;
			}
		}

		return $clean;
	}
}
