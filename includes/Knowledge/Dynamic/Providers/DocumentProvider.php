<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\DocumentCard;
defined( 'ABSPATH' ) || exit;
final class DocumentProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'documents', __( 'Documents', 'adam-bot' ), array( Intent::DOCUMENTS ), 88, 'adam_bot_dynamic_documents', 300 ); }
	protected function card( array $item ): ?ComponentInterface { return new DocumentCard( $item ); }
}
