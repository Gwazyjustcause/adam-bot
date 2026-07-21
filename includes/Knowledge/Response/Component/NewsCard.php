<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class NewsCard extends AbstractCard {
	/** @param array<string,mixed> $data News data. */
	public function __construct( array $data ) {
		if ( empty( $data['date'] ) && ! empty( $data['published_at'] ) ) { $data['date'] = $data['published_at']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = $this->label( 'Ler mais' ); }
		$this->build( 'news', $this->label( 'Notícias' ), $data, array(
			$this->meta( $data, 'date', $this->label( 'Data' ) ),
			$this->meta( $data, 'category', $this->label( 'Categoria' ) ),
		) );
	}
}
