<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class DocumentCard extends AbstractCard {
	/** @param array<string,mixed> $data Document data. */
	public function __construct( array $data ) {
		if ( empty( $data['document_type'] ) && ! empty( $data['file_type'] ) ) { $data['document_type'] = $data['file_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Download', 'adam-bot' ); }
		$this->build( 'document', __( 'Documents', 'adam-bot' ), $data, array(
			$this->meta( $data, 'document_type', __( 'Type', 'adam-bot' ) ),
			$this->meta( $data, 'updated_at', __( 'Updated', 'adam-bot' ) ),
			$this->meta( $data, 'file_size', __( 'File size', 'adam-bot' ) ),
		) );
		$this->component['download'] = true;
	}
}
