<?php
/**
 * ADAM BOT AI settings page.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Admin;

use AdamBot\AI\Settings\AISettings;

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

	/**
	 * Creates the settings component.
	 *
	 * @param AISettings $settings Settings repository.
	 */
	public function __construct( AISettings $settings ) {
		$this->settings = $settings;
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

		$settings  = $this->settings->all();
		$option    = AISettings::OPTION_KEY;
		$key_saved = '' !== (string) $settings['openai_api_key'];
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

				<?php submit_button( __( 'Save', 'adam-bot' ), 'primary', 'submit', false ); ?>
				<?php submit_button( __( 'Restore Default', 'adam-bot' ), 'secondary', 'adam_bot_restore_prompt', false ); ?>
			</form>
		</div>
		<?php
	}
}
