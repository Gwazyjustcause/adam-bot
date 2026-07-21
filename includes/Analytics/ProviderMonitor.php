<?php
/**
 * Operational health records for dynamic providers.
 *
 * @package AdamBot
 */

declare(strict_types=1);

namespace AdamBot\Analytics;

defined( 'ABSPATH' ) || exit;

/** Stores bounded operational counters without visitor data. */
final class ProviderMonitor {
	public const OPTION_KEY = 'adam_bot_provider_health';

	/** @return void */
	public function register_hooks(): void {
		add_action( 'adam_bot_dynamic_provider_error', array( $this, 'recordError' ), 10, 2 );
	}

	/** @return array<string,array<string,mixed>> */
	public function all(): array {
		$value = get_option( self::OPTION_KEY, array() );
		return is_array( $value ) ? $value : array();
	}

	/** @return void */
	public function recordSearch( string $provider, int $result_count, int $duration_ms ): void {
		$key = sanitize_key( $provider );
		if ( '' === $key ) { return; }
		$data = $this->all();
		$row = isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
		$row['searches'] = (int) ( $row['searches'] ?? 0 ) + 1;
		$row['result_total'] = (int) ( $row['result_total'] ?? 0 ) + max( 0, $result_count );
		$row['duration_total_ms'] = (int) ( $row['duration_total_ms'] ?? 0 ) + max( 0, $duration_ms );
		$row['last_update'] = gmdate( 'c' );
		$data[ $key ] = $row;
		update_option( self::OPTION_KEY, $data, false );
	}

	/** @param \Throwable $exception Provider exception. @return void */
	public function recordError( string $provider, $exception ): void {
		$key = sanitize_key( $provider );
		if ( '' === $key ) { $key = 'unknown'; }
		$data = $this->all();
		$row = isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
		$row['errors'] = (int) ( $row['errors'] ?? 0 ) + 1;
		$row['last_error'] = $exception instanceof \Throwable ? sanitize_text_field( $exception->getMessage() ) : __( 'Erro desconhecido do fornecedor.', 'adam-bot' );
		$row['last_error_at'] = gmdate( 'c' );
		$data[ $key ] = $row;
		update_option( self::OPTION_KEY, $data, false );
	}

	/** @return void */
	public static function activate(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, array(), '', 'no' );
		}
	}
}
