<?php
/**
 * Standalone visual fixture for the ADAM BOT frontend.
 *
 * Run with: php -S 127.0.0.1:8765 -t .
 * Then open: http://127.0.0.1:8765/tests/browser-fixture.php
 *
 * @package AdamBot
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

/**
 * Minimal fixture implementation of esc_attr_e().
 *
 * @param string $text Text to escape.
 * @return void
 */
function esc_attr_e( string $text ): void {
	echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Minimal fixture implementation of esc_html_e().
 *
 * @param string $text Text to escape.
 * @return void
 */
function esc_html_e( string $text ): void {
	echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Minimal fixture implementation of esc_html().
 *
 * @param string $text Text to escape.
 * @return string
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

$suggestions = array(
	'O que é a ADAM?',
	'Como me posso tornar sócio?',
	'Próximos eventos',
	'Quanto custa ser sócio?',
	'Como renovar a quota?',
	'Legislação do Airsoft',
);
?>
<!doctype html>
<html lang="pt-PT">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<title>ADAM BOT browser fixture</title>
	<link rel="stylesheet" href="../assets/css/widget.css">
	<style>
		body {
			background: linear-gradient(145deg, #eff3ed, #dfe8dc);
			color: #17231c;
			font-family: system-ui, sans-serif;
			margin: 0;
			min-height: 100vh;
			padding: 3rem;
		}

		main {
			max-width: 56rem;
		}
	</style>
	<script>
		window.AdamBotConfig = {
			fakeResponse: {
				message: "Obrigado pela sua pergunta.\n\nNesta primeira versão ainda não estou ligado ao motor de IA.\n\nEm breve poderei responder automaticamente.",
				delay: 1000
			},
			strings: {
				userLabel: 'A sua mensagem',
				assistantLabel: 'Resposta do ADAM BOT',
				typingLabel: 'O ADAM BOT está a escrever'
			}
		};
	</script>
</head>
<body>
	<main>
		<h1>Página pública ADAM</h1>
		<p>Fixture local usada apenas para validar o widget isolado de um tema WordPress.</p>
	</main>
	<?php require dirname( __DIR__ ) . '/templates/chat-widget.php'; ?>
	<script src="../assets/js/widget.js"></script>
</body>
</html>
