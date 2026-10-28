<?php
/** Website-backed starter answers for the public guided assistant. */

declare(strict_types=1);

namespace AdamBot\Guided;

use AdamBot\Knowledge\EntrySchema;

defined( 'ABSPATH' ) || exit;

/** Adds missing answers without rewriting an administrator's guided content. */
final class WebsiteAnswers {

	public function register_hooks(): void {
		// Run after the existing root migration, including on upgraded installations.
		add_action( 'admin_init', array( $this, 'populate' ), 32 );
	}

	public function populate(): void {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'wp_insert_post' ) ) return;

		foreach ( $this->answers() as $key => $answer ) {
			// Include trash: deliberately removed answers must not reappear.
			$existing = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'posts_per_page' => 1, 'meta_key' => FlowSchema::MIGRATION_KEY_META, 'meta_value' => 'website-' . $key, 'no_found_rows' => true ) );
			if ( ! empty( $existing ) ) continue;

			$roots = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_key' => FlowSchema::MIGRATION_KEY_META, 'meta_value' => 'root-' . $answer['root'], 'no_found_rows' => true ) );
			if ( empty( $roots ) ) continue;
			$parent = (int) $roots[0]->ID;
			$duplicate = get_posts( array( 'post_type' => FlowSchema::POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'post_parent' => $parent, 'title' => $answer['question'], 'posts_per_page' => 1, 'no_found_rows' => true ) );
			if ( ! empty( $duplicate ) ) continue;

			$page = get_page_by_path( $answer['page'], OBJECT, 'page' );
			if ( ! is_object( $page ) || 'publish' !== $page->post_status || ! empty( $page->post_password ) ) continue;

			$id = wp_insert_post( array(
				'post_type' => FlowSchema::POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => $parent,
				'post_title' => $answer['question'],
				'post_content' => '',
				'menu_order' => 10,
				'meta_input' => array(
					FlowSchema::MIGRATION_KEY_META => 'website-' . $key,
					FlowSchema::NODE_TYPE_META => 'answer',
					FlowSchema::LABEL_META => $answer['question'],
					FlowSchema::LANGUAGE_META => 'pt',
					FlowSchema::DIRECT_ANSWER_META => $answer['answer'],
					FlowSchema::MIGRATION_STATUS_META => 'reviewed',
					FlowSchema::MIGRATION_NOTES_META => 'Resposta baseada no website, consultado em 28/10/2026. Fonte: ' . get_permalink( $page->ID ),
					EntrySchema::SOURCE_POST_META => (int) $page->ID,
					FlowSchema::ACTIONS_META => FlowSchema::sanitizeActions( array(
						array( 'label' => $answer['button'], 'type' => 'page', 'page_id' => (int) $page->ID ),
					) ),
				),
			), true );
			// Failed inserts are retried on the next administrator visit.
			if ( is_wp_error( $id ) || (int) $id <= 0 ) continue;
		}
	}

	/** Editorial summaries of public pages, verified on 2026-10-28. */
	private function answers(): array {
		return array(
			'join' => array( 'root' => 'membership', 'page' => 'associa-te', 'question' => 'Como faço a inscrição?', 'answer' => 'Preenche a inscrição online, envia os documentos pedidos e segue as instruções de pagamento. A ADAM revê o pedido e envia a confirmação por email.', 'button' => 'Ver como me inscrever' ),
			'prices' => array( 'root' => 'membership', 'page' => 'associa-te', 'question' => 'Quanto custa ser sócio?', 'answer' => 'Existem as opções ADAM e ADAM + ANA. Consulta os valores anuais e o que cada modalidade inclui na página de adesão.', 'button' => 'Comparar modalidades e quotas' ),
			'independent' => array( 'root' => 'membership', 'page' => 'associa-te', 'question' => 'Preciso de pertencer a uma equipa?', 'answer' => 'Não. Podes aderir como jogador independente ou integrar uma equipa ou clube.', 'button' => 'Conhecer as condições de adesão' ),
			'other-apd' => array( 'root' => 'membership', 'page' => 'associa-te', 'question' => 'Já pertenço a outra APD. Posso aderir?', 'answer' => 'Sim. Podes manter a tua APD e aderir à ADAM. Indica a associação a que pertences no pedido.', 'button' => 'Consultar a adesão ADAM' ),
			'renew' => array( 'root' => 'membership', 'page' => 'associa-te', 'question' => 'Quando tenho de renovar a quota?', 'answer' => 'A quota é anual. Consulta o estado, a validade e a renovação disponível na tua Área de Sócio.', 'button' => 'Ver informação sobre a quota' ),
			'benefits' => array( 'root' => 'membership', 'page' => 'associa-te', 'question' => 'Quais são as vantagens de ser sócio?', 'answer' => 'Podes participar na vida da associação, apoiar a comunidade e aceder aos benefícios disponibilizados e à Área de Sócio com cartão digital.', 'button' => 'Conhecer as vantagens' ),
			'about' => array( 'root' => 'about', 'page' => 'quem-somos', 'question' => 'O que faz a ADAM?', 'answer' => 'A ADAM é uma associação sem fins lucrativos que aproxima praticantes, equipas e organizações e apoia o desenvolvimento responsável do airsoft.', 'button' => 'Conhecer a associação' ),
			'apd' => array( 'root' => 'about', 'page' => 'associa-te', 'question' => 'A ADAM é uma APD?', 'answer' => 'Não. A ADAM colabora com a ANA para quem procura esse enquadramento através da opção ADAM + ANA.', 'button' => 'Perceber a opção ADAM + ANA' ),
			'teams' => array( 'root' => 'teams', 'page' => 'equipas', 'question' => 'Como encontro uma equipa?', 'answer' => 'Explora o diretório e filtra por localização, estilo de jogo e recrutamento. Abre uma equipa para consultar os seus detalhes.', 'button' => 'Explorar equipas' ),
			'fields' => array( 'root' => 'fields', 'page' => 'campos', 'question' => 'Onde posso encontrar campos?', 'answer' => 'O diretório de campos reúne localização, instalações e regras para te ajudar a preparar a próxima experiência.', 'button' => 'Explorar campos' ),
			'community' => array( 'root' => 'airsoft', 'page' => 'comunidade', 'question' => 'Como me aproximo da comunidade?', 'answer' => 'A página da comunidade ajuda-te a descobrir equipas e campos e a conhecer os projetos locais de airsoft.', 'button' => 'Explorar a comunidade' ),
			'events' => array( 'root' => 'events', 'page' => 'quem-somos', 'question' => 'A ADAM organiza e divulga atividades?', 'answer' => 'A ADAM promove e divulga eventos e iniciativas e colabora com equipas, campos e organizadores.', 'button' => 'Conhecer as atividades da ADAM' ),
			'partners' => array( 'root' => 'partners', 'page' => 'contactos', 'question' => 'Como proponho uma parceria?', 'answer' => 'Envia a tua proposta através do formulário de contactos e explica como gostarias de colaborar com a ADAM.', 'button' => 'Propor uma colaboração' ),
			'contact' => array( 'root' => 'help', 'page' => 'contactos', 'question' => 'Como contacto a ADAM?', 'answer' => 'Usa o formulário de contactos para dúvidas sobre a associação, eventos, inscrições ou parcerias.', 'button' => 'Abrir contactos' ),
		);
	}
}
