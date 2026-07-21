<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class FieldCard extends AbstractCard {
	/** @param array<string,mixed> $data Field data. */
	public function __construct( array $data ) {
		if ( empty( $data['type'] ) && ! empty( $data['field_type'] ) ) { $data['type'] = $data['field_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'View field', 'adam-bot' ); }
		$this->build( 'field', __( 'Associated Fields', 'adam-bot' ), $data, array(
			$this->meta( $data, 'municipality', __( 'Municipality', 'adam-bot' ) ),
			$this->meta( $data, 'type', __( 'Type', 'adam-bot' ) ),
			$this->meta( $data, 'association_status', __( 'Association status', 'adam-bot' ) ),
		) );
	}
}
