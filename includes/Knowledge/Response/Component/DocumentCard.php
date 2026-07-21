<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class DocumentCard extends AbstractCard {
	/** @param array<string,mixed> $data Document data. */
	public function __construct( array $data ) {
		if ( empty( $data['document_type'] ) && ! empty( $data['file_type'] ) ) { $data['document_type'] = $data['file_type']; }
		if ( empty( $data['button_text'] ) ) { $data['button_text'] = $this->label( 'Descarregar' ); }
		$this->build( 'document', $this->label( 'Documentos' ), $data, array(
			$this->meta( $data, 'document_type', $this->label( 'Tipo' ) ),
			$this->meta( $data, 'updated_at', $this->label( 'Atualizado' ) ),
			$this->meta( $data, 'file_size', $this->label( 'Tamanho do ficheiro' ) ),
		) );
		$this->component['download'] = true;
	}
}
