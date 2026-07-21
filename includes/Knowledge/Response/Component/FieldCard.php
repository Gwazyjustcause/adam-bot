<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class FieldCard extends AbstractCard {
	/** @param array<string,mixed> $data Field data. */
	public function __construct( array $data ) {
		if ( empty( $data['type'] ) && ! empty( $data['field_type'] ) ) { $data['type'] = $data['field_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Ver campo', 'adam-bot' ); }
		$this->build( 'field', __( 'Campos associados', 'adam-bot' ), $data, array(
			$this->meta( $data, 'municipality', __( 'Município', 'adam-bot' ) ),
			$this->meta( $data, 'type', __( 'Tipo', 'adam-bot' ) ),
			$this->meta( $data, 'association_status', __( 'Estado de associação', 'adam-bot' ) ),
		) );
	}
}
