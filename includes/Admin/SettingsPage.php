<?php
/**
 * ADAM BOT administration pages.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Admin;

use AdamBot\Analytics\Analytics;
use AdamBot\Knowledge\KnowledgeAdmin;
use AdamBot\Knowledge\KnowledgeSettings;
use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;
use AdamBot\UX\ExperienceSettings;

defined( 'ABSPATH' ) || exit;

/** Owns Dashboard, Conversations, Search Analytics, and Settings screens. */
final class SettingsPage {
	private const EXPERIENCE_GROUP = 'adam_bot_experience';

	/** @var ExperienceSettings */
	private $experience_settings;

	/** @var Analytics */
	private $analytics;

	/** @var KnowledgeAdmin */
	private $knowledge_admin;

	public function __construct( ExperienceSettings $experience_settings, Analytics $analytics, KnowledgeAdmin $knowledge_admin ) {
		$this->experience_settings = $experience_settings;
		$this->analytics           = $analytics;
		$this->knowledge_admin     = $knowledge_admin;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_primary_menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'register_secondary_menu' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/** @return void */
	public function register_primary_menu(): void {
		add_menu_page( __( 'ADAM BOT Dashboard', 'adam-bot' ), __( 'ADAM BOT', 'adam-bot' ), 'manage_options', 'adam-bot', array( $this, 'render_dashboard' ), 'dashicons-format-chat', 80 );
		add_submenu_page( 'adam-bot', __( 'ADAM BOT Dashboard', 'adam-bot' ), __( 'Dashboard', 'adam-bot' ), 'manage_options', 'adam-bot', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'adam-bot', __( 'Conversations', 'adam-bot' ), __( 'Conversations', 'adam-bot' ), 'manage_options', 'adam-bot-conversations', array( $this, 'render_conversations' ) );
	}

	/** @return void */
	public function register_secondary_menu(): void {
		add_submenu_page( 'adam-bot', __( 'Search Analytics', 'adam-bot' ), __( 'Search Analytics', 'adam-bot' ), 'manage_options', 'adam-bot-search-analytics', array( $this, 'render_search_analytics' ) );
		add_submenu_page( 'adam-bot', __( 'ADAM BOT Settings', 'adam-bot' ), __( 'Settings', 'adam-bot' ), 'manage_options', 'adam-bot-settings', array( $this, 'render_settings' ) );
	}

	/** @return void */
	public function register_settings(): void {
		register_setting(
			self::EXPERIENCE_GROUP,
			ExperienceSettings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->experience_settings, 'sanitize' ),
				'default'           => $this->experience_settings->defaults(),
			)
		);
	}

	/** @return void */
	public function render_dashboard(): void {
		$this->authorize();
		$data         = $this->analytics->all();
		$knowledge    = $this->postCounts( ManualSource::POST_TYPE );
		$faq          = $this->postCounts( FAQSource::POST_TYPE );
		$total        = $knowledge['publish'] + $knowledge['draft'] + $faq['publish'] + $faq['draft'];
		$common       = $this->analytics->getCommonQuestions( 5 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADAM BOT Dashboard', 'adam-bot' ); ?></h1>
			<p><?php esc_html_e( 'Manage the content, quality, and performance of every answer from one place.', 'adam-bot' ); ?></p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:1000px;margin:20px 0;">
				<?php $this->metricCard( __( 'Knowledge entries', 'adam-bot' ), $total ); ?>
				<?php $this->metricCard( __( 'Published', 'adam-bot' ), $knowledge['publish'] + $faq['publish'] ); ?>
				<?php $this->metricCard( __( 'Drafts', 'adam-bot' ), $knowledge['draft'] + $faq['draft'] ); ?>
				<?php $this->metricCard( __( 'High confidence', 'adam-bot' ), (int) $data['high_confidence'] ); ?>
				<?php $this->metricCard( __( 'No answer', 'adam-bot' ), (int) $data['no_confidence'] ); ?>
				<?php $this->metricCard( __( 'Average confidence', 'adam-bot' ), $this->analytics->getAverageConfidence(), '%' ); ?>
			</div>

			<h2><?php esc_html_e( 'Quick Actions', 'adam-bot' ); ?></h2>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . ManualSource::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add Knowledge Entry', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . FAQSource::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add FAQ', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=adam-bot-search-analytics' ) ); ?>"><?php esc_html_e( 'Review Search Analytics', 'adam-bot' ); ?></a></p>

			<?php if ( ! empty( $common ) ) : ?>
				<h2><?php esc_html_e( 'Most searched questions', 'adam-bot' ); ?></h2>
				<?php $this->renderQuestionTable( $common, 'count' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return void */
	public function render_conversations(): void {
		$this->authorize();
		$data = $this->analytics->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Conversations', 'adam-bot' ); ?></h1>
			<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Privacy by design:', 'adam-bot' ); ?></strong> <?php esc_html_e( 'ADAM BOT does not store visitor identities or conversation transcripts. This screen shows anonymous aggregate activity only.', 'adam-bot' ); ?></p></div>
			<table class="widefat striped" style="max-width:760px;margin-top:18px;"><tbody>
				<tr><th><?php esc_html_e( 'Total conversations', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $data['total_conversations'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Total messages', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $data['total_messages'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Knowledge answers', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $data['knowledge_hits'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Average response time', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $this->analytics->getAverageResponseTime() ); ?> ms</td></tr>
			</tbody></table>
		</div>
		<?php
	}

	/** @return void */
	public function render_search_analytics(): void {
		$this->authorize();
		$data = $this->analytics->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Search Analytics', 'adam-bot' ); ?></h1>
			<p><?php esc_html_e( 'Anonymous trends reveal missing content and entries that need stronger keywords or clearer questions.', 'adam-bot' ); ?></p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:900px;margin:20px 0;">
				<?php $this->metricCard( __( 'Average confidence', 'adam-bot' ), $this->analytics->getAverageConfidence(), '%' ); ?>
				<?php $this->metricCard( __( 'Average response time', 'adam-bot' ), $this->analytics->getAverageResponseTime(), ' ms' ); ?>
				<?php $this->metricCard( __( 'Low confidence searches', 'adam-bot' ), (int) $data['low_confidence'] ); ?>
				<?php $this->metricCard( __( 'Questions with no answer', 'adam-bot' ), (int) $data['no_confidence'] ); ?>
			</div>

			<h2><?php esc_html_e( 'Most searched questions', 'adam-bot' ); ?></h2>
			<?php $this->renderQuestionTable( $this->analytics->getCommonQuestions( 20 ), 'count' ); ?>
			<h2><?php esc_html_e( 'Questions with no answer', 'adam-bot' ); ?></h2>
			<?php $this->renderQuestionTable( $this->analytics->getNoAnswerQuestions( 20 ), 'no_answer_count' ); ?>
			<h2><?php esc_html_e( 'Low confidence searches', 'adam-bot' ); ?></h2>
			<?php $this->renderQuestionTable( $this->analytics->getLowConfidenceQuestions( 20 ), 'low_confidence_count' ); ?>
			<h2><?php esc_html_e( 'Most viewed Knowledge entries', 'adam-bot' ); ?></h2>
			<?php $this->renderEntryTable( $this->analytics->getMostViewedEntries( 20 ) ); ?>
		</div>
		<?php
	}

	/** @return void */
	public function render_settings(): void {
		$this->authorize();
		$experience = $this->experience_settings->all();
		$option     = ExperienceSettings::OPTION_KEY;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ADAM BOT Settings', 'adam-bot' ); ?></h1>
			<?php settings_errors(); ?>
			<h2><?php esc_html_e( 'Chat Quick Actions', 'adam-bot' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::EXPERIENCE_GROUP ); ?>
				<p class="description"><?php esc_html_e( 'Configure the cards visitors see before their first question.', 'adam-bot' ); ?></p>
				<table class="widefat striped" style="max-width:960px;margin:12px 0 18px;"><thead><tr><th><?php esc_html_e( 'Icon', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Card label', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Question', 'adam-bot' ); ?></th></tr></thead><tbody>
				<?php for ( $index = 0; $index < 8; $index++ ) : $action = $experience['quick_actions'][ $index ] ?? array( 'icon' => '', 'label' => '', 'prompt' => '' ); ?>
					<tr><td><input class="small-text" type="text" maxlength="8" name="<?php echo esc_attr( $option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][icon]" value="<?php echo esc_attr( (string) $action['icon'] ); ?>" /></td><td><input class="regular-text" type="text" maxlength="60" name="<?php echo esc_attr( $option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) $action['label'] ); ?>" /></td><td><input class="large-text" type="text" maxlength="240" name="<?php echo esc_attr( $option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][prompt]" value="<?php echo esc_attr( (string) $action['prompt'] ); ?>" /></td></tr>
				<?php endfor; ?>
				</tbody></table>
				<?php submit_button( __( 'Save Quick Actions', 'adam-bot' ), 'primary', 'submit', false ); ?>
			</form>

			<hr style="margin:32px 0;" />
			<form method="post" action="options.php">
				<?php settings_fields( KnowledgeAdmin::SETTINGS_GROUP ); ?>
				<?php $this->knowledge_admin->render_settings_fields(); ?>
				<?php submit_button( __( 'Save Knowledge Settings', 'adam-bot' ) ); ?>
			</form>

			<hr style="margin:32px 0;" />
			<?php $this->knowledge_admin->render_transfer_tools(); ?>
		</div>
		<?php
	}

	/** @return array<string,int> */
	private function postCounts( string $post_type ): array {
		if ( ! function_exists( 'wp_count_posts' ) ) {
			return array( 'publish' => 0, 'draft' => 0 );
		}
		$counts = wp_count_posts( $post_type );
		return array( 'publish' => (int) ( $counts->publish ?? 0 ), 'draft' => (int) ( $counts->draft ?? 0 ) );
	}

	private function metricCard( string $label, int $value, string $suffix = '' ): void {
		$formatted = function_exists( 'number_format_i18n' ) ? number_format_i18n( $value ) : number_format( $value );
		echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;"><span style="display:block;color:#646970;">' . esc_html( $label ) . '</span><strong style="font-size:28px;line-height:1.4;">' . esc_html( $formatted . $suffix ) . '</strong></div>';
	}

	/** @param array<int,array<string,mixed>> $rows Rows. */
	private function renderQuestionTable( array $rows, string $metric ): void {
		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'No data yet.', 'adam-bot' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:1000px;"><thead><tr><th>' . esc_html__( 'Question', 'adam-bot' ) . '</th><th>' . esc_html__( 'Searches', 'adam-bot' ) . '</th><th>' . esc_html__( 'Average confidence', 'adam-bot' ) . '</th><th>' . esc_html__( 'Average response time', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$count = max( 1, (int) ( $row['count'] ?? 1 ) );
			echo '<tr><td>' . esc_html( (string) ( $row['question'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row[ $metric ] ?? 0 ) ) . '</td><td>' . esc_html( (string) round( (int) ( $row['confidence_total'] ?? 0 ) / $count ) ) . '%</td><td>' . esc_html( (string) round( (int) ( $row['response_time_total'] ?? 0 ) / $count ) ) . ' ms</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** @param array<int,array<string,mixed>> $rows Rows. */
	private function renderEntryTable( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'No viewed entries yet.', 'adam-bot' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:1000px;"><thead><tr><th>' . esc_html__( 'Knowledge entry', 'adam-bot' ) . '</th><th>' . esc_html__( 'Provider', 'adam-bot' ) . '</th><th>' . esc_html__( 'Views', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$title = (string) ( $row['title'] ?? '' );
			$url   = (int) ( $row['entry_id'] ?? 0 ) > 0 ? get_edit_post_link( (int) $row['entry_id'] ) : '';
			echo '<tr><td>' . ( $url ? '<a href="' . esc_url( (string) $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td><td>' . esc_html( (string) ( $row['provider'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['count'] ?? 0 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'adam-bot' ) );
		}
	}
}
