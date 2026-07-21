<?php
/**
 * ADAM BOT administration pages.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Admin;

use AdamBot\Analytics\Analytics;
use AdamBot\Analytics\SearchInsights;
use AdamBot\Knowledge\KnowledgeAdmin;
use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;
use AdamBot\Knowledge\SiteKnowledgeIndexer;
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

	/** @var SearchInsights */
	private $search_insights;

	/** @var SiteKnowledgeIndexer */
	private $site_indexer;

	public function __construct( ExperienceSettings $experience_settings, Analytics $analytics, KnowledgeAdmin $knowledge_admin, SearchInsights $search_insights, SiteKnowledgeIndexer $site_indexer ) {
		$this->experience_settings = $experience_settings;
		$this->analytics           = $analytics;
		$this->knowledge_admin     = $knowledge_admin;
		$this->search_insights     = $search_insights;
		$this->site_indexer        = $site_indexer;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_primary_menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'register_secondary_menu' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/** @return void */
	public function register_primary_menu(): void {
		add_menu_page( __( 'Painel do ADAM BOT', 'adam-bot' ), __( 'ADAM BOT', 'adam-bot' ), 'manage_options', 'adam-bot', array( $this, 'render_dashboard' ), 'dashicons-format-chat', 80 );
		add_submenu_page( 'adam-bot', __( 'Painel do ADAM BOT', 'adam-bot' ), __( 'Painel', 'adam-bot' ), 'manage_options', 'adam-bot', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'adam-bot', __( 'Conversas', 'adam-bot' ), __( 'Conversas', 'adam-bot' ), 'manage_options', 'adam-bot-conversations', array( $this, 'render_conversations' ) );
	}

	/** @return void */
	public function register_secondary_menu(): void {
		add_submenu_page( 'adam-bot', __( 'Analítica de pesquisa', 'adam-bot' ), __( 'Analítica de pesquisa', 'adam-bot' ), 'manage_options', 'adam-bot-search-analytics', array( $this, 'render_search_analytics' ) );
		add_submenu_page( 'adam-bot', __( 'Definições do ADAM BOT', 'adam-bot' ), __( 'Definições', 'adam-bot' ), 'manage_options', 'adam-bot-settings', array( $this, 'render_settings' ) );
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
		$providers    = array_slice( $this->analytics->getProviderUsage(), 0, 5, true );
		$topics       = array_slice( $this->analytics->getIntentUsage(), 0, 5, true );
		$successful   = (int) $data['high_confidence'] + (int) $data['medium_confidence'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Painel do ADAM BOT', 'adam-bot' ); ?></h1>
			<p><?php esc_html_e( 'Acompanhe a saúde, a qualidade e o desempenho do assistente num único local.', 'adam-bot' ); ?></p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:1000px;margin:20px 0;">
				<?php $this->metricCard( __( 'Total de conversas', 'adam-bot' ), (int) $data['total_conversations'] ); ?>
				<?php $this->metricCard( __( 'Perguntas respondidas', 'adam-bot' ), (int) $data['knowledge_hits'] ); ?>
				<?php $this->metricCard( __( 'Pesquisas bem-sucedidas', 'adam-bot' ), $successful ); ?>
				<?php $this->metricCard( __( 'Perguntas sem resposta', 'adam-bot' ), (int) $data['no_confidence'] ); ?>
				<?php $this->metricCard( __( 'Tempo médio de resposta', 'adam-bot' ), $this->analytics->getAverageResponseTime(), ' ms' ); ?>
				<?php $this->metricCard( __( 'Confiança média', 'adam-bot' ), $this->analytics->getAverageConfidence(), '%' ); ?>
			</div>

			<h2><?php esc_html_e( 'Ações rápidas', 'adam-bot' ); ?></h2>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . ManualSource::POST_TYPE ) ); ?>"><?php esc_html_e( 'Adicionar conhecimento', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=adam-bot-unanswered' ) ); ?>"><?php esc_html_e( 'Rever perguntas sem resposta', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=adam-bot-search-analytics' ) ); ?>"><?php esc_html_e( 'Ver analítica', 'adam-bot' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=adam-bot-providers' ) ); ?>"><?php esc_html_e( 'Gerir fornecedores', 'adam-bot' ); ?></a></p>

			<p class="description"><?php echo esc_html( sprintf( __( '%1$d entradas de conhecimento no total; %2$d publicadas e %3$d em rascunho.', 'adam-bot' ), $total, $knowledge['publish'] + $faq['publish'], $knowledge['draft'] + $faq['draft'] ) ); ?></p>

			<?php if ( ! empty( $common ) ) : ?>
				<h2><?php esc_html_e( 'Perguntas mais pesquisadas', 'adam-bot' ); ?></h2>
				<?php $this->renderQuestionTable( $common, 'count' ); ?>
			<?php endif; ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;max-width:1000px;margin-top:24px;">
				<div><h2><?php esc_html_e( 'Tópicos mais pesquisados', 'adam-bot' ); ?></h2><?php $this->renderUsageTable( $topics, __( 'Tópico', 'adam-bot' ) ); ?></div>
				<div><h2><?php esc_html_e( 'Fornecedores mais utilizados', 'adam-bot' ); ?></h2><?php $this->renderUsageTable( $providers, __( 'Fornecedor', 'adam-bot' ) ); ?></div>
			</div>
			<h2><?php esc_html_e( 'Conhecimento mais consultado', 'adam-bot' ); ?></h2>
			<?php $this->renderEntryTable( $this->analytics->getMostViewedEntries( 5 ) ); ?>
		</div>
		<?php
	}

	/** @return void */
	public function render_conversations(): void {
		$this->authorize();
		$data = $this->analytics->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Analítica de conversas', 'adam-bot' ); ?></h1>
			<div class="notice notice-info inline"><p><strong><?php esc_html_e( 'Privacidade desde a conceção:', 'adam-bot' ); ?></strong> <?php esc_html_e( 'O ADAM BOT não guarda identidades de visitantes nem transcrições. Este ecrã apresenta apenas atividade agregada e anónima.', 'adam-bot' ); ?></p></div>
			<table class="widefat striped" style="max-width:760px;margin-top:18px;"><tbody>
				<tr><th><?php esc_html_e( 'Total de conversas', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $data['total_conversations'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Total de mensagens', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $data['total_messages'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Respostas com conhecimento', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $data['knowledge_hits'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Tempo médio de resposta', 'adam-bot' ); ?></th><td><?php echo esc_html( (string) $this->analytics->getAverageResponseTime() ); ?> ms</td></tr>
			</tbody></table>
		</div>
		<?php
	}

	/** @return void */
	public function render_search_analytics(): void {
		$this->authorize();
		$data = $this->analytics->all();
		$days = max( 7, min( 180, absint( $_GET['days'] ?? 30 ) ) );
		$selected_provider = sanitize_key( wp_unslash( (string) ( $_GET['provider'] ?? '' ) ) );
		$selected_category = sanitize_key( wp_unslash( (string) ( $_GET['category'] ?? '' ) ) );
		$only_unanswered   = ! empty( $_GET['unanswered'] );
		$logs = array_values( array_filter( $this->analytics->getProviderLogs( 1000 ), static function ( array $row ) use ( $selected_provider, $selected_category, $only_unanswered ): bool {
			return ( '' === $selected_provider || $selected_provider === ( $row['provider'] ?? '' ) )
				&& ( '' === $selected_category || $selected_category === ( $row['category'] ?? '' ) )
				&& ( ! $only_unanswered || ! empty( $row['unanswered'] ) );
		} ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Analítica de pesquisa', 'adam-bot' ); ?></h1>
			<p><?php esc_html_e( 'Tendências anónimas ajudam a encontrar conteúdo em falta e respostas que precisam de ser melhoradas.', 'adam-bot' ); ?></p>
			<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin:18px 0;padding:14px;background:#fff;border:1px solid #c3c4c7;">
				<input type="hidden" name="page" value="adam-bot-search-analytics" />
				<label><?php esc_html_e( 'Período', 'adam-bot' ); ?><br><select name="days"><option value="7" <?php selected( 7, $days ); ?>>7 dias</option><option value="30" <?php selected( 30, $days ); ?>>30 dias</option><option value="90" <?php selected( 90, $days ); ?>>90 dias</option><option value="180" <?php selected( 180, $days ); ?>>180 dias</option></select></label>
				<label><?php esc_html_e( 'Categoria', 'adam-bot' ); ?><br><select name="category"><option value=""><?php esc_html_e( 'Todas', 'adam-bot' ); ?></option><?php foreach ( $this->analytics->getCategoryUsage() as $key => $count ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $selected_category ); ?>><?php echo esc_html( $key ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Fornecedor', 'adam-bot' ); ?><br><select name="provider"><option value=""><?php esc_html_e( 'Todos', 'adam-bot' ); ?></option><?php foreach ( $this->analytics->getProviderUsage() as $key => $count ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $selected_provider ); ?>><?php echo esc_html( $key ); ?></option><?php endforeach; ?></select></label>
				<label><input type="checkbox" name="unanswered" value="1" <?php checked( $only_unanswered ); ?> /> <?php esc_html_e( 'Apenas sem resposta', 'adam-bot' ); ?></label>
				<button class="button"><?php esc_html_e( 'Aplicar filtros', 'adam-bot' ); ?></button>
			</form>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:900px;margin:20px 0;">
				<?php $this->metricCard( __( 'Confiança média', 'adam-bot' ), $this->analytics->getAverageConfidence(), '%' ); ?>
				<?php $this->metricCard( __( 'Tempo médio de resposta', 'adam-bot' ), $this->analytics->getAverageResponseTime(), ' ms' ); ?>
				<?php $this->metricCard( __( 'Pesquisas com baixa confiança', 'adam-bot' ), (int) $data['low_confidence'] ); ?>
				<?php $this->metricCard( __( 'Perguntas sem resposta', 'adam-bot' ), (int) $data['no_confidence'] ); ?>
			</div>
			<h2><?php esc_html_e( 'Perguntas por dia', 'adam-bot' ); ?></h2>
			<?php $this->renderTrendChart( $this->analytics->getDailySeries( $days ), 'questions', __( 'Perguntas', 'adam-bot' ) ); ?>
			<h2><?php esc_html_e( 'Tendência do tempo de resposta', 'adam-bot' ); ?></h2>
			<?php $this->renderTrendChart( $this->analytics->getDailySeries( $days ), 'response_time_ms', __( 'Milissegundos', 'adam-bot' ) ); ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;max-width:1100px;">
				<div><h2><?php esc_html_e( 'Categorias populares', 'adam-bot' ); ?></h2><?php $this->renderBarChart( $this->analytics->getCategoryUsage() ); ?></div>
				<div><h2><?php esc_html_e( 'Distribuição da confiança', 'adam-bot' ); ?></h2><?php $this->renderBarChart( $this->analytics->getConfidenceDistribution() ); ?></div>
				<div><h2><?php esc_html_e( 'Palavras-chave mais pesquisadas', 'adam-bot' ); ?></h2><?php $this->renderBarChart( $this->analytics->getKeywordUsage() ); ?></div>
				<div><h2><?php esc_html_e( 'Utilização de fornecedores', 'adam-bot' ); ?></h2><?php $this->renderBarChart( $this->analytics->getProviderUsage() ); ?></div>
			</div>

			<h2><?php esc_html_e( 'Perguntas mais pesquisadas', 'adam-bot' ); ?></h2>
			<?php $this->renderQuestionTable( $this->analytics->getCommonQuestions( 20 ), 'count' ); ?>
			<h2><?php esc_html_e( 'Pesquisas falhadas', 'adam-bot' ); ?></h2>
			<?php $this->renderQuestionTable( $this->analytics->getNoAnswerQuestions( 20 ), 'no_answer_count' ); ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=adam-bot-unanswered' ) ); ?>"><?php esc_html_e( 'Abrir fila de perguntas sem resposta', 'adam-bot' ); ?></a></p>
			<h2><?php esc_html_e( 'Pesquisas com baixa confiança', 'adam-bot' ); ?></h2>
			<?php $this->renderQuestionTable( $this->analytics->getLowConfidenceQuestions( 20 ), 'low_confidence_count' ); ?>
			<h2><?php esc_html_e( 'Conhecimento mais consultado', 'adam-bot' ); ?></h2>
			<?php $this->renderEntryTable( $this->analytics->getMostViewedEntries( 20 ) ); ?>
			<h2><?php esc_html_e( 'Atividade dos fornecedores', 'adam-bot' ); ?></h2>
			<p class="description"><?php echo esc_html( sprintf( __( '%d registos anónimos correspondem aos filtros selecionados. Não são guardadas identidades nem transcrições.', 'adam-bot' ), count( $logs ) ) ); ?></p>
			<?php $this->renderProviderTable( array_slice( $logs, 0, 50 ) ); ?>

			<h2><?php esc_html_e( 'Sugestões de melhoria da pesquisa', 'adam-bot' ); ?></h2>
			<?php $this->renderImprovementSuggestions( $this->search_insights->suggestions( $this->analytics->getCommonQuestions( 100 ) ) ); ?>
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
			<h1><?php esc_html_e( 'Definições do ADAM BOT', 'adam-bot' ); ?></h1>
			<?php settings_errors(); ?>
			<h2><?php esc_html_e( 'Ações rápidas da conversa', 'adam-bot' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::EXPERIENCE_GROUP ); ?>
				<p class="description"><?php esc_html_e( 'Configure os cartões apresentados aos visitantes antes da primeira pergunta.', 'adam-bot' ); ?></p>
				<table class="widefat striped" style="max-width:960px;margin:12px 0 18px;"><thead><tr><th><?php esc_html_e( 'Ícone', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Texto do cartão', 'adam-bot' ); ?></th><th><?php esc_html_e( 'Pergunta', 'adam-bot' ); ?></th></tr></thead><tbody>
				<?php for ( $index = 0; $index < 8; $index++ ) : $action = $experience['quick_actions'][ $index ] ?? array( 'icon' => '', 'label' => '', 'prompt' => '' ); ?>
					<tr><td><input class="small-text" type="text" maxlength="8" name="<?php echo esc_attr( $option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][icon]" value="<?php echo esc_attr( (string) $action['icon'] ); ?>" /></td><td><input class="regular-text" type="text" maxlength="60" name="<?php echo esc_attr( $option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) $action['label'] ); ?>" /></td><td><input class="large-text" type="text" maxlength="240" name="<?php echo esc_attr( $option ); ?>[quick_actions][<?php echo esc_attr( (string) $index ); ?>][prompt]" value="<?php echo esc_attr( (string) $action['prompt'] ); ?>" /></td></tr>
				<?php endfor; ?>
				</tbody></table>
				<?php submit_button( __( 'Guardar ações rápidas', 'adam-bot' ), 'primary', 'submit', false ); ?>
			</form>

			<hr style="margin:32px 0;" />
			<form method="post" action="options.php">
				<?php settings_fields( KnowledgeAdmin::SETTINGS_GROUP ); ?>
				<?php $this->knowledge_admin->render_settings_fields(); ?>
				<?php submit_button( __( 'Guardar definições de conhecimento', 'adam-bot' ) ); ?>
			</form>

			<hr style="margin:32px 0;" />
			<?php $this->site_indexer->renderTools(); ?>

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
			echo '<p class="description">' . esc_html__( 'Ainda não existem dados.', 'adam-bot' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:1000px;"><thead><tr><th>' . esc_html__( 'Pergunta', 'adam-bot' ) . '</th><th>' . esc_html__( 'Pesquisas', 'adam-bot' ) . '</th><th>' . esc_html__( 'Confiança média', 'adam-bot' ) . '</th><th>' . esc_html__( 'Tempo médio de resposta', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$count = max( 1, (int) ( $row['count'] ?? 1 ) );
			echo '<tr><td>' . esc_html( (string) ( $row['question'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row[ $metric ] ?? 0 ) ) . '</td><td>' . esc_html( (string) round( (int) ( $row['confidence_total'] ?? 0 ) / $count ) ) . '%</td><td>' . esc_html( (string) round( (int) ( $row['response_time_total'] ?? 0 ) / $count ) ) . ' ms</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** @param array<int,array<string,mixed>> $rows Rows. */
	private function renderEntryTable( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Ainda não existem entradas consultadas.', 'adam-bot' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:1000px;"><thead><tr><th>' . esc_html__( 'Entrada de conhecimento', 'adam-bot' ) . '</th><th>' . esc_html__( 'Fornecedor', 'adam-bot' ) . '</th><th>' . esc_html__( 'Visualizações', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$title = (string) ( $row['title'] ?? '' );
			$url   = (int) ( $row['entry_id'] ?? 0 ) > 0 ? get_edit_post_link( (int) $row['entry_id'] ) : '';
			echo '<tr><td>' . ( $url ? '<a href="' . esc_url( (string) $url ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td><td>' . esc_html( (string) ( $row['provider'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $row['count'] ?? 0 ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** @param array<int,array<string,mixed>> $rows Rows. */
	private function renderProviderTable( array $rows ): void {
		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Ainda não existem pesquisas de fornecedores.', 'adam-bot' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:1100px;"><thead><tr><th>' . esc_html__( 'Data (UTC)', 'adam-bot' ) . '</th><th>' . esc_html__( 'Intenção', 'adam-bot' ) . '</th><th>' . esc_html__( 'Fornecedor selecionado', 'adam-bot' ) . '</th><th>' . esc_html__( 'Resultados', 'adam-bot' ) . '</th><th>' . esc_html__( 'Confiança', 'adam-bot' ) . '</th><th>' . esc_html__( 'Tempo do fornecedor', 'adam-bot' ) . '</th><th>' . esc_html__( 'Alternativa', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$provider = (string) ( $row['provider'] ?? '' );
			echo '<tr><td>' . esc_html( (string) ( $row['timestamp'] ?? '' ) ) . '</td><td><code>' . esc_html( (string) ( $row['intent'] ?? '' ) ) . '</code></td><td>' . esc_html( '' !== $provider ? $provider : __( 'Sem correspondência', 'adam-bot' ) ) . '</td><td>' . esc_html( (string) ( $row['result_count'] ?? 0 ) ) . '</td><td>' . esc_html( (string) ( $row['confidence'] ?? 0 ) ) . '%</td><td>' . esc_html( (string) ( $row['search_duration_ms'] ?? 0 ) ) . ' ms</td><td>' . esc_html( (string) ( $row['fallback_provider'] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** @param array<string,int> $rows Usage rows. */
	private function renderUsageTable( array $rows, string $heading ): void {
		if ( empty( $rows ) ) { echo '<p class="description">' . esc_html__( 'Ainda não existem dados.', 'adam-bot' ) . '</p>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html( $heading ) . '</th><th>' . esc_html__( 'Utilizações', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $key => $count ) { echo '<tr><td><code>' . esc_html( (string) $key ) . '</code></td><td>' . esc_html( (string) $count ) . '</td></tr>'; }
		echo '</tbody></table>';
	}

	/** @param array<string,int> $rows Values. */
	private function renderBarChart( array $rows ): void {
		$rows = array_slice( $rows, 0, 10, true );
		if ( empty( $rows ) ) { echo '<p class="description">' . esc_html__( 'Ainda não existem dados.', 'adam-bot' ) . '</p>'; return; }
		$maximum = max( 1, max( array_map( 'intval', $rows ) ) );
		echo '<div class="adam-bot-admin-chart" role="list" aria-label="' . esc_attr__( 'Dados do gráfico de barras', 'adam-bot' ) . '">';
		foreach ( $rows as $label => $value ) {
			$width = (int) round( max( 0, (int) $value ) * 100 / $maximum );
			echo '<div role="listitem" style="display:grid;grid-template-columns:minmax(90px,1fr) 3fr 42px;gap:8px;align-items:center;margin:7px 0;"><span>' . esc_html( (string) $label ) . '</span><span aria-hidden="true" style="display:block;height:12px;background:#dcdcde;"><span style="display:block;width:' . esc_attr( (string) $width ) . '%;height:100%;background:#2271b1;"></span></span><strong>' . esc_html( (string) $value ) . '</strong></div>';
		}
		echo '</div>';
	}

	/** @param array<int,array<string,mixed>> $rows Trend rows. */
	private function renderTrendChart( array $rows, string $metric, string $label ): void {
		$values = array();
		foreach ( $rows as $row ) { $values[ (string) ( $row['date'] ?? '' ) ] = (int) ( $row[ $metric ] ?? 0 ); }
		$this->renderBarChart( array_slice( $values, -14, null, true ) );
		echo '<p class="description">' . esc_html( $label ) . ' — ' . esc_html__( 'últimos 14 pontos do período selecionado', 'adam-bot' ) . '</p>';
	}

	/** @param array<int,array<string,mixed>> $groups Suggested groups. */
	private function renderImprovementSuggestions( array $groups ): void {
		if ( empty( $groups ) ) { echo '<p class="description">' . esc_html__( 'Não foram detetados grupos repetidos.', 'adam-bot' ) . '</p>'; return; }
		echo '<div style="display:grid;gap:12px;max-width:900px;">';
		foreach ( $groups as $group ) {
			echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:14px;"><strong>' . esc_html__( 'Considere consolidar estas perguntas', 'adam-bot' ) . '</strong><ul>';
			foreach ( (array) ( $group['questions'] ?? array() ) as $question ) { echo '<li>' . esc_html( (string) $question ) . '</li>'; }
			echo '</ul><small>' . esc_html( (string) ( $group['count'] ?? 0 ) ) . ' ' . esc_html__( 'pesquisas no total', 'adam-bot' ) . '</small></div>';
		}
		echo '</div>';
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Não tem permissão para aceder a esta página.', 'adam-bot' ) );
		}
	}
}
