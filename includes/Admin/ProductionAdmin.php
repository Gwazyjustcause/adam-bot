<?php
/**
 * Production operations screens and notices.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Admin;

use AdamBot\Analytics\Analytics;
use AdamBot\Analytics\ProviderMonitor;
use AdamBot\Core\Maintenance;
use AdamBot\Knowledge\Dynamic\DynamicProviderRegistry;
use AdamBot\Knowledge\EntrySchema;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/** Adds the unanswered queue, provider inspector, and health notifications. */
final class ProductionAdmin {
	/** @var Analytics */ private $analytics;
	/** @var DynamicProviderRegistry */ private $providers;
	/** @var ProviderMonitor */ private $monitor;

	public function __construct( Analytics $analytics, DynamicProviderRegistry $providers, ProviderMonitor $monitor ) {
		$this->analytics = $analytics;
		$this->providers = $providers;
		$this->monitor   = $monitor;
	}

	/** @return void */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ), 35 );
		add_action( 'admin_post_adam_bot_create_from_question', array( $this, 'createFromQuestion' ) );
		add_action( 'admin_notices', array( $this, 'renderNotices' ) );
	}

	/** @return void */
	public function registerMenu(): void {
		add_submenu_page( 'adam-bot', __( 'Perguntas sem resposta', 'adam-bot' ), __( 'Perguntas sem resposta', 'adam-bot' ), 'manage_options', 'adam-bot-unanswered', array( $this, 'renderUnanswered' ) );
		add_submenu_page( 'adam-bot', __( 'Inspetor de fornecedores', 'adam-bot' ), __( 'Fornecedores', 'adam-bot' ), 'manage_options', 'adam-bot-providers', array( $this, 'renderProviders' ) );
	}

	/** @return void */
	public function renderUnanswered(): void {
		$this->authorize();
		$rows = $this->analytics->getNoAnswerQuestions( 100 );
		echo '<div class="wrap"><h1>' . esc_html__( 'Perguntas sem resposta', 'adam-bot' ) . '</h1><p>' . esc_html__( 'Transforme pesquisas recorrentes sem resposta em conhecimento administrável.', 'adam-bot' ) . '</p>';
		if ( empty( $rows ) ) { echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Não existem perguntas pendentes.', 'adam-bot' ) . '</p></div></div>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Pergunta', 'adam-bot' ) . '</th><th>' . esc_html__( 'Vezes perguntada', 'adam-bot' ) . '</th><th>' . esc_html__( 'Última pergunta', 'adam-bot' ) . '</th><th>' . esc_html__( 'Ação', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$question = sanitize_text_field( (string) ( $row['question'] ?? '' ) );
			$url = wp_nonce_url( add_query_arg( array( 'action' => 'adam_bot_create_from_question', 'question' => $question ), admin_url( 'admin-post.php' ) ), 'adam_bot_create_from_question' );
			echo '<tr><td>' . esc_html( $question ) . '</td><td>' . esc_html( (string) ( $row['no_answer_count'] ?? 0 ) ) . '</td><td>' . esc_html( (string) ( $row['last_asked'] ?? '' ) ) . '</td><td><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Criar entrada de conhecimento', 'adam-bot' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** @return void */
	public function createFromQuestion(): void {
		$this->authorize();
		check_admin_referer( 'adam_bot_create_from_question' );
		$question = sanitize_text_field( wp_unslash( (string) ( $_GET['question'] ?? '' ) ) );
		if ( '' === $question ) { wp_safe_redirect( admin_url( 'admin.php?page=adam-bot-unanswered' ) ); exit; }
		$post_id = wp_insert_post( array( 'post_type' => ManualSource::POST_TYPE, 'post_status' => 'draft', 'post_title' => $question, 'post_content' => '' ), true );
		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( (int) $post_id, EntrySchema::QUESTION_META, $question );
			update_post_meta( (int) $post_id, EntrySchema::PRIORITY_META, 50 );
			update_post_meta( (int) $post_id, EntrySchema::SEARCH_WEIGHT_META, 100 );
			wp_safe_redirect( (string) get_edit_post_link( (int) $post_id, 'url' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=adam-bot-unanswered&created=0' ) );
		exit;
	}

	/** @return void */
	public function renderProviders(): void {
		$this->authorize();
		$health = $this->monitor->all();
		$activity = array();
		foreach ( $this->analytics->getProviderLogs( 1000 ) as $log ) {
			$key = sanitize_key( (string) ( $log['provider'] ?? '' ) );
			if ( '' === $key ) { continue; }
			if ( ! isset( $activity[ $key ] ) ) { $activity[ $key ] = array( 'searches' => 0, 'duration' => 0, 'last_update' => '' ); }
			$activity[ $key ]['searches']++;
			$activity[ $key ]['duration'] += max( 0, (int) ( $log['search_duration_ms'] ?? 0 ) );
			if ( '' === $activity[ $key ]['last_update'] ) { $activity[ $key ]['last_update'] = sanitize_text_field( (string) ( $log['timestamp'] ?? '' ) ); }
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Inspetor de fornecedores', 'adam-bot' ) . '</h1><p>' . esc_html__( 'Estado operacional dos fornecedores registados. A inspeção carrega os fornecedores apenas nesta página.', 'adam-bot' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fornecedor', 'adam-bot' ) . '</th><th>' . esc_html__( 'Estado', 'adam-bot' ) . '</th><th>' . esc_html__( 'Prioridade', 'adam-bot' ) . '</th><th>' . esc_html__( 'Itens indexados', 'adam-bot' ) . '</th><th>' . esc_html__( 'Tempo médio', 'adam-bot' ) . '</th><th>' . esc_html__( 'Última atualização', 'adam-bot' ) . '</th><th>' . esc_html__( 'Erros', 'adam-bot' ) . '</th></tr></thead><tbody>';
		foreach ( $this->providers->inspect() as $provider ) {
			$key = (string) $provider['key'];
			$row = isset( $health[ $key ] ) && is_array( $health[ $key ] ) ? $health[ $key ] : array();
			$stats = isset( $activity[ $key ] ) ? $activity[ $key ] : array();
			$searches = max( 1, (int) ( $stats['searches'] ?? 0 ) );
			$average = (int) round( (int) ( $stats['duration'] ?? 0 ) / $searches );
			$updated = (string) ( $provider['updated'] ?: ( $stats['last_update'] ?? '' ) );
			echo '<tr><td><strong>' . esc_html( (string) $provider['label'] ) . '</strong><br><code>' . esc_html( $key ) . '</code></td><td>' . esc_html( $provider['available'] ? __( 'Disponível', 'adam-bot' ) : __( 'Indisponível', 'adam-bot' ) ) . '</td><td>' . esc_html( (string) $provider['priority'] ) . '</td><td>' . esc_html( (string) $provider['indexed'] ) . '</td><td>' . esc_html( (string) $average ) . ' ms</td><td>' . esc_html( $updated ?: '—' ) . '</td><td>' . esc_html( (string) ( $row['errors'] ?? 0 ) ) . ( ! empty( $row['last_error'] ) ? '<br><small>' . esc_html( (string) $row['last_error'] ) . '</small>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** @return void */
	public function renderNotices(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$data = $this->analytics->all();
		if ( (int) $data['no_confidence'] >= 10 ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'O ADAM BOT acumulou várias perguntas sem resposta.', 'adam-bot' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=adam-bot-unanswered' ) ) . '">' . esc_html__( 'Rever fila', 'adam-bot' ) . '</a></p></div>';
		}
		if ( (int) $data['response_count'] >= 20 && $this->analytics->getAverageConfidence() < 50 ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'A confiança média das pesquisas está abaixo de 50%. Reveja palavras-chave e respostas.', 'adam-bot' ) . '</p></div>';
		}
		foreach ( $this->monitor->all() as $row ) {
			if ( ! empty( $row['last_error_at'] ) && strtotime( (string) $row['last_error_at'] ) >= time() - DAY_IN_SECONDS ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Foi detetada uma falha recente num fornecedor do ADAM BOT.', 'adam-bot' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=adam-bot-providers' ) ) . '">' . esc_html__( 'Abrir inspetor', 'adam-bot' ) . '</a></p></div>';
				break;
			}
		}
		$maintenance = get_option( Maintenance::STATUS_KEY, array() );
		if ( is_array( $maintenance ) && 'error' === ( $maintenance['status'] ?? '' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'A última manutenção automática do ADAM BOT falhou.', 'adam-bot' ) . '</p></div>';
		}
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Não tem permissão para aceder a esta página.', 'adam-bot' ) ); }
	}
}
