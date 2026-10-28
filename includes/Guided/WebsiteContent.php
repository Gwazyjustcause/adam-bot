<?php
/** Public website content, read at response time without creating bot records. */
declare(strict_types=1);
namespace AdamBot\Guided;
defined( 'ABSPATH' ) || exit;

final class WebsiteContent {
	/** Refresh generated navigation, preserving explicitly reviewed and custom nodes. */
	public function enrich( $node, array $response ): array {
		$key = (string) get_post_meta( $node->ID, FlowSchema::MIGRATION_KEY_META, true );
		if ( ! preg_match( '/^(root-|group-|dynamic-)/', $key ) || 'reviewed' === get_post_meta( $node->ID, FlowSchema::MIGRATION_STATUS_META, true ) ) return $response;
		$topic = $this->topic( $node );
		$pages = array( 'teams' => 'equipas', 'fields' => 'campos', 'partners' => 'contactos', 'about' => 'quem-somos', 'membership' => 'associa-te' );
		if ( ! isset( $pages[$topic] ) ) return $response;
		if ( ! in_array( $topic, array( 'teams', 'fields', 'partners' ), true ) && 0 !== strpos( $key, 'group-' ) ) return $response;
		$page = $this->page( $topic, $pages[$topic] );
		// Discard legacy consolidated text and guessed URLs together.
		$response['actions'] = array();
		$response['blocks'] = array();
		$response['content'] = '';
		$response['direct_answer'] = '';
		$response['intro'] = '';
		$response['items'] = array();
		if ( $page ) {
			$text = trim( wp_strip_all_tags( strip_shortcodes( (string) $page->post_content ) ) );
			$response['content'] = wp_trim_words( $text, 90, '…' );
			$labels = array( 'teams' => 'Ver todas as equipas', 'fields' => 'Ver todos os campos', 'partners' => 'Falar com a ADAM sobre uma parceria', 'about' => 'Conhecer a ADAM', 'membership' => 'Consultar adesão e quotas' );
			$response['actions'][] = array( 'label' => $labels[$topic], 'primary' => true, 'destination' => array( 'type' => 'page', 'id' => (int) $page->ID, 'url' => esc_url_raw( get_permalink( $page->ID ) ) ) );
		}
		if ( 'partners' === $topic ) $response['label'] = 'Parcerias e colaboração';
		if ( in_array( $topic, array( 'teams', 'fields' ), true ) ) {
			$response['items'] = $this->directory( $topic );
			if ( '' === $response['content'] ) $response['content'] = empty( $response['items'] ) ? 'Consulta o diretório para conhecer a informação pública disponível.' : 'Conhece os perfis publicados na comunidade ADAM e abre cada página para saber mais.';
		} elseif ( '' === $response['content'] ) {
			$response['content'] = 'A informação desta página não está disponível neste momento.';
		}
		return $response;
	}

	private function topic( $node ): string {
		$seen = array();
		while ( is_object( $node ) && ! isset( $seen[$node->ID] ) ) {
			$seen[$node->ID] = true;
			$key = (string) get_post_meta( $node->ID, FlowSchema::MIGRATION_KEY_META, true );
			if ( 0 === strpos( $key, 'root-' ) ) return substr( $key, 5 );
			$node = $node->post_parent > 0 ? get_post( (int) $node->post_parent ) : null;
		}
		return '';
	}

	private function page( string $topic, string $path ) {
		// Stored IDs keep Community directory links valid after slug changes.
		$settings = get_option( 'adam_comunidade_settings', array() );
		$id = is_array( $settings ) && in_array( $topic, array( 'teams', 'fields' ), true ) ? absint( $settings[$topic . '_page_id'] ?? 0 ) : 0;
		$page = $id > 0 ? get_post( $id ) : get_page_by_path( $path, OBJECT, 'page' );
		return is_object( $page ) && 'page' === $page->post_type && 'publish' === $page->post_status && empty( $page->post_password ) ? $page : null;
	}

	/** Dispatch the installed plugin's public REST contract locally, without HTTP. */
	private function directory( string $topic ): array {
		if ( ! function_exists( 'rest_do_request' ) ) return array();
		$request = new \WP_REST_Request( 'GET', '/adam-comunidade/v1/' . ( 'teams' === $topic ? 'equipas' : 'campos' ) );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10 );
		try {
			$result = rest_do_request( $request );
			if ( is_wp_error( $result ) || 200 !== $result->get_status() ) return array();
			$data = $result->get_data();
		} catch ( \Throwable $error ) {
			return array();
		}
		if ( ! is_array( $data ) ) return array();
		$items = array();
		foreach ( array_slice( $data, 0, 10 ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['name'] ) ) continue;
			$url = esc_url_raw( (string) ( $item['url'] ?? '' ) );
			if ( ! $this->local_profile( $url ) ) continue;
			$description = trim( (string) ( $item['description'] ?? '' ) );
			if ( '' === $description ) $description = (string) ( $item['short_description'] ?? '' );
			$location = array_unique( array_filter( array(
				sanitize_text_field( (string) ( $item['municipality'] ?? '' ) ),
				sanitize_text_field( (string) ( $item['district'] ?? '' ) ),
			) ) );
			$recruitment = array( 'recruiting' => 'A recrutar', 'limited' => 'Recrutamento limitado', 'not_recruiting' => 'Sem recrutamento' );
			$details = array();
			if ( ! empty( $location ) ) $details[] = implode( ', ', $location );
			if ( 'teams' === $topic && isset( $item['members'] ) ) $details[] = absint( $item['members'] ) . ' membros';
			if ( 'teams' === $topic && isset( $recruitment[$item['recruitment_status'] ?? ''] ) ) $details[] = $recruitment[$item['recruitment_status']];
			$items[] = array( 'title' => sanitize_text_field( (string) $item['name'] ), 'description' => wp_trim_words( wp_strip_all_tags( $description ), 70, '…' ), 'details' => implode( ' · ', $details ), 'url' => $url, 'button' => 'teams' === $topic ? 'Ver equipa' : 'Ver campo' );
		}
		return $items;
	}

	private function local_profile( string $url ): bool {
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) && strtolower( (string) parse_url( $url, PHP_URL_HOST ) ) === strtolower( (string) parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}
}
