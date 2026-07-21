<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class DocumentCard extends AbstractCard {
	/** @param array<string,mixed> $data Document data. */
	public function __construct( array $data ) {
		if ( empty( $data['document_type'] ) && ! empty( $data['file_type'] ) ) { $data['document_type'] = $data['file_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = __( 'Descarregar', 'adam-bot' ); }
		$this->build( 'document', __( 'Documentos', 'adam-bot' ), $data, array(
			$this->meta( $data, 'document_type', __( 'Tipo', 'adam-bot' ) ),
			$this->meta( $data, 'updated_at', __( 'Atualizado', 'adam-bot' ) ),
			$this->meta( $data, 'file_size', __( 'Tamanho do ficheiro', 'adam-bot' ) ),
		) );
		$this->component['download'] = true;
	}
}
