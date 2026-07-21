<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class FieldCard extends AbstractCard {
	/** @param array<string,mixed> $data Field data. */
	public function __construct( array $data ) {
		if ( empty( $data['type'] ) && ! empty( $data['field_type'] ) ) { $data['type'] = $data['field_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = $this->label( 'Ver campo' ); }
		$this->build( 'field', $this->label( 'Campos associados' ), $data, array(
			$this->meta( $data, 'municipality', $this->label( 'Município' ) ),
			$this->meta( $data, 'type', $this->label( 'Tipo' ) ),
			$this->meta( $data, 'association_status', $this->label( 'Estado de associação' ) ),
		) );
	}
}
