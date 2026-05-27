<?php
/**
 * OpenAI ChatGPT provider adapter.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPC_Slug_Genius_OpenAI_Adapter implements NPC_Slug_Genius_Provider {

    const API_URL    = 'https://api.openai.com/v1/chat/completions';
    const MODEL      = 'gpt-4o-mini';
    const MAX_TOKENS = 64;
    const TEMP       = 0.3;

    /** @var string */
    private $api_key;

    public function __construct( $api_key ) {
        $this->api_key = (string) $api_key;
    }

    public function get_id() {
        return 'openai';
    }

    public function get_display_name() {
        return __( 'OpenAI ChatGPT', 'npc-slug-genius' );
    }

    public function has_api_key() {
        return ! empty( $this->api_key );
    }

    public function generate_slug( $title, $context = array() ) {
        if ( ! $this->has_api_key() ) {
            return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'npc-slug-genius' ) );
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
            'model'       => self::MODEL,
            'max_tokens'  => self::MAX_TOKENS,
            'temperature' => self::TEMP,
            'messages'    => array(
                array(
                    'role'    => 'system',
                    'content' => $system_prompt,
                ),
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
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ),
                'body'    => wp_json_encode( $body ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_connection_failed', $response->get_error_message() );
        }

        $status  = wp_remote_retrieve_response_code( $response );
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $status ) {
            $message = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : ( 'HTTP ' . $status );
            return new WP_Error( 'api_error', $message );
        }

        $text = '';
        if ( isset( $decoded['choices'][0]['message']['content'] ) ) {
            $text = $decoded['choices'][0]['message']['content'];
        }
        if ( '' === trim( $text ) ) {
            return new WP_Error( 'empty_response', __( 'Empty response from OpenAI API.', 'npc-slug-genius' ) );
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
