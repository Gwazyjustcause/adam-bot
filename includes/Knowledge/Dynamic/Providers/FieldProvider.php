<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\FieldCard;
defined( 'ABSPATH' ) || exit;
final class FieldProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'fields', 'fields', array( Intent::FIELDS ), 86, 'adam_bot_dynamic_fields', 180 ); }
	public function getLabel(): string { return $this->translatedLabel( 'Campos associados' ); }
	protected function card( array $item ): ?ComponentInterface { return new FieldCard( $item ); }
}
