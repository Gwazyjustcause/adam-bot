<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class PartnerCard extends AbstractCard {
	/** @param array<string,mixed> $data Partner data. */
	public function __construct( array $data ) {
		if ( empty( $data['category'] ) && ! empty( $data['partner_type'] ) ) { $data['category'] = $data['partner_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'View partner', 'adam-bot' ); }
		$this->build( 'partner', __( 'Partners', 'adam-bot' ), $data, array(
			$this->meta( $data, 'category', __( 'Category', 'adam-bot' ) ),
			$this->meta( $data, 'discount', __( 'Discount', 'adam-bot' ) ),
			$this->meta( $data, 'location', __( 'Location', 'adam-bot' ) ),
		) );
	}
}
