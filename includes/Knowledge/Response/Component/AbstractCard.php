<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;

/** Shared sanitization for all public card types. */
abstract class AbstractCard implements ComponentInterface {
	/** @var array<string,mixed> */
	protected $component;

	/**
	 * @param array<string,mixed> $data Source data.
	 * @param array<int,string>   $meta Meta lines.
	 */
	protected function build( string $type, string $group_label, array $data, array $meta = array() ): void {
		$actions = array();
		foreach ( isset( $data['actions'] ) && is_array( $data['actions'] ) ? $data['actions'] : array() as $action ) {
			if ( ! is_array( $action ) ) { continue; }
			$label = sanitize_text_field( (string) ( $action['label'] ?? '' ) );
			$url   = esc_url_raw( (string) ( $action['url'] ?? '' ) );
			if ( '' !== $label && '' !== $url ) { $actions[] = array( 'label' => $label, 'url' => $url ); }
		}
		$this->component = array(
			'component'   => 'card',
			'type'        => sanitize_key( $type ),
			'group_label' => $this->truncate( sanitize_text_field( $group_label ), 80 ),
			'image'       => esc_url_raw( (string) ( $data['image'] ?? $data['cover_image'] ?? '' ) ),
			'title'       => $this->truncate( sanitize_text_field( (string) ( $data['title'] ?? $data['name'] ?? '' ) ), 140 ),
			'description' => $this->truncate( sanitize_text_field( (string) ( $data['description'] ?? $data['summary'] ?? $data['excerpt'] ?? $data['content'] ?? '' ) ), 320 ),
			'meta'        => array_values( array_filter( array_map( 'sanitize_text_field', $meta ) ) ),
			'url'         => esc_url_raw( (string) ( $data['url'] ?? $data['profile_url'] ?? $data['download_url'] ?? '' ) ),
			'action_label'=> $this->truncate( sanitize_text_field( (string) ( $data['button_text'] ?? $data['action_label'] ?? '' ) ), 60 ),
			'actions'     => array_slice( $actions, 0, 3 ),
		);
	}

	/** @return array<string,mixed> */
	public function toArray(): array { return $this->component; }

	/** @param array<string,mixed> $data */
	protected function meta( array $data, string $key, string $label ): string {
		$value = $this->truncate( sanitize_text_field( (string) ( $data[ $key ] ?? '' ) ), 160 );
		return '' !== $value ? $label . ': ' . $value : '';
	}

	/** Translates card labels only after WordPress init has begun. */
	protected function label( string $text ): string {
		$ready = ( function_exists( 'did_action' ) && did_action( 'init' ) > 0 ) || ( function_exists( 'doing_action' ) && doing_action( 'init' ) );
		return $ready ? __( $text, 'adam-bot' ) : $text;
	}

	private function truncate( string $value, int $limit ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}
}
