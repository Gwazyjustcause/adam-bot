<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Response\Component;
defined( 'ABSPATH' ) || exit;

/** Serializable rich response component. */
interface ComponentInterface {
	/** @return array<string,mixed> */
	public function toArray(): array;
}
