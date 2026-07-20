<?php
/**
 * Knowledge administration component.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/**
 * Registers FAQ/manual managers and the source-selection screen.
 */
final class KnowledgeAdmin {
	/** Settings group used by options.php. */
	private const SETTINGS_GROUP = 'adam_bot_knowledge';

	/** Knowledge screen slug. */
	private const MENU_SLUG = 'adam-bot-knowledge';

	/** Entry meta nonce action. */
	private const NONCE_ACTION = 'adam_bot_save_knowledge_entry';

	/** Entry meta nonce field. */
	private const NONCE_FIELD = 'adam_bot_knowledge_nonce';

	/** @var KnowledgeSettings */
	private $settings;

	/** @param KnowledgeSettings $settings Knowledge settings. */
	public function __construct( KnowledgeSettings $settings ) {
		$this->settings = $settings;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_entry_meta' ), 20, 3 );
		add_action( 'save_post', array( $this, 'maybe_invalidate_content_cache' ), 30, 3 );
		add_action( 'adam_bot_knowledge_invalidate_cache', array( $this->settings, 'bumpCacheVersion' ) );
	}

	/** @return void */
	public function register_post_types(): void {
		register_post_type(
			FAQSource::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'FAQs', 'adam-bot' ),
					'singular_name' => __( 'FAQ', 'adam-bot' ),
					'add_new_item'  => __( 'Add FAQ', 'adam-bot' ),
					'edit_item'     => __( 'Edit FAQ', 'adam-bot' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'adam-bot',
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor', 'page-attributes' ),
				'menu_icon'    => 'dashicons-editor-help',
			)
		);

		register_post_type(
			ManualSource::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Knowledge Entries', 'adam-bot' ),
					'singular_name' => __( 'Knowledge Entry', 'adam-bot' ),
					'add_new_item'  => __( 'Add Knowledge Entry', 'adam-bot' ),
					'edit_item'     => __( 'Edit Knowledge Entry', 'adam-bot' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'adam-bot',
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor' ),
				'menu_icon'    => 'dashicons-welcome-write-blog',
			)
		);
	}

	/** @return void */
	public function register_menu(): void {
		add_submenu_page(
			'adam-bot',
			__( 'ADAM BOT Knowledge', 'adam-bot' ),
			__( 'Knowledge', 'adam-bot' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/** @return void */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			KnowledgeSettings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->settings, 'sanitize' ),
				'default'           => $this->settings->defaults(),
			)
		);
	}

	/** @return void */
	public function register_meta_boxes(): void {
		add_meta_box(
			'adam-bot-faq-details',
			__( 'FAQ Details', 'adam-bot' ),
			array( $this, 'render_faq_meta_box' ),
			FAQSource::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'adam-bot-knowledge-details',
			__( 'Knowledge Details', 'adam-bot' ),
			array( $this, 'render_manual_meta_box' ),
			ManualSource::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Renders FAQ category, priority, and enabled controls.
	 *
	 * @param object $post Current post.
	 * @return void
	 */
	public function render_faq_meta_box( $post ): void {
		$this->render_common_meta_fields( $post, true );
	}

	/**
	 * Renders manual-entry category and enabled controls.
	 *
	 * @param object $post Current post.
	 * @return void
	 */
	public function render_manual_meta_box( $post ): void {
		$this->render_common_meta_fields( $post, false );
	}

	/**
	 * Saves category, priority, and per-entry enablement.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 * @param bool   $update Whether this is an update.
	 * @return void
	 */
	public function save_entry_meta( int $post_id, $post, bool $update ): void {
		unset( $update );

		if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, array( FAQSource::POST_TYPE, ManualSource::POST_TYPE ), true ) ) {
			return;
		}

		if (
			! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$category = isset( $_POST['adam_bot_knowledge_category'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['adam_bot_knowledge_category'] ) )
			: '';
		$enabled  = isset( $_POST['adam_bot_knowledge_enabled'] ) ? '1' : '0';

		update_post_meta( $post_id, FAQSource::CATEGORY_META, $category );
		update_post_meta( $post_id, FAQSource::ENABLED_META, $enabled );

		if ( FAQSource::POST_TYPE === $post->post_type ) {
			$priority = isset( $_POST['adam_bot_knowledge_priority'] )
				? max( 0, min( 100, absint( $_POST['adam_bot_knowledge_priority'] ) ) )
				: 50;
			update_post_meta( $post_id, FAQSource::PRIORITY_META, $priority );
		}
	}

	/**
	 * Invalidates cached queries when a contributing post changes.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 * @param bool   $update Whether this is an update.
	 * @return void
	 */
	public function maybe_invalidate_content_cache( int $post_id, $post, bool $update ): void {
		unset( $update );

		if ( wp_is_post_revision( $post_id ) || ! isset( $post->post_type ) ) {
			return;
		}

		$tracked_types = array( FAQSource::POST_TYPE, ManualSource::POST_TYPE );

		if ( 'page' === $post->post_type && in_array( $post_id, $this->settings->getPageIds(), true ) ) {
			$this->settings->bumpCacheVersion();
			return;
		}

		$event_types = apply_filters( 'adam_bot_knowledge_event_post_types', array( 'event', 'events', 'tribe_events' ) );
		$event_types = is_array( $event_types ) ? array_map( 'sanitize_key', $event_types ) : array();

		if ( in_array( $post->post_type, array_merge( $tracked_types, $event_types ), true ) ) {
			$this->settings->bumpCacheVersion();
		}
	}

	/** @return void */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'adam-bot' ) );
		}

		$settings = $this->settings->all();
		$pages    = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'menu_order,post_title',
			)
		);
		$option   = KnowledgeSettings::OPTION_KEY;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADAM BOT Knowledge', 'adam-bot' ); ?></h1>
			<p><?php esc_html_e( 'Choose which ADAM information may contribute concise context to AI answers.', 'adam-bot' ); ?></p>
			<?php settings_errors( KnowledgeSettings::OPTION_KEY ); ?>

			<p>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . FAQSource::POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage FAQs', 'adam-bot' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ManualSource::POST_TYPE ) ); ?>"><?php esc_html_e( 'Manage Knowledge Entries', 'adam-bot' ); ?></a>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<h2><?php esc_html_e( 'Knowledge Sources', 'adam-bot' ); ?></h2>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Enabled knowledge sources', 'adam-bot' ); ?></legend>
					<?php foreach ( $this->settings->sources() as $source => $label ) : ?>
						<label style="display:block;margin:0 0 8px;">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option ); ?>[enabled_sources][]"
								value="<?php echo esc_attr( $source ); ?>"
								<?php checked( in_array( $source, $settings['enabled_sources'], true ) ); ?>
							/>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<h2><?php esc_html_e( 'Website Pages', 'adam-bot' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Only selected published pages can be searched by ADAM BOT.', 'adam-bot' ); ?></p>
				<fieldset style="max-height:320px;overflow:auto;background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:680px;">
					<legend class="screen-reader-text"><?php esc_html_e( 'Selected website pages', 'adam-bot' ); ?></legend>
					<?php if ( empty( $pages ) ) : ?>
						<p><?php esc_html_e( 'No published pages are available.', 'adam-bot' ); ?></p>
					<?php else : ?>
						<?php foreach ( $pages as $page ) : ?>
							<label style="display:block;margin:0 0 8px;">
								<input
									type="checkbox"
									name="<?php echo esc_attr( $option ); ?>[page_ids][]"
									value="<?php echo esc_attr( (string) $page->ID ); ?>"
									<?php checked( in_array( (int) $page->ID, $settings['page_ids'], true ) ); ?>
								/>
								<?php echo esc_html( get_the_title( $page ) ); ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</fieldset>

				<?php submit_button( __( 'Save', 'adam-bot' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the shared entry fields.
	 *
	 * @param object $post Current post.
	 * @param bool   $show_priority Whether to render FAQ priority.
	 * @return void
	 */
	private function render_common_meta_fields( $post, bool $show_priority ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$category = (string) get_post_meta( $post->ID, FAQSource::CATEGORY_META, true );
		$enabled  = (string) get_post_meta( $post->ID, FAQSource::ENABLED_META, true );
		$enabled  = '0' !== $enabled;
		?>
		<p class="description">
			<?php echo esc_html( $show_priority ? __( 'Use the title for the question and the editor for the answer.', 'adam-bot' ) : __( 'Use the title and editor for the knowledge entry.', 'adam-bot' ) ); ?>
		</p>
		<p>
			<label for="adam-bot-knowledge-category"><strong><?php esc_html_e( 'Category', 'adam-bot' ); ?></strong></label>
			<input
				type="text"
				class="widefat"
				id="adam-bot-knowledge-category"
				name="adam_bot_knowledge_category"
				value="<?php echo esc_attr( $category ); ?>"
			/>
		</p>

		<?php if ( $show_priority ) : ?>
			<?php $priority = '' === (string) get_post_meta( $post->ID, FAQSource::PRIORITY_META, true ) ? 50 : (int) get_post_meta( $post->ID, FAQSource::PRIORITY_META, true ); ?>
			<p>
				<label for="adam-bot-knowledge-priority"><strong><?php esc_html_e( 'Priority', 'adam-bot' ); ?></strong></label>
				<input
					type="number"
					class="small-text"
					id="adam-bot-knowledge-priority"
					name="adam_bot_knowledge_priority"
					value="<?php echo esc_attr( (string) $priority ); ?>"
					min="0"
					max="100"
				/>
			</p>
		<?php endif; ?>

		<p>
			<label>
				<input type="checkbox" name="adam_bot_knowledge_enabled" value="1" <?php checked( $enabled ); ?> />
				<strong><?php esc_html_e( 'Enabled', 'adam-bot' ); ?></strong>
			</label>
		</p>
		<?php
	}
}
