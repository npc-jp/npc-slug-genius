<?php
/**
 * Anthropic Claude provider adapter.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPC_Slug_Genius_Claude_Adapter implements NPC_Slug_Genius_Provider {

    const API_URL    = 'https://api.anthropic.com/v1/messages';
    const MODEL      = 'claude-haiku-5-5';
    const MAX_TOKENS = 64;

    /** @var string */
    private $api_key;

    public function __construct( $api_key ) {
        $this->api_key = (string) $api_key;
    }

    public function get_id() {
        return 'claude';
    }

    public function get_display_name() {
        return __( 'Anthropic Claude', 'npc-slug-genius' );
    }

    public function has_api_key() {
        return ! empty( $this->api_key );
    }

    public function generate_slug( $title, $context = array() ) {
        if ( ! $this->has_api_key() ) {
            return new WP_Error( 'no_api_key', __( 'Anthropic API key is not configured.', 'npc-slug-genius' ) );
        }

        $title = (string) $title;
        if ( '' === trim( $title ) ) {
            return new WP_Error( 'empty_title', __( 'Title is empty.', 'npc-slug-genius' ) );
        }

        $system_prompt = $this->load_system_prompt();
        if ( '' === $system_prompt ) {
            return new WP_Error( 'no_system_prompt', __( 'System prompt file is missing.', 'npc-slug-genius' ) );
        }

        $body = array(
            // Haiku 5.5 は temperature: 0.3 を HTTP 400 で断った（2026-10-19 実測）ので送らない。思考は切って、出力の上限を返事だけに使う
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'thinking'   => array(
                'type' => 'disabled',
            ),
            'system'     => $system_prompt,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => 'Title: ' . $title,
                ),
            ),
        );

        $response = wp_remote_post(
            self::API_URL,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => '2023-06-01',
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_connection_failed', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $status ) {
            $message = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : ( 'HTTP ' . $status );
            return new WP_Error( 'api_error', $message );
        }

        // 最後まで書き終えた返事だけを使う。出力の上限で途中まで（stop_reason "max_tokens"）の返事はスラッグにしない
        $stop = isset( $decoded['stop_reason'] ) ? (string) $decoded['stop_reason'] : '';
        if ( 'end_turn' !== $stop ) {
            return new WP_Error( 'incomplete_response', __( 'Claude API response did not finish normally.', 'npc-slug-genius' ), array( 'stop_reason' => $stop ) );
        }

        $text = '';
        if ( isset( $decoded['content'][0]['text'] ) ) {
            $text = $decoded['content'][0]['text'];
        }
        if ( '' === trim( $text ) ) {
            return new WP_Error( 'empty_response', __( 'Empty response from Claude API.', 'npc-slug-genius' ) );
        }

        $slug = $this->normalize_slug( $text );
        if ( '' === $slug ) {
            return new WP_Error( 'invalid_slug', __( 'Generated slug is invalid after normalization.', 'npc-slug-genius' ) );
        }

        return $slug;
    }

    private function load_system_prompt() {
        $path = NPC_SLUG_GENIUS_DIR . 'prompts/system-prompt-v1.txt';
        if ( ! file_exists( $path ) ) {
            return '';
        }
        $content = file_get_contents( $path );
        return false === $content ? '' : $content;
    }

    /**
     * Normalize slug: extract first line, lowercase, keep [a-z0-9-] only,
     * collapse consecutive hyphens, trim leading/trailing hyphens, cap at 60 chars.
     */
    private function normalize_slug( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            return '';
        }
        $lines = preg_split( '/\r\n|\r|\n/', $raw );
        $first = is_array( $lines ) && isset( $lines[0] ) ? $lines[0] : $raw;
        $first = preg_replace( '/["\'`]/', '', $first );
        $first = strtolower( $first );
        $first = preg_replace( '/[^a-z0-9-]+/', '-', $first );
        $first = preg_replace( '/-+/', '-', $first );
        $first = trim( $first, '-' );
        if ( strlen( $first ) > 60 ) {
            $first = substr( $first, 0, 60 );
            $first = trim( $first, '-' );
        }
        return $first;
    }
}
