<?php
/** @package AdamBot */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic\Providers;
use AdamBot\Knowledge\Dynamic\Intent;
use AdamBot\Knowledge\Response\Component\ComponentInterface;
use AdamBot\Knowledge\Response\Component\NewsCard;
defined( 'ABSPATH' ) || exit;
final class NewsProvider extends AbstractFilterProvider {
	public function __construct() { parent::__construct( 'news', 'news', array( Intent::NEWS ), 82, 'adam_bot_dynamic_news', 120 ); }
	public function getLabel(): string { return $this->translatedLabel( 'Notícias' ); }
	protected function card( array $item ): ?ComponentInterface { return new NewsCard( $item ); }
}
