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
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Inscrever-me', 'adam-bot' ); }
		$this->build( 'event', __( 'Eventos', 'adam-bot' ), $data, array(
			$this->meta( $data, 'date', __( 'Data', 'adam-bot' ) ),
			$this->meta( $data, 'location', __( 'Local', 'adam-bot' ) ),
			$this->meta( $data, 'available_places', __( 'Lugares disponíveis', 'adam-bot' ) ),
			$this->meta( $data, 'price', __( 'Preço', 'adam-bot' ) ),
			$this->meta( $data, 'registration_deadline', __( 'Prazo de inscrição', 'adam-bot' ) ),
			$this->meta( $data, 'teams', __( 'Equipas', 'adam-bot' ) ),
		) );
	}
}
