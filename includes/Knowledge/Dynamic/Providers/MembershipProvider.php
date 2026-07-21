<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\DTO\KnowledgeResult;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ButtonGroup;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\InformationBox;
defined( 'ABSPATH' ) || exit;
final class MembershipProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'membership', __( 'Sócios em tempo real', 'adam-bot' ), array( Intent::MEMBERSHIP ), 96, 'adam_bot_dynamic_membership', 180 ); }
	protected function items( string $query, string $intent ): array {
		$items = parent::items( $query, $intent );
		$legacy = apply_filters( 'adam_bot_knowledge_membership_items', array(), $query );
		return array_merge( $items, is_array( $legacy ) ? $legacy : array() );
	}
	protected function card( array $item ): ?ComponentInterface { unset( $item ); return null; }
	protected function mapItem( array $item, string $query, string $intent ): ?KnowledgeResult {
		$result = parent::mapItem( $item, $query, $intent );
		if ( ! $result ) { return null; }
		$components = array( ( new InformationBox( $result->getContent() ) )->toArray() );
		$url = esc_url_raw( (string) ( $item['url'] ?? $item['registration_url'] ?? '' ) );
		if ( '' !== $url ) {
			$components[] = ( new ButtonGroup( array( array( 'label' => sanitize_text_field( (string) ( $item['button_text'] ?? __( 'Tornar-me sócio', 'adam-bot' ) ) ), 'url' => $url ) ) ) )->toArray();
		}
		$data = $result->toArray();
		$data['attributes']['components'] = $components;
		return KnowledgeResult::fromArray( $data );
	}
}
