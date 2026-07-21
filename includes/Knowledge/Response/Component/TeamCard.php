<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class TeamCard extends AbstractCard {
	/** @param array<string,mixed> $data Team data. */
	public function __construct( array $data ) {
		if ( empty( $data['contact'] ) && ! empty( $data['contact_details'] ) ) { $data['contact'] = $data['contact_details']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'View team', 'adam-bot' ); }
		$this->build( 'team', __( 'Teams', 'adam-bot' ), $data, array(
			$this->meta( $data, 'municipality', __( 'Municipality', 'adam-bot' ) ),
			$this->meta( $data, 'region', __( 'Region', 'adam-bot' ) ),
			$this->meta( $data, 'contact', __( 'Contact', 'adam-bot' ) ),
		) );
	}
}
