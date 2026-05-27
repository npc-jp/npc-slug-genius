<?php
/**
 * Settings page template.
 *
 * Available variables:
 * @var string $provider           Active provider id.
 * @var string $api_key_claude     Claude API key (may be empty).
 * @var string $api_key_openai     OpenAI API key (may be empty).
 * @var string $api_key_gemini     Gemini API key (may be empty).
 * @var array  $post_types         Currently selected post types.
 * @var array  $all_types          All public post type objects.
 * @var bool   $include_existing   Whether to process posts created before activation.
 * @var string $activated_at       Plugin activation timestamp (GMT mysql format).
 * @var string $activated_at_local Plugin activation timestamp formatted in site timezone.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build a masked display string for an existing API key.
 */
$mask = function ( $key ) {
    if ( '' === $key ) {
        return '';
    }
    return str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 );
};

$providers = array(
    'claude' => array(
        'label'         => __( 'Anthropic Claude', 'npc-slug-genius' ),
        'option'        => NPC_Slug_Genius::OPTION_API_KEY_CLAUDE,
        'value'         => $api_key_claude,
        'display'       => $mask( $api_key_claude ),
        'placeholder'   => 'sk-ant-api03-...',
        'console_url'   => 'https://console.anthropic.com/settings/keys',
        'console_label' => 'console.anthropic.com',
        'delete_field'  => 'npc_slug_genius_delete_api_key_claude',
        'label_text'    => __( 'Anthropic API Key', 'npc-slug-genius' ),
    ),
    'openai' => array(
        'label'         => __( 'OpenAI ChatGPT', 'npc-slug-genius' ),
        'option'        => NPC_Slug_Genius::OPTION_API_KEY_OPENAI,
        'value'         => $api_key_openai,
        'display'       => $mask( $api_key_openai ),
        'placeholder'   => 'sk-proj-...',
        'console_url'   => 'https://platform.openai.com/api-keys',
        'console_label' => 'platform.openai.com',
        'delete_field'  => 'npc_slug_genius_delete_api_key_openai',
        'label_text'    => __( 'OpenAI API Key', 'npc-slug-genius' ),
    ),
    'gemini' => array(
        'label'         => __( 'Google Gemini', 'npc-slug-genius' ),
        'option'        => NPC_Slug_Genius::OPTION_API_KEY_GEMINI,
        'value'         => $api_key_gemini,
        'display'       => $mask( $api_key_gemini ),
        'placeholder'   => 'AIza...',
        'console_url'   => 'https://aistudio.google.com/apikey',
        'console_label' => 'aistudio.google.com',
        'delete_field'  => 'npc_slug_genius_delete_api_key_gemini',
        'label_text'    => __( 'Google Gemini API Key', 'npc-slug-genius' ),
    ),
);
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'NPC Slug Genius', 'npc-slug-genius' ); ?></h1>

    <p>
        <?php echo esc_html__( 'Automatically generates SEO-optimized English URL slugs from Japanese post titles using AI.', 'npc-slug-genius' ); ?>
    </p>

    <form method="post" action="options.php">
        <?php settings_fields( 'npc_slug_genius_settings' ); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="npc-slug-genius-provider"><?php echo esc_html__( 'AI Provider', 'npc-slug-genius' ); ?></label>
                    </th>
                    <td>
                        <select id="npc-slug-genius-provider" name="<?php echo esc_attr( NPC_Slug_Genius::OPTION_PROVIDER ); ?>">
                            <?php foreach ( $providers as $pid => $p ) : ?>
                                <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $provider, $pid ); ?>><?php echo esc_html( $p['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php echo esc_html__( 'Choose which AI provider to use for slug generation. Only the API key field for the selected provider is shown below.', 'npc-slug-genius' ); ?>
                        </p>
                    </td>
                </tr>

                <?php foreach ( $providers as $pid => $p ) : ?>
                    <tr class="npc-slug-genius-provider-row" data-provider="<?php echo esc_attr( $pid ); ?>" style="<?php echo $provider === $pid ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="npc-slug-genius-api-key-<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $p['label_text'] ); ?></label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="npc-slug-genius-api-key-<?php echo esc_attr( $pid ); ?>"
                                name="<?php echo esc_attr( $p['option'] ); ?>"
                                class="regular-text"
                                autocomplete="new-password"
                                placeholder="<?php echo '' === $p['value'] ? esc_attr( $p['placeholder'] ) : esc_attr( $p['display'] ); ?>"
                            />
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: link to provider console */
                                    wp_kses(
                                        /* translators: %s: link to provider console */
                                        __( 'Get your API key from %s. Leave this field empty to keep the current saved key unchanged.', 'npc-slug-genius' ),
                                        array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                                    ),
                                    '<a href="' . esc_url( $p['console_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $p['console_label'] ) . '</a>'
                                );
                                ?>
                            </p>
                            <?php if ( '' !== $p['value'] ) : ?>
                                <p style="margin-top: 8px;">
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="<?php echo esc_attr( $p['delete_field'] ); ?>"
                                            value="1"
                                        />
                                        <?php echo esc_html__( 'Delete the saved API key on save', 'npc-slug-genius' ); ?>
                                    </label>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Target Post Types', 'npc-slug-genius' ); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php echo esc_html__( 'Target Post Types', 'npc-slug-genius' ); ?></legend>
                            <?php foreach ( $all_types as $type_obj ) : ?>
                                <?php
                                $type_name  = $type_obj->name;
                                $type_label = isset( $type_obj->labels->singular_name ) ? $type_obj->labels->singular_name : $type_name;
                                $checked    = in_array( $type_name, $post_types, true );
                                ?>
                                <label style="display:inline-block; margin-right:18px; margin-bottom:6px;">
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr( NPC_Slug_Genius::OPTION_POST_TYPES ); ?>[]"
                                        value="<?php echo esc_attr( $type_name ); ?>"
                                        <?php checked( $checked ); ?>
                                    />
                                    <?php echo esc_html( $type_label ); ?>
                                    <code><?php echo esc_html( $type_name ); ?></code>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php echo esc_html__( 'Slug auto-generation will run only on the selected post types.', 'npc-slug-genius' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php echo esc_html__( 'Existing Posts', 'npc-slug-genius' ); ?></th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr( NPC_Slug_Genius::OPTION_INCLUDE_EXISTING ); ?>"
                                value="1"
                                <?php checked( $include_existing ); ?>
                            />
                            <?php echo esc_html__( 'Also process posts created before plugin activation', 'npc-slug-genius' ); ?>
                        </label>
                        <p class="description">
                            <strong style="color:#b32d2e;"><?php echo esc_html__( 'Warning:', 'npc-slug-genius' ); ?></strong>
                            <?php echo esc_html__( 'Enabling this will rewrite existing Japanese slugs to English on the next save. This changes existing URLs and may break inbound links and SEO. Leave this off unless you know what you are doing.', 'npc-slug-genius' ); ?>
                        </p>
                        <?php if ( '' !== $activated_at_local ) : ?>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: plugin activation date/time formatted in site timezone. */
                                    esc_html__( 'Plugin was activated on: %s. Posts created on or after this time will be processed by default.', 'npc-slug-genius' ),
                                    esc_html( $activated_at_local )
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr style="margin: 30px 0;" />

    <h2><?php echo esc_html__( 'How it works', 'npc-slug-genius' ); ?></h2>
    <ol>
        <li><?php echo esc_html__( 'When you save a post with a Japanese title, this plugin sends the title to your chosen AI provider.', 'npc-slug-genius' ); ?></li>
        <li><?php echo esc_html__( 'The AI returns an SEO-optimized English slug, which is set as the post URL.', 'npc-slug-genius' ); ?></li>
        <li><?php echo esc_html__( 'If you manually edit the slug after that, this plugin will respect your choice and not overwrite it on subsequent saves.', 'npc-slug-genius' ); ?></li>
        <li><?php echo esc_html__( 'If the API key is missing or the API call fails, this plugin does nothing - the post saves as normal.', 'npc-slug-genius' ); ?></li>
    </ol>
</div>

<script>
(function () {
    var selectEl = document.getElementById('npc-slug-genius-provider');
    if (!selectEl) {
        return;
    }
    var rows = document.querySelectorAll('.npc-slug-genius-provider-row');
    function applyVisibility() {
        var current = selectEl.value;
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            row.style.display = (row.getAttribute('data-provider') === current) ? '' : 'none';
        }
    }
    selectEl.addEventListener('change', applyVisibility);
    applyVisibility();
})();
</script>
