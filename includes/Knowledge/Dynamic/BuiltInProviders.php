<?php
/**
 * Built-in live data adapters.
 *
 * @package AdamBot
 */
declare(strict_types=1);
namespace AdamBot\Knowledge\Dynamic;
use AdamBot\Knowledge\Dynamic\Providers\DocumentProvider;
use AdamBot\Knowledge\Dynamic\Providers\EventProvider;
use AdamBot\Knowledge\Dynamic\Providers\FieldProvider;
use AdamBot\Knowledge\Dynamic\Providers\MembershipProvider;
use AdamBot\Knowledge\Dynamic\Providers\NewsProvider;
use AdamBot\Knowledge\Dynamic\Providers\PartnerProvider;
use AdamBot\Knowledge\Dynamic\Providers\TeamProvider;
defined( 'ABSPATH' ) || exit;
final class BuiltInProviders {
	public static function register( DynamicProviderRegistry $registry ): void {
		$registry->registerFactory( 'membership', __( 'Sócios em tempo real', 'adam-bot' ), array( Intent::MEMBERSHIP ), 96, static function (): DynamicProviderInterface { return new MembershipProvider(); } );
		$registry->registerFactory( 'event', __( 'Eventos em tempo real', 'adam-bot' ), array( Intent::EVENTS ), 90, static function (): DynamicProviderInterface { return new EventProvider(); } );
		$registry->registerFactory( 'documents', __( 'Documentos', 'adam-bot' ), array( Intent::DOCUMENTS ), 88, static function (): DynamicProviderInterface { return new DocumentProvider(); } );
		$registry->registerFactory( 'teams', __( 'Equipas da comunidade', 'adam-bot' ), array( Intent::TEAMS ), 86, static function (): DynamicProviderInterface { return new TeamProvider(); } );
		$registry->registerFactory( 'fields', __( 'Campos associados', 'adam-bot' ), array( Intent::FIELDS ), 86, static function (): DynamicProviderInterface { return new FieldProvider(); } );
		$registry->registerFactory( 'partners', __( 'Parceiros', 'adam-bot' ), array( Intent::PARTNERS ), 84, static function (): DynamicProviderInterface { return new PartnerProvider(); } );
		$registry->registerFactory( 'news', __( 'Notícias', 'adam-bot' ), array( Intent::NEWS ), 82, static function (): DynamicProviderInterface { return new NewsProvider(); } );
	}
}
