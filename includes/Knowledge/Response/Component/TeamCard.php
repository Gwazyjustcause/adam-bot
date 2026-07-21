<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class TeamCard extends AbstractCard {
	/** @param array<string,mixed> $data Team data. */
	public function __construct( array $data ) {
		if ( empty( $data['contact'] ) && ! empty( $data['contact_details'] ) ) { $data['contact'] = $data['contact_details']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = $this->label( 'Ver equipa' ); }
		$this->build( 'team', $this->label( 'Equipas' ), $data, array(
			$this->meta( $data, 'municipality', $this->label( 'Município' ) ),
			$this->meta( $data, 'region', $this->label( 'Região' ) ),
			$this->meta( $data, 'contact', $this->label( 'Contacto' ) ),
		) );
	}
}
