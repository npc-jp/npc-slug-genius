<?php
/**
 * Settings page template.
 *
 * Available variables:
 * @var string $provider         Active provider id.
 * @var string $api_key          Claude API key (may be empty).
 * @var array  $post_types       Currently selected post types.
 * @var array  $all_types        All public post type objects.
 * @var bool   $include_existing Whether to process posts created before activation.
 * @var string $activated_at     Plugin activation timestamp (GMT mysql format).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_key_display = '';
if ( '' !== $api_key ) {
    $api_key_display = str_repeat( '*', max( 0, strlen( $api_key ) - 4 ) ) . substr( $api_key, -4 );
}
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
                            <option value="claude" <?php selected( $provider, 'claude' ); ?>><?php echo esc_html__( 'Anthropic Claude', 'npc-slug-genius' ); ?></option>
                            <option value="openai" disabled><?php echo esc_html__( 'OpenAI ChatGPT (coming in v0.2)', 'npc-slug-genius' ); ?></option>
                            <option value="gemini" disabled><?php echo esc_html__( 'Google Gemini (coming in v0.3)', 'npc-slug-genius' ); ?></option>
                        </select>
                        <p class="description">
                            <?php echo esc_html__( 'Choose which AI provider to use for slug generation. More providers coming soon.', 'npc-slug-genius' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="npc-slug-genius-api-key-claude"><?php echo esc_html__( 'Anthropic API Key', 'npc-slug-genius' ); ?></label>
                    </th>
                    <td>
                        <input
                            type="password"
                            id="npc-slug-genius-api-key-claude"
                            name="<?php echo esc_attr( NPC_Slug_Genius::OPTION_API_KEY_CLAUDE ); ?>"
                            class="regular-text"
                            autocomplete="new-password"
                            placeholder="<?php echo '' === $api_key ? 'sk-ant-api03-...' : esc_attr( $api_key_display ); ?>"
                        />
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: link to Anthropic Console */
                                wp_kses(
                                    /* translators: %s: link to Anthropic Console */
                                    __( 'Get your API key from %s. Leave this field empty to keep the current saved key unchanged.', 'npc-slug-genius' ),
                                    array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
                                ),
                                '<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">console.anthropic.com</a>'
                            );
                            ?>
                        </p>
                        <?php if ( '' !== $api_key ) : ?>
                            <p style="margin-top: 8px;">
                                <label>
                                    <input
                                        type="checkbox"
                                        name="npc_slug_genius_delete_api_key_claude"
                                        value="1"
                                    />
                                    <?php echo esc_html__( 'Delete the saved API key on save', 'npc-slug-genius' ); ?>
                                </label>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

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
                        <?php if ( '' !== $activated_at ) : ?>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: plugin activation date/time. */
                                    esc_html__( 'Plugin was activated on: %s (UTC). Posts created on or after this time will be processed by default.', 'npc-slug-genius' ),
                                    esc_html( $activated_at )
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
