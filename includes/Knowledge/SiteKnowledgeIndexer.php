<?php
/**
 * One-time, administrator-controlled website knowledge indexing.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Knowledge;

use AdamBot\Knowledge\Sources\FAQSource;
use AdamBot\Knowledge\Sources\ManualSource;

defined( 'ABSPATH' ) || exit;

/** Converts public WordPress content into editable Knowledge and FAQ posts. */
final class SiteKnowledgeIndexer {
	public const INITIAL_HOOK = 'adam_bot_initial_site_index';
	public const TRANSLATION_HOOK = 'adam_bot_site_index_translation_batch';
	public const STATUS_OPTION = 'adam_bot_site_index_status';
	public const QUEUE_OPTION = 'adam_bot_site_index_translation_queue';
	private const NONCE_ACTION = 'adam_bot_rebuild_site_knowledge';

	/** @var LanguageDetector */
	private $language_detector;

	public function __construct( ?LanguageDetector $language_detector = null ) {
		$this->language_detector = $language_detector ?: new LanguageDetector();
	}

	public function register_hooks(): void {
		add_action( self::INITIAL_HOOK, array( $this, 'runInitialIndex' ) );
		add_action( self::TRANSLATION_HOOK, array( $this, 'processTranslationQueue' ) );
		add_action( 'admin_post_adam_bot_rebuild_site_knowledge', array( $this, 'handleRebuild' ) );
	}

	/** Schedules the first import once; subsequent imports require an administrator. */
	public function maybeScheduleInitial(): void {
		$status = get_option( self::STATUS_OPTION, false );
		if ( is_array( $status ) && 'translating' === (string) ( $status['state'] ?? '' ) ) {
			$this->scheduleTranslation();
			return;
		}
		if ( false === $status ) {
			update_option( self::STATUS_OPTION, array( 'state' => 'scheduled', 'scheduled_at' => gmdate( 'c' ) ), false );
		}
		if ( ( false === $status || ( is_array( $status ) && 'scheduled' === (string) ( $status['state'] ?? '' ) ) ) && function_exists( 'wp_schedule_single_event' ) && ( ! function_exists( 'wp_next_scheduled' ) || ! wp_next_scheduled( self::INITIAL_HOOK ) ) ) {
			wp_schedule_single_event( time() + 15, self::INITIAL_HOOK );
		}
	}

	public function runInitialIndex(): void {
		$this->rebuild( false );
	}

	public function handleRebuild(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Não tem permissão para reconstruir a base de conhecimento.', 'adam-bot' ) );
		}
		check_admin_referer( self::NONCE_ACTION );
		$this->rebuild( true );
		wp_safe_redirect( add_query_arg( array( 'page' => 'adam-bot-settings', 'adam_bot_indexed' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Scans every published, publicly queryable content item. */
	public function rebuild( bool $explicit = true ): array {
		$started = microtime( true );
		$seen    = array();
		$queue   = array();
		$counts  = array( 'sources' => 0, 'knowledge' => 0, 'faq' => 0, 'english_pending' => 0, 'skipped' => 0 );

		foreach ( $this->publicPosts() as $post ) {
			$sections = $this->extractSections( $post );
			if ( empty( $sections ) ) {
				$counts['skipped']++;
				continue;
			}

			$counts['sources']++;
			$language = $this->language_detector->detect( (string) $post->post_title . ' ' . (string) $post->post_content );
			$needs_generated_english = 'pt' === $language && 0 === $this->englishTranslationId( $post );
			foreach ( $sections as $index => $section ) {
				$payload = $this->payload( $post, $section, $language, (int) $index );
				foreach ( array( ManualSource::POST_TYPE, FAQSource::POST_TYPE ) as $post_type ) {
					$id = $this->upsert( $post_type, $payload, $explicit );
					if ( $id > 0 ) {
						$seen[] = (string) $payload['source_key'] . '|' . $post_type;
						if ( $needs_generated_english ) {
							$seen[] = ( preg_replace( '/:pt$/', ':en', (string) $payload['source_key'] ) ?: (string) $payload['source_key'] . ':en' ) . '|' . $post_type;
						}
						$counts[ ManualSource::POST_TYPE === $post_type ? 'knowledge' : 'faq' ]++;
					}
				}

				if ( $needs_generated_english ) {
					$queue[] = array( 'post_id' => (int) $post->ID, 'section_key' => (string) $section['key'] );
				}
			}
		}

		if ( $explicit ) {
			$this->hideStaleGeneratedEntries( $seen );
		}

		update_option( self::QUEUE_OPTION, array_values( $queue ), false );
		$counts['english_pending'] = count( $queue );
		$status = array_merge(
			$counts,
			array(
				'state'       => empty( $queue ) ? 'complete' : 'translating',
				'indexed_at'  => gmdate( 'c' ),
				'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
			)
		);
		update_option( self::STATUS_OPTION, $status, false );
		if ( ! empty( $queue ) ) {
			$this->scheduleTranslation();
		}
		do_action( 'adam_bot_knowledge_invalidate_cache' );
		return $status;
	}

	/** Translates one section per cron request and persists it exactly once. */
	public function processTranslationQueue(): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		$queue = is_array( $queue ) ? array_values( $queue ) : array();
		$item  = array_shift( $queue );
		if ( ! is_array( $item ) ) {
			$this->completeTranslationStatus();
			return;
		}

		$post     = get_post( absint( $item['post_id'] ?? 0 ) );
		$sections = is_object( $post ) ? $this->extractSections( $post ) : array();
		$section  = null;
		$section_index = 0;
		foreach ( $sections as $candidate_index => $candidate_section ) {
			if ( (string) ( $candidate_section['key'] ?? '' ) === (string) ( $item['section_key'] ?? '' ) ) {
				$section = $candidate_section;
				$section_index = (int) $candidate_index;
				break;
			}
		}
		if ( is_object( $post ) && is_array( $section ) ) {
			$payload = $this->payload( $post, $section, 'pt', $section_index );
			$english = $this->translatePayload( $payload );
			if ( is_array( $english ) ) {
				$this->upsert( ManualSource::POST_TYPE, $english, true );
				$this->upsert( FAQSource::POST_TYPE, $english, true );
			} else {
				$attempts = 1 + absint( $item['attempts'] ?? 0 );
				if ( $attempts < 3 ) {
					$item['attempts'] = $attempts;
					$queue[] = $item;
				}
				$status = get_option( self::STATUS_OPTION, array() );
				$status = is_array( $status ) ? $status : array();
				$status['translation_errors'] = 1 + (int) ( $status['translation_errors'] ?? 0 );
				update_option( self::STATUS_OPTION, $status, false );
			}
		}

		update_option( self::QUEUE_OPTION, $queue, false );
		$status = get_option( self::STATUS_OPTION, array() );
		$status = is_array( $status ) ? $status : array();
		$status['english_pending'] = count( $queue );
		update_option( self::STATUS_OPTION, $status, false );
		do_action( 'adam_bot_knowledge_invalidate_cache' );

		if ( empty( $queue ) ) {
			$this->completeTranslationStatus();
		} else {
			$this->scheduleTranslation();
		}
	}

	/** Renders status and the explicit rebuild control in Settings. */
	public function renderTools(): void {
		$status = get_option( self::STATUS_OPTION, array() );
		$status = is_array( $status ) ? $status : array();
		?>
		<h2><?php esc_html_e( 'Indexação automática do website', 'adam-bot' ); ?></h2>
		<p><?php esc_html_e( 'As páginas e notícias públicas são convertidas em entradas de conhecimento e FAQ editáveis. A indexação inicial ocorre uma vez; alterações manuais são preservadas até escolher reconstruir.', 'adam-bot' ); ?></p>
		<?php if ( ! empty( $status['indexed_at'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Última indexação:', 'adam-bot' ); ?></strong> <?php echo esc_html( (string) $status['indexed_at'] ); ?> — <?php echo esc_html( sprintf( __( '%1$d fontes, %2$d entradas de conhecimento e %3$d FAQ.', 'adam-bot' ), (int) ( $status['sources'] ?? 0 ), (int) ( $status['knowledge'] ?? 0 ), (int) ( $status['faq'] ?? 0 ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $status['english_pending'] ) ) : ?>
			<p class="notice notice-info inline"><?php echo esc_html( sprintf( __( 'A tradução inglesa está a decorrer em segundo plano: %d secções em fila.', 'adam-bot' ), (int) $status['english_pending'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $status['translation_errors'] ) && empty( $status['english_pending'] ) ) : ?>
			<p class="notice notice-warning inline"><?php esc_html_e( 'Algumas traduções inglesas não ficaram concluídas. Verifique a conectividade ou o filtro de tradução e escolha reconstruir para tentar novamente.', 'adam-bot' ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="adam_bot_rebuild_site_knowledge" />
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<?php submit_button( __( 'Reconstruir a base de conhecimento', 'adam-bot' ), 'secondary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'A reconstrução atualiza as entradas geradas a partir do conteúdo público atual. As entradas criadas manualmente não são alteradas. Continuar?', 'adam-bot' ) ) . "');" ) ); ?>
		</form>
		<p class="description"><?php esc_html_e( 'A tradução usa primeiro páginas inglesas existentes. Quando não existem, o texto público é traduzido em segundo plano e guardado como uma entrada inglesa independente e editável.', 'adam-bot' ); ?></p>
		<?php
	}

	/** @return array<int,object> */
	private function publicPosts(): array {
		$post_types = function_exists( 'get_post_types' ) ? get_post_types( array( 'public' => true ), 'names' ) : array( 'page', 'post' );
		$post_types = is_array( $post_types ) ? array_values( array_diff( $post_types, array( 'attachment', ManualSource::POST_TYPE, FAQSource::POST_TYPE ) ) ) : array( 'page', 'post' );
		$post_types = apply_filters( 'adam_bot_site_index_post_types', $post_types );
		$posts      = get_posts( array( 'post_type' => $post_types, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => false, 'lang' => '' ) );
		return array_values(
			array_filter(
				is_array( $posts ) ? $posts : array(),
				static function ( $post ): bool {
					$allowed = is_object( $post ) && empty( $post->post_password );
					return (bool) apply_filters( 'adam_bot_site_index_include_post', $allowed, $post );
				}
			)
		);
	}

	/** @param object $post Source post. @return array<int,array<string,string>> */
	private function extractSections( $post ): array {
		$html = (string) ( $post->post_content ?? '' );
		if ( function_exists( 'apply_filters' ) ) {
			$html = (string) apply_filters( 'the_content', $html );
		}
		$html .= $this->elementorText( (int) $post->ID );
		$html  = (string) apply_filters( 'adam_bot_site_index_source_html', $html, $post );
		$sections = class_exists( '\\DOMDocument' ) ? $this->extractWithDom( $html, (string) $post->post_title ) : array();
		if ( empty( $sections ) ) {
			$sections = $this->extractWithoutDom( $html, (string) $post->post_title );
		}
		$unique = array();
		$heading_counts = array();
		$output = array();
		foreach ( $sections as $section ) {
			$heading = $this->cleanLine( (string) ( $section['heading'] ?? '' ) );
			$answer  = $this->cleanAnswer( (string) ( $section['answer'] ?? '' ) );
			if ( '' === $heading || $this->length( $answer ) < 45 ) {
				continue;
			}
			$key = md5( strtolower( $heading . '|' . $answer ) );
			if ( isset( $unique[ $key ] ) ) {
				continue;
			}
			$unique[ $key ] = true;
			$normalized_heading = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $heading ) : $heading );
			$occurrence = (int) ( $heading_counts[ $normalized_heading ] ?? 0 );
			$heading_counts[ $normalized_heading ] = $occurrence + 1;
			$section_key = substr( hash( 'sha256', $normalized_heading ), 0, 16 ) . ( $occurrence > 0 ? '-' . $occurrence : '' );
			$output[] = array( 'heading' => $heading, 'answer' => $answer, 'key' => $section_key );
			if ( 30 === count( $output ) ) {
				break;
			}
		}
		return $output;
	}

	/** @return array<int,array<string,string>> */
	private function extractWithDom( string $html, string $title ): array {
		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8" ?><main>' . $html . '</main>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xpath = new \DOMXPath( $document );
		foreach ( $xpath->query( '//script|//style|//noscript|//svg|//iframe|//input|//select|//textarea|//button|//label' ) ?: array() as $node ) {
			$node->parentNode && $node->parentNode->removeChild( $node );
		}
		$sections = array();
		$heading  = $title;
		$lines    = array();
		foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//p|//li|//dt|//dd' ) ?: array() as $node ) {
			$text = $this->cleanLine( (string) $node->textContent );
			if ( '' === $text || $this->isNoise( $text ) ) {
				continue;
			}
			if ( in_array( strtolower( (string) $node->nodeName ), array( 'h1', 'h2', 'h3', 'h4' ), true ) ) {
				if ( ! empty( $lines ) ) {
					$sections[] = array( 'heading' => $heading, 'answer' => implode( "\n", $lines ) );
				}
				$heading = $text;
				$lines   = array();
			} elseif ( ! in_array( $text, $lines, true ) ) {
				$lines[] = 'li' === strtolower( (string) $node->nodeName ) ? '- ' . $text : $text;
			}
		}
		if ( ! empty( $lines ) ) {
			$sections[] = array( 'heading' => $heading, 'answer' => implode( "\n", $lines ) );
		}
		return $sections;
	}

	/** @return array<int,array<string,string>> */
	private function extractWithoutDom( string $html, string $title ): array {
		$text = trim( wp_strip_all_tags( strip_shortcodes( $html ) ) );
		return '' === $text ? array() : array( array( 'heading' => $title, 'answer' => $text ) );
	}

	private function elementorText( int $post_id ): string {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		$parts = array();
		$walk = static function ( $value, string $key = '' ) use ( &$walk, &$parts ): void {
			if ( is_array( $value ) ) {
				foreach ( $value as $child_key => $child ) {
					$walk( $child, is_string( $child_key ) ? $child_key : $key );
				}
				return;
			}
			if ( is_string( $value ) && in_array( $key, array( 'title', 'heading', 'editor', 'text', 'description', 'testimonial_content', 'tab_content' ), true ) ) {
				$parts[] = $value;
			}
		};
		$walk( $data );
		return implode( "\n", $parts );
	}

	/** @param object $post @param array<string,string> $section @return array<string,mixed> */
	private function payload( $post, array $section, string $language, int $index ): array {
		$title    = $this->cleanLine( (string) $post->post_title );
		$heading  = $this->cleanLine( $section['heading'] );
		$slug     = (string) ( $post->post_name ?? '' );
		$question = $this->question( $heading, $title, $slug, $language );
		$entry_title = $heading === $title ? $title : $title . ' — ' . $heading;
		$heading_key = sanitize_key( (string) ( $section['key'] ?? '' ) );
		$heading_key = '' !== $heading_key ? $heading_key : substr( hash( 'sha256', strtolower( $heading ) ), 0, 16 );
		$source_key  = (int) $post->ID . ':' . $heading_key . ':' . $language;
		return array(
			'title'       => $entry_title,
			'question'    => $question,
			'answer'      => $section['answer'],
			'keywords'    => $this->keywords( $question . ' ' . $heading . ' ' . $section['answer'] ),
			'category'    => $this->category( $post ),
			'related_page'=> (int) $post->ID,
			'button_text' => $this->buttonText( $slug, $language ),
			'button_url'  => (string) get_permalink( $post ),
			'language'    => $language,
			'source_post' => (int) $post->ID,
			'source_key'  => $source_key,
			'source_hash' => hash( 'sha256', $entry_title . '|' . $question . '|' . $section['answer'] ),
		);
	}

	/** @param array<string,mixed> $payload */
	private function upsert( string $post_type, array $payload, bool $allow_update ): int {
		$source_key = (string) $payload['source_key'];
		$existing   = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 1, 'meta_key' => EntrySchema::SOURCE_KEY_META, 'meta_value' => $source_key, 'no_found_rows' => true ) );
		$current    = is_array( $existing ) && isset( $existing[0] ) ? $existing[0] : null;
		if ( is_object( $current ) && ! $allow_update ) {
			return (int) $current->ID;
		}
		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( (string) $payload['title'] ),
			'post_content' => wp_kses_post( wpautop( (string) $payload['answer'] ) ),
		);
		if ( is_object( $current ) ) {
			$postarr['ID'] = (int) $current->ID;
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) || (int) $post_id <= 0 ) {
			return 0;
		}
		$meta = array(
			EntrySchema::QUESTION_META      => sanitize_text_field( (string) $payload['question'] ),
			EntrySchema::KEYWORDS_META      => EntrySchema::sanitizeTerms( $payload['keywords'] ),
			EntrySchema::SYNONYMS_META      => array(),
			EntrySchema::PRIORITY_META      => 50,
			EntrySchema::SEARCH_WEIGHT_META => 100,
			EntrySchema::VISIBILITY_META    => 'published',
			EntrySchema::ENABLED_META       => '1',
			EntrySchema::RELATED_PAGE_META  => (int) $payload['related_page'],
			EntrySchema::BUTTON_TEXT_META   => sanitize_text_field( (string) $payload['button_text'] ),
			EntrySchema::BUTTON_URL_META    => esc_url_raw( (string) $payload['button_url'] ),
			EntrySchema::LANGUAGE_META      => EntrySchema::sanitizeLanguage( $payload['language'] ),
			EntrySchema::GENERATED_META     => '1',
			EntrySchema::SOURCE_POST_META   => (int) $payload['source_post'],
			EntrySchema::SOURCE_KEY_META    => sanitize_text_field( $source_key ),
			EntrySchema::SOURCE_HASH_META   => sanitize_text_field( (string) $payload['source_hash'] ),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( (int) $post_id, $key, $value );
		}
		wp_set_object_terms( (int) $post_id, array( sanitize_text_field( (string) $payload['category'] ) ), EntrySchema::TAXONOMY );
		return (int) $post_id;
	}

	/** @param array<int,string> $seen */
	private function hideStaleGeneratedEntries( array $seen ): void {
		$posts = get_posts( array( 'post_type' => array( ManualSource::POST_TYPE, FAQSource::POST_TYPE ), 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => -1, 'meta_key' => EntrySchema::GENERATED_META, 'meta_value' => '1', 'no_found_rows' => true ) );
		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			$key = (string) get_post_meta( $post->ID, EntrySchema::SOURCE_KEY_META, true ) . '|' . (string) $post->post_type;
			if ( ! in_array( $key, $seen, true ) ) {
				wp_update_post( array( 'ID' => (int) $post->ID, 'post_status' => 'draft' ) );
				update_post_meta( (int) $post->ID, EntrySchema::VISIBILITY_META, 'hidden' );
				update_post_meta( (int) $post->ID, EntrySchema::ENABLED_META, '0' );
			}
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|null */
	private function translatePayload( array $payload ): ?array {
		$title    = $this->translate( (string) $payload['title'] );
		$question = $this->translate( (string) $payload['question'] );
		$answer   = $this->translate( (string) $payload['answer'] );
		if ( '' === $title || '' === $question || '' === $answer ) {
			return null;
		}
		$payload['title']       = $title;
		$payload['question']    = rtrim( $question, " ?\t\n\r\0\x0B" ) . '?';
		$payload['answer']      = $answer;
		$payload['language']    = 'en';
		$payload['button_text'] = $this->buttonText( (string) parse_url( (string) $payload['button_url'], PHP_URL_PATH ), 'en' );
		$payload['source_key']  = preg_replace( '/:pt$/', ':en', (string) $payload['source_key'] ) ?: (string) $payload['source_key'] . ':en';
		$payload['source_hash'] = hash( 'sha256', $title . '|' . $question . '|' . $answer );
		$payload['keywords']    = $this->keywords( $question . ' ' . $title . ' ' . $answer );
		return $payload;
	}

	private function translate( string $text ): string {
		$filtered = apply_filters( 'adam_bot_site_index_translation', null, $text, 'pt', 'en' );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return trim( $filtered );
		}
		if ( ! apply_filters( 'adam_bot_site_index_remote_translation', true ) || ! function_exists( 'wp_remote_get' ) ) {
			return '';
		}
		$chunks = $this->chunks( $text, 420 );
		$output = array();
		foreach ( $chunks as $chunk ) {
			$url = add_query_arg( array( 'q' => $chunk, 'langpair' => 'pt|en' ), 'https://api.mymemory.translated.net/get' );
			$response = wp_remote_get( $url, array( 'timeout' => 12, 'redirection' => 2, 'user-agent' => 'ADAM-BOT/' . ADAM_BOT_VERSION . '; ' . home_url( '/' ) ) );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return '';
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || (int) ( $data['responseStatus'] ?? 200 ) >= 400 ) {
				return '';
			}
			$translated = is_array( $data ) ? html_entity_decode( (string) ( $data['responseData']['translatedText'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
			if ( '' === trim( $translated ) || false !== stripos( $translated, 'MYMEMORY WARNING' ) ) {
				return '';
			}
			$output[] = trim( wp_strip_all_tags( $translated ) );
		}
		return implode( ' ', $output );
	}

	/** @return array<int,string> */
	private function chunks( string $text, int $maximum ): array {
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
		$chunks = array();
		while ( $this->length( $text ) > $maximum ) {
			$part = $this->substring( $text, 0, $maximum );
			$cut  = max( (int) strrpos( $part, '. ' ), (int) strrpos( $part, ' ' ) );
			$cut  = $cut > 100 ? $cut + 1 : $maximum;
			$chunks[] = trim( $this->substring( $text, 0, $cut ) );
			$text = trim( $this->substring( $text, $cut, $this->length( $text ) ) );
		}
		if ( '' !== $text ) {
			$chunks[] = $text;
		}
		return $chunks;
	}

	/** @param object $post */
	private function englishTranslationId( $post ): int {
		if ( function_exists( 'pll_get_post' ) ) {
			return absint( pll_get_post( (int) $post->ID, 'en' ) );
		}
		if ( function_exists( 'has_filter' ) && has_filter( 'wpml_object_id' ) ) {
			$id = apply_filters( 'wpml_object_id', (int) $post->ID, (string) $post->post_type, false, 'en' );
			return (int) $id === (int) $post->ID ? 0 : absint( $id );
		}
		return 0;
	}

	/** @param object $post */
	private function category( $post ): string {
		$terms = function_exists( 'get_the_terms' ) ? get_the_terms( (int) $post->ID, 'category' ) : array();
		if ( is_array( $terms ) && isset( $terms[0]->name ) ) {
			return sanitize_text_field( (string) $terms[0]->name );
		}
		$slug = strtolower( (string) ( $post->post_name ?? '' ) );
		$map = array(
			'membro|socio|associ|inscri|quota' => 'Adesão', 'evento' => 'Eventos', 'campo' => 'Campos', 'parceir' => 'Parceiros',
			'privacidade|cookie|termos|imagem|video' => 'Legal', 'contact' => 'Contactos', 'noticia' => 'Notícias', 'quem-somos' => 'Associação',
		);
		foreach ( $map as $pattern => $category ) {
			if ( 1 === preg_match( '/(' . $pattern . ')/u', $slug ) ) {
				return $category;
			}
		}
		return sanitize_text_field( (string) $post->post_title );
	}

	private function question( string $heading, string $title, string $slug, string $language ): string {
		if ( false !== strpos( $heading, '?' ) ) {
			return rtrim( $heading, " \t\n\r\0\x0B" );
		}
		$normalized = function_exists( 'remove_accents' ) ? strtolower( remove_accents( $heading . ' ' . $slug ) ) : strtolower( $heading . ' ' . $slug );
		if ( 'en' === $language ) {
			if ( preg_match( '/who we are|about/', $normalized ) ) { return 'What is ADAM?'; }
			if ( false !== strpos( $normalized, 'mission' ) ) { return 'What is ADAM’s mission?'; }
			if ( false !== strpos( $normalized, 'vision' ) ) { return 'What is ADAM’s vision?'; }
			if ( preg_match( '/renew|quota/', $normalized ) ) { return 'How do I renew my membership?'; }
			if ( preg_match( '/contact/', $normalized ) ) { return 'How can I contact ADAM?'; }
			if ( preg_match( '/join|membership|register/', $normalized ) ) { return 'How do I become an ADAM member?'; }
			return 'What should I know about ' . rtrim( $heading ?: $title, '. ' ) . '?';
		}
		if ( false !== strpos( $normalized, 'quem somos' ) ) { return 'O que é a ADAM?'; }
		if ( false !== strpos( $normalized, 'missao' ) ) { return 'Qual é a missão da ADAM?'; }
		if ( false !== strpos( $normalized, 'visao' ) ) { return 'Qual é a visão da ADAM?'; }
		if ( preg_match( '/renov|quota/', $normalized ) ) { return 'Como renovo a minha quota?'; }
		if ( preg_match( '/contact/', $normalized ) ) { return 'Como posso contactar a ADAM?'; }
		if ( preg_match( '/associar|inscri|adesao|junta-te/', $normalized ) ) { return 'Como me torno sócio da ADAM?'; }
		return 'O que devo saber sobre ' . rtrim( $heading ?: $title, '. ' ) . '?';
	}

	private function buttonText( string $slug, string $language ): string {
		$slug = strtolower( $slug );
		if ( preg_match( '/renov|quota/', $slug ) ) { return 'en' === $language ? 'Renew membership' : 'Renovar quota'; }
		if ( preg_match( '/associ|inscri|join/', $slug ) ) { return 'en' === $language ? 'Become a member' : 'Inscrever-me'; }
		if ( false !== strpos( $slug, 'contact' ) ) { return 'en' === $language ? 'Contact ADAM' : 'Contactar a ADAM'; }
		return 'en' === $language ? 'View page' : 'Consultar página';
	}

	/** @return array<int,string> */
	private function keywords( string $text ): array {
		$normalized = function_exists( 'remove_accents' ) ? strtolower( remove_accents( $text ) ) : strtolower( $text );
		$tokens = preg_split( '/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$stop = array( 'para','como','uma','das','dos','que','com','por','mais','esta','este','ser','sao','the','and','for','with','that','this','from','what','about','your','you','adam' );
		$counts = array_count_values( array_filter( is_array( $tokens ) ? $tokens : array(), static function ( string $token ) use ( $stop ): bool { return strlen( $token ) >= 4 && ! in_array( $token, $stop, true ); } ) );
		arsort( $counts );
		return array_slice( array_keys( $counts ), 0, 14 );
	}

	private function cleanLine( string $text ): string {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) ?? $text );
	}

	private function cleanAnswer( string $answer ): string {
		$lines = preg_split( '/\R+/u', $answer, -1, PREG_SPLIT_NO_EMPTY );
		$clean = array();
		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = $this->cleanLine( $line );
			if ( '' !== $line && ! $this->isNoise( $line ) && ! in_array( $line, $clean, true ) ) {
				$clean[] = $line;
			}
		}
		$answer = implode( "\n", $clean );
		return $this->length( $answer ) > 2400 ? rtrim( $this->substring( $answer, 0, 2399 ) ) . '…' : $answer;
	}

	private function isNoise( string $text ): bool {
		return 1 === preg_match( '/^(image|menu|submit|enviar|choose file|no file chosen|delete uploaded file)$/iu', trim( $text ) )
			|| 1 === preg_match( '/(password|palavra-passe|\biban\b|mb\s?way|comprovativo|\[input|\[select|\[button)/iu', $text );
	}

	private function scheduleTranslation(): void {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::TRANSLATION_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::TRANSLATION_HOOK );
		}
	}

	private function completeTranslationStatus(): void {
		$status = get_option( self::STATUS_OPTION, array() );
		$status = is_array( $status ) ? $status : array();
		$status['state'] = empty( $status['translation_errors'] ) ? 'complete' : 'complete_with_errors';
		$status['english_pending'] = 0;
		$status['translation_finished_at'] = gmdate( 'c' );
		update_option( self::STATUS_OPTION, $status, false );
	}

	private function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	private function substring( string $value, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, $start, $length ) : substr( $value, $start, $length );
	}
}
