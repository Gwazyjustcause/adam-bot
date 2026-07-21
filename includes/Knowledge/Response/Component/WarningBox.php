<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;
final class WarningBox implements ComponentInterface {
	/** @var string */ private $text;
	public function __construct( string $text ) { $this->text = sanitize_textarea_field( $text ); }
	public function toArray(): array { return array( 'component' => 'warning_box', 'text' => $this->text ); }
}
