<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class ButtonGroup implements ComponentInterface {
	/** @var array<string,mixed> */ private $component;
	/** @param array<int,array<string,string>> $buttons Buttons. */
	public function __construct( array $buttons ) {
		$clean = array();
		foreach ( $buttons as $button ) {
			if ( ! is_array( $button ) ) { continue; }
			$label = sanitize_text_field( (string) ( $button['label'] ?? '' ) );
			$url = esc_url_raw( (string) ( $button['url'] ?? '' ) );
			if ( '' !== $label && '' !== $url ) { $clean[] = array( 'label' => $label, 'url' => $url ); }
		}
		$this->component = array( 'component' => 'button_group', 'buttons' => array_slice( $clean, 0, 4 ) );
	}
	public function toArray(): array { return $this->component; }
}
