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
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Register', 'adam-bot' ); }
		$this->build( 'event', __( 'Events', 'adam-bot' ), $data, array(
			$this->meta( $data, 'date', __( 'Date', 'adam-bot' ) ),
			$this->meta( $data, 'location', __( 'Location', 'adam-bot' ) ),
			$this->meta( $data, 'available_places', __( 'Available places', 'adam-bot' ) ),
			$this->meta( $data, 'price', __( 'Price', 'adam-bot' ) ),
			$this->meta( $data, 'registration_deadline', __( 'Registration deadline', 'adam-bot' ) ),
			$this->meta( $data, 'teams', __( 'Teams', 'adam-bot' ) ),
		) );
	}
}
