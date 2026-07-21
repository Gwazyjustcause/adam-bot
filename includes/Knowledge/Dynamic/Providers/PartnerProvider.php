<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\PartnerCard;
defined( 'ABSPATH' ) || exit;
final class PartnerProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'partners', 'partners', array( Intent::PARTNERS ), 84, 'adam_bot_dynamic_partners', 240 ); }
	public function getLabel(): string { return $this->translatedLabel( 'Parceiros' ); }
	protected function card( array $item ): ?ComponentInterface { return new PartnerCard( $item ); }
}
