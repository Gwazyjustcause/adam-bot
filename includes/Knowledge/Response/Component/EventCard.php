<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class EventCard extends AbstractCard {
	/** @param array<string,mixed> $data Event data. */
	public function __construct( array $data ) {
		if ( empty( $data['date'] ) && ! empty( $data['start_date'] ) ) { $data['date'] = $data['start_date']; }
		if ( empty( $data['url'] ) && ! empty( $data['registration_url'] ) ) { $data['url'] = $data['registration_url']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = $this->label( 'Inscrever-me' ); }
		$this->build( 'event', $this->label( 'Eventos' ), $data, array(
			$this->meta( $data, 'date', $this->label( 'Data' ) ),
			$this->meta( $data, 'location', $this->label( 'Local' ) ),
			$this->meta( $data, 'available_places', $this->label( 'Lugares disponíveis' ) ),
			$this->meta( $data, 'price', $this->label( 'Preço' ) ),
			$this->meta( $data, 'registration_deadline', $this->label( 'Prazo de inscrição' ) ),
			$this->meta( $data, 'teams', $this->label( 'Equipas' ) ),
		) );
	}
}
