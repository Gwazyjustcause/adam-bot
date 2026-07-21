<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\FieldCard;
defined( 'ABSPATH' ) || exit;
final class FieldProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'fields', __( 'Campos associados', 'adam-bot' ), array( Intent::FIELDS ), 86, 'adam_bot_dynamic_fields', 180 ); }
	protected function card( array $item ): ?ComponentInterface { return new FieldCard( $item ); }
}
