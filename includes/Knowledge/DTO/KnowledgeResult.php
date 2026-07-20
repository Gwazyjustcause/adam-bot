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

	/**
	 * Creates a knowledge result.
	 *
	 * @param string $source Source key.
	 * @param string $source_label Human-readable attribution label.
	 * @param string $title Result title.
	 * @param string $content Result content.
	 * @param string $category Optional category.
	 * @param string $url Optional canonical URL.
	 * @param int    $score Relevance score from 0 to 100.
	 */
	public function __construct(
		string $source,
		string $source_label,
		string $title,
		string $content,
		string $category = '',
		string $url = '',
		int $score = 0
	) {
		$this->source       = sanitize_key( $source );
		$this->source_label = trim( $source_label );
		$this->title        = trim( $title );
		$this->content      = trim( $content );
		$this->category     = trim( $category );
		$this->url          = esc_url_raw( $url );
		$this->score        = max( 0, min( 100, $score ) );
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

	/**
	 * Returns a serializable cache representation.
	 *
	 * @return array<string, int|string>
	 */
	public function toArray(): array {
		return array(
			'source'       => $this->source,
			'source_label' => $this->source_label,
			'title'        => $this->title,
			'content'      => $this->content,
			'category'     => $this->category,
			'url'          => $this->url,
			'score'        => $this->score,
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
			(int) ( $data['score'] ?? 0 )
		);
	}
}
