<?php
/**
 * ADAM BOT AI settings page.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Admin;

use AdamBot\Analytics\Analytics;
use AdamBot\AI\Settings\AISettings;
use AdamBot\UX\ExperienceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the minimal ADAM BOT -> Settings administration screen.
 */
final class SettingsPage {
	/** Settings group used by options.php. */
	private const SETTINGS_GROUP = 'adam_bot_ai';

	/** Menu slug. */
	private const MENU_SLUG = 'adam-bot';

	/** @var AISettings */
	private $settings;

	/** @var ExperienceSettings */
	private $experience_settings;

	/** @var Analytics */
	private $analytics;

	/**
	 * Creates the settings component.
	 *
	 * @param AISettings        $settings AI settings repository.
	 * @param ExperienceSettings $experience_settings Public experience settings.
	 * @param Analytics          $analytics Aggregate analytics repository.
	 */
	public function __construct( AISettings $settings, ExperienceSettings $experience_settings, Analytics $analytics ) {
		$this->settings            = $settings;
		$this->experience_settings = $experience_settings;
		$this->analytics           = $analytics;
	}

	/**
	 * Registers administration hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Registers the top-level menu and its Settings item.
	 *
	 * @return void
	 */
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

	/**
	 * Registers the single structured option.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			AISettings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->defaults(),
			)
		);

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

	/**
	 * Renders the settings page without exposing the stored API key.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'adam-bot' ) );
		}

		$settings            = $this->settings->all();
		$experience          = $this->experience_settings->all();
		$analytics           = $this->analytics->all();
		$option              = AISettings::OPTION_KEY;
		$experience_option   = ExperienceSettings::OPTION_KEY;
		$key_saved           = '' !== (string) $settings['openai_api_key'];
		$response_count      = max( 1, (int) $analytics['response_count'] );
		$average_response_ms = (int) round( (int) $analytics['total_response_time_ms'] / $response_count );
		$knowledge_hit_rate  = (int) round( (int) $analytics['knowledge_hits'] * 100 / $response_count );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADAM BOT Settings', 'adam-bot' ); ?></h1>
			<?php settings_errors( AISettings::OPTION_KEY ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="adam-bot-openai-key"><?php esc_html_e( 'OpenAI API Key', 'adam-bot' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								class="regular-text"
								id="adam-bot-openai-key"
								name="<?php echo esc_attr( $option ); ?>[openai_api_key]"
								value=""
								autocomplete="new-password"
								placeholder="<?php echo esc_attr( $key_saved ? __( 'API key configured', 'adam-bot' ) : 'sk-...' ); ?>"
							/>
							<?php if ( $key_saved ) : ?>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the currently configured key.', 'adam-bot' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Provider', 'adam-bot' ); ?></th>
						<td>
							<label>
								<input type="radio" name="<?php echo esc_attr( $option ); ?>[provider]" value="openai" checked="checked" />
								<?php esc_html_e( 'OpenAI', 'adam-bot' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="adam-bot-model"><?php esc_html_e( 'Model', 'adam-bot' ); ?></label>
						</th>
						<td>
							<select id="adam-bot-model" name="<?php echo esc_attr( $option ); ?>[model]">
								<?php foreach ( $this->settings->models() as $model => $label ) : ?>
									<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $settings['model'], $model ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="adam-bot-temperature"><?php esc_html_e( 'Temperature', 'adam-bot' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								class="small-text"
								id="adam-bot-temperature"
								name="<?php echo esc_attr( $option ); ?>[temperature]"
								value="<?php echo esc_attr( (string) $settings['temperature'] ); ?>"
								min="0"
								max="2"
								step="0.1"
							/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="adam-bot-max-tokens"><?php esc_html_e( 'Max Tokens', 'adam-bot' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								class="small-text"
								id="adam-bot-max-tokens"
								name="<?php echo esc_attr( $option ); ?>[max_tokens]"
								value="<?php echo esc_attr( (string) $settings['max_tokens'] ); ?>"
								min="1"
								max="4000"
								step="1"
							/>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="adam-bot-system-prompt"><?php esc_html_e( 'System Prompt', 'adam-bot' ); ?></label>
						</th>
						<td>
							<textarea
								class="large-text code"
								id="adam-bot-system-prompt"
								name="<?php echo esc_attr( $option ); ?>[system_prompt]"
								rows="12"
								maxlength="<?php echo esc_attr( (string) AISettings::MAX_SYSTEM_PROMPT_CHARACTERS ); ?>"
							><?php echo esc_textarea( (string) $settings['system_prompt'] ); ?></textarea>
						</td>
					</tr>
				</table>

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
				<?php submit_button( __( 'Restore Default', 'adam-bot' ), 'secondary', 'adam_bot_restore_prompt', false ); ?>
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
					<tr><th><?php esc_html_e( 'General AI responses', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['general_responses'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Mixed responses', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $analytics['mixed_responses'] ); ?></td></tr>
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
