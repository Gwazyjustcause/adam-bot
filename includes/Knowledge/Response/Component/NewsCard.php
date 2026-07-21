<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class NewsCard extends AbstractCard {
	/** @param array<string,mixed> $data News data. */
	public function __construct( array $data ) {
		if ( empty( $data['date'] ) && ! empty( $data['published_at'] ) ) { $data['date'] = $data['published_at']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Read more', 'adam-bot' ); }
		$this->build( 'news', __( 'News', 'adam-bot' ), $data, array(
			$this->meta( $data, 'date', __( 'Date', 'adam-bot' ) ),
			$this->meta( $data, 'category', __( 'Category', 'adam-bot' ) ),
		) );
	}
}
