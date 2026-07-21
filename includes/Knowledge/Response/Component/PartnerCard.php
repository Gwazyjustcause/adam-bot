<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class PartnerCard extends AbstractCard {
	/** @param array<string,mixed> $data Partner data. */
	public function __construct( array $data ) {
		if ( empty( $data['category'] ) && ! empty( $data['partner_type'] ) ) { $data['category'] = $data['partner_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = $this->label( 'Ver parceiro' ); }
		$this->build( 'partner', $this->label( 'Parceiros' ), $data, array(
			$this->meta( $data, 'category', $this->label( 'Categoria' ) ),
			$this->meta( $data, 'discount', $this->label( 'Desconto' ) ),
			$this->meta( $data, 'location', $this->label( 'Local' ) ),
		) );
	}
}
