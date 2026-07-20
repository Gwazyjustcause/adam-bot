<?php
/**
 * ADAM BOT experience settings page.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Admin;

use AdamBot\Analytics\Analytics;
use AdamBot\UX\ExperienceSettings;

defined( 'ABSPATH' ) || exit;

/** Registers the ADAM BOT -> Settings administration screen. */
final class SettingsPage {
	/** Settings group used by options.php. */
	private const SETTINGS_GROUP = 'adam_bot_experience';

	/** Menu slug. */
	private const MENU_SLUG = 'adam-bot';

	/** @var ExperienceSettings */
	private $experience_settings;

	/** @var Analytics */
	private $analytics;

	/**
	 * @param ExperienceSettings $experience_settings Public experience settings.
	 * @param Analytics          $analytics Aggregate analytics repository.
	 */
	public function __construct( ExperienceSettings $experience_settings, Analytics $analytics ) {
		$this->experience_settings = $experience_settings;
		$this->analytics           = $analytics;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/** @return void */
	public function register_menu(): void {
		add_menu_page(
			__( 'ADAM BOT Settings', 'adam-bot' ),
			__( 'ADAM BOT', 'adam-bot' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-format-chat',
			80
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'ADAM BOT Settings', 'adam-bot' ),
			__( 'Settings', 'adam-bot' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/** @return void */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			ExperienceSettings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->experience_settings, 'sanitize' ),
				'default'           => $this->experience_settings->defaults(),
			)
		);
	}

	/** @return void */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'adam-bot' ) );
		}

		$experience          = $this->experience_settings->all();
		$analytics           = $this->analytics->all();
		$experience_option   = ExperienceSettings::OPTION_KEY;
		$response_count      = max( 1, (int) $analytics['response_count'] );
		$average_response_ms = (int) round( (int) $analytics['total_response_time_ms'] / $response_count );
		$knowledge_hit_rate  = (int) round( (int) $analytics['knowledge_hits'] * 100 / $response_count );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADAM BOT Settings', 'adam-bot' ); ?></h1>
			<?php settings_errors( ExperienceSettings::OPTION_KEY ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<h2><?php esc_html_e( 'Quick Actions', 'adam-bot' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure the cards users see before their first question. Empty rows are ignored.', 'adam-bot' ); ?></p>
				<table class="widefat striped" style="max-width:960px;margin:12px 0 24px;">
					<thead>
						<tr>
							<th style="width:90px;"><?php esc_html_e( 'Icon', 'adam-bot' ); ?></th>
							<th><?php esc_html_e( 'Card label', 'adam-bot' ); ?></th>
							<th><?php esc_html_e( 'Prompt sent to ADAM BOT', 'adam-bot' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php for ( $index = 0; $index < 8; $index++ ) : ?>
							<?php $action = $experience['quick_actions'][ $index ] ?? array( 'icon' => '', 'label' => '', 'prompt' => '' ); ?>
							<tr>
								<td><input class="small-text" type="text" maxlength="8" aria-label="<?php echo esc_attr( sprintf( __( 'Quick action %d icon', 'adam-bot' ), $index + 1 ) ); ?>" name="<?php echo esc_attr( $experience_option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][icon]" value="<?php echo esc_attr( (string) $action['icon'] ); ?>" /></td>
								<td><input class="regular-text" type="text" maxlength="60" aria-label="<?php echo esc_attr( sprintf( __( 'Quick action %d label', 'adam-bot' ), $index + 1 ) ); ?>" name="<?php echo esc_attr( $experience_option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) $action['label'] ); ?>" /></td>
								<td><input class="large-text" type="text" maxlength="240" aria-label="<?php echo esc_attr( sprintf( __( 'Quick action %d prompt', 'adam-bot' ), $index + 1 ) ); ?>" name="<?php echo esc_attr( $experience_option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][prompt]" value="<?php echo esc_attr( (string) $action['prompt'] ); ?>" /></td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save', 'adam-bot' ), 'primary', 'submit', false ); ?>
			</form>

			<hr style="margin:32px 0;" />
			<h2><?php esc_html_e( 'Anonymous Usage Statistics', 'adam-bot' ); ?></h2>
			<p class="description"><?php esc_html_e( 'ADAM BOT stores aggregate counters and PII-scrubbed question summaries only. It does not store conversation transcripts or visitor identifiers.', 'adam-bot' ); ?></p>
			<table class="widefat striped" style="max-width:760px;margin-top:12px;">
				<tbody>
					<tr><th><?php esc_html_e( 'Total conversations', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['total_conversations'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Total messages', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['total_messages'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Average response time', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $average_response_ms ); ?> ms</td></tr>
					<tr><th><?php esc_html_e( 'Knowledge hit rate', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $knowledge_hit_rate ); ?>%</td></tr>
					<tr><th><?php esc_html_e( 'High confidence', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['high_confidence'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Medium confidence', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['medium_confidence'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Low confidence', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['low_confidence'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'No match', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['no_confidence'] ); ?></td></tr>
				</tbody>
			</table>

			<?php $common_questions = $this->analytics->getCommonQuestions(); ?>
			<?php if ( ! empty( $common_questions ) ) : ?>
				<h3><?php esc_html_e( 'Most common questions', 'adam-bot' ); ?></h3>
				<ol>
					<?php foreach ( $common_questions as $common ) : ?>
						<li><?php echo esc_html( (string) ( $common['question'] ?? '' ) ); ?> <span aria-label="<?php esc_attr_e( 'Number of uses', 'adam-bot' ); ?>">(<?php echo esc_html( (string) ( $common['count'] ?? 0 ) ); ?>)</span></li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</div>
		<?php
	}
}
