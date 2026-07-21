<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\EventCard;
defined( 'ABSPATH' ) || exit;
final class EventProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'event', __( 'Live Events', 'adam-bot' ), array( Intent::EVENTS ), 90, 'adam_bot_dynamic_events', 90 ); }
	protected function items( string $query, string $intent ): array {
		$items = parent::items( $query, $intent );
		$legacy = apply_filters( 'adam_bot_knowledge_event_items', array(), $query );
		return array_merge( $items, is_array( $legacy ) ? $legacy : array() );
	}
	protected function card( array $item ): ?ComponentInterface { return new EventCard( $item ); }
}
