<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\TeamCard;
defined( 'ABSPATH' ) || exit;
final class TeamProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'teams', 'teams', array( Intent::TEAMS ), 86, 'adam_bot_dynamic_teams', 180 ); }
	public function getLabel(): string { return $this->translatedLabel( 'Equipas da comunidade' ); }
	protected function card( array $item ): ?ComponentInterface { return new TeamCard( $item ); }
}
