<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class PartnerCard extends AbstractCard {
	/** @param array<string,mixed> $data Partner data. */
	public function __construct( array $data ) {
		if ( empty( $data['category'] ) && ! empty( $data['partner_type'] ) ) { $data['category'] = $data['partner_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Ver parceiro', 'adam-bot' ); }
		$this->build( 'partner', __( 'Parceiros', 'adam-bot' ), $data, array(
			$this->meta( $data, 'category', __( 'Categoria', 'adam-bot' ) ),
			$this->meta( $data, 'discount', __( 'Desconto', 'adam-bot' ) ),
			$this->meta( $data, 'location', __( 'Local', 'adam-bot' ) ),
		) );
	}
}
