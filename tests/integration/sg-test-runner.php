<?php
/**
 * Slug Genius 結合テスト（WordPress Playground に mu-plugin として載せて使う）
 *
 * /?sg_test=run を開くと全ケースを実行し、結果を JSON で返して終了する。
 * AI の API は pre_http_request で差し替え、外部には一切通信しない。
 * 実行は tests/integration/run.sh から。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---- API 応答の差し替え ------------------------------------------------------

/**
 * 次の API 呼び出しに返す応答を決める。呼ばれた URL と送られた本文は $GLOBALS['sg_t_calls'] に残す。
 */
function sg_t_mock( $status, $body ) {
    $GLOBALS['sg_t_mock']  = array( 'status' => $status, 'body' => $body );
    $GLOBALS['sg_t_calls'] = array();
}

function sg_t_unmock() {
    unset( $GLOBALS['sg_t_mock'] );
    $GLOBALS['sg_t_calls'] = array();
}

add_filter(
    'pre_http_request',
    function ( $pre, $args, $url ) {
        if ( ! isset( $GLOBALS['sg_t_mock'] ) ) {
            return $pre;
        }
        $GLOBALS['sg_t_calls'][] = array(
            'url'  => $url,
            'body' => isset( $args['body'] ) ? json_decode( $args['body'], true ) : null,
        );
        return array(
            'headers'  => array(),
            'body'     => wp_json_encode( $GLOBALS['sg_t_mock']['body'] ),
            'response' => array( 'code' => $GLOBALS['sg_t_mock']['status'], 'message' => 'mock' ),
            'cookies'  => array(),
            'filename' => null,
        );
    },
    10,
    3
);

// 各社の応答の形（途中で切れたかどうかだけを変えられるようにする）
function sg_t_gemini_body( $text, $finish ) {
    $candidate = array( 'content' => array( 'parts' => array( array( 'text' => $text ) ), 'role' => 'model' ) );
    if ( null !== $finish ) {
        $candidate['finishReason'] = $finish;
    }
    return array( 'candidates' => array( $candidate ) );
}

function sg_t_openai_body( $text, $finish ) {
    return array(
        'choices' => array(
            array(
                'index'         => 0,
                'message'       => array( 'role' => 'assistant', 'content' => $text ),
                'finish_reason' => $finish,
            ),
        ),
    );
}

function sg_t_claude_body( $text, $stop ) {
    return array(
        'content'     => array( array( 'type' => 'text', 'text' => $text ) ),
        'stop_reason' => $stop,
    );
}

// ---- 準備と後片付け ------------------------------------------------------------

function sg_t_use_provider( $provider ) {
    update_option( NPC_Slug_Genius::OPTION_PROVIDER, $provider );
    update_option( 'npc_slug_genius_api_key_' . $provider, 'test-key-not-real' );
}

function sg_t_cleanup() {
    sg_t_unmock();
    foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
        wp_delete_post( $id, true );
    }
}

/**
 * 投稿画面の保存と同じく save_post を通して投稿を作る。
 */
function sg_t_insert_post( $title, $extra = array() ) {
    return wp_insert_post(
        array_merge(
            array(
                'post_title'   => $title,
                'post_content' => 'body',
                'post_status'  => 'publish',
                'post_type'    => 'post',
            ),
            $extra
        )
    );
}

function sg_t_meta( $post_id, $key ) {
    return get_post_meta( $post_id, $key, true );
}

// ---- テストケース ----------------------------------------------------------------
// 各関数は array( bool 合否, string 詳細 ) を返す。

$GLOBALS['sg_t_cases'] = array();

function sg_t_case( $name, $fn ) {
    $GLOBALS['sg_t_cases'][ $name ] = $fn;
}

// 不具合1: 途中で切れた返事をスラッグに使わない（3社）
sg_t_case(
    'gemini_max_tokens_is_not_used_as_slug',
    function () {
        sg_t_mock( 200, sg_t_gemini_body( 'about-u', 'MAX_TOKENS' ) );
        $r = ( new NPC_Slug_Genius_Gemini_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_code() : 'returned ' . $r );
    }
);
sg_t_case(
    'gemini_missing_finish_reason_is_not_used_as_slug',
    function () {
        sg_t_mock( 200, sg_t_gemini_body( 'about-u', null ) );
        $r = ( new NPC_Slug_Genius_Gemini_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_code() : 'returned ' . $r );
    }
);
sg_t_case(
    'gemini_stop_returns_slug',
    function () {
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
        $r = ( new NPC_Slug_Genius_Gemini_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( 'about-us' === $r, is_wp_error( $r ) ? $r->get_error_code() : $r );
    }
);
sg_t_case(
    'openai_length_is_not_used_as_slug',
    function () {
        sg_t_mock( 200, sg_t_openai_body( 'about-u', 'length' ) );
        $r = ( new NPC_Slug_Genius_OpenAI_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_code() : 'returned ' . $r );
    }
);
sg_t_case(
    'openai_stop_returns_slug',
    function () {
        sg_t_mock( 200, sg_t_openai_body( 'about-us', 'stop' ) );
        $r = ( new NPC_Slug_Genius_OpenAI_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( 'about-us' === $r, is_wp_error( $r ) ? $r->get_error_code() : $r );
    }
);
sg_t_case(
    'claude_max_tokens_is_not_used_as_slug',
    function () {
        sg_t_mock( 200, sg_t_claude_body( 'about-u', 'max_tokens' ) );
        $r = ( new NPC_Slug_Genius_Claude_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_code() : 'returned ' . $r );
    }
);
sg_t_case(
    'claude_end_turn_returns_slug',
    function () {
        sg_t_mock( 200, sg_t_claude_body( 'about-us', 'end_turn' ) );
        $r = ( new NPC_Slug_Genius_Claude_Adapter( 'k' ) )->generate_slug( '会社案内' );
        return array( 'about-us' === $r, is_wp_error( $r ) ? $r->get_error_code() : $r );
    }
);

// 不具合1 を保存の流れで: 途中で切れた返事ではスラッグも記録も変わらない
sg_t_case(
    'truncated_reply_leaves_post_slug_unchanged',
    function () {
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'about-u', 'MAX_TOKENS' ) );
        $id    = sg_t_insert_post( '会社案内' );
        $post  = get_post( $id );
        $auto  = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $ok    = 'about-u' !== $post->post_name && '' === $auto && 1 === count( $GLOBALS['sg_t_calls'] );
        return array( $ok, "post_name={$post->post_name} auto={$auto} calls=" . count( $GLOBALS['sg_t_calls'] ) );
    }
);

// 不具合2: スラッグの更新に失敗したら自動生成の記録を書かない → 次の保存で手動扱いにしない
sg_t_case(
    'failed_slug_update_does_not_lock_post_as_manual',
    function () {
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'company-profile', 'STOP' ) );
        // スラッグを書き込む更新だけを失敗させる（第2引数 true の wp_update_post は WP_Error 'empty_content' を返す）
        $fail = function ( $maybe_empty, $postarr ) {
            return ( isset( $postarr['post_name'] ) && 'company-profile' === $postarr['post_name'] ) ? true : $maybe_empty;
        };
        add_filter( 'wp_insert_post_empty_content', $fail, 10, 2 );
        $id = sg_t_insert_post( '会社概要' );
        remove_filter( 'wp_insert_post_empty_content', $fail, 10 );

        $auto_after_fail = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );

        // 次の保存（更新は成功する）
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = '' === $auto_after_fail && '1' !== (string) $manual && 'company-profile' === $post->post_name;
        return array( $ok, "auto_after_fail={$auto_after_fail} manual={$manual} post_name_after_resave={$post->post_name}" );
    }
);

// 不具合2: 同じスラッグがすでにあって WordPress が -2 を付けたら、実際に保存された方を記録する
sg_t_case(
    'records_slug_actually_saved_when_wordpress_adds_suffix',
    function () {
        sg_t_unmock();
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact' ) );

        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id   = sg_t_insert_post( 'お問い合わせ' );
        $post = get_post( $id );
        $auto = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );

        // 次の保存でロックされないこと
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && 'contact-2' === $auto && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 不具合2（下書き経由）: WordPress は下書き・レビュー待ちでは "-2" を付けず、公開した時に付ける。
// その "-2" を手で書き換えたとみなさない（2026-10-19 チェック1巡目で Codex と product-qa が再現）
function sg_t_draft_then_publish( $status_before, $publish ) {
    sg_t_unmock();
    sg_t_use_provider( 'gemini' );
    sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
    $id     = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => $status_before ) );
    // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
    // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
    sg_t_unmock();
    sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
    sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
    $before = get_post( $id )->post_name . '/' . sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );

    $publish( $id );
    $post   = get_post( $id );
    $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
    $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );

    // 公開のあとにもう一度保存してもロックされない
    $publish( $id );
    $manual2 = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );

    $ok = 'contact/contact' === $before && 'contact-2' === $post->post_name && 'contact-2' === $auto && '1' !== (string) $manual && '1' !== (string) $manual2;
    return array( $ok, "before={$before} post_name={$post->post_name} auto={$auto} manual={$manual} manual_after_resave={$manual2}" );
}

function sg_t_publish_with_wp_update_post( $id ) {
    wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
}

// ブロックエディタと同じ経路（REST の PUT）
function sg_t_publish_with_rest( $id ) {
    $req = new WP_REST_Request( 'PUT', '/wp/v2/posts/' . $id );
    $req->set_body_params( array( 'status' => 'publish' ) );
    rest_do_request( $req );
}

sg_t_case(
    'draft_then_publish_suffix_is_not_treated_as_manual',
    function () {
        return sg_t_draft_then_publish( 'draft', 'sg_t_publish_with_wp_update_post' );
    }
);
sg_t_case(
    'draft_then_publish_via_rest_suffix_is_not_treated_as_manual',
    function () {
        return sg_t_draft_then_publish( 'draft', 'sg_t_publish_with_rest' );
    }
);
sg_t_case(
    'pending_then_publish_suffix_is_not_treated_as_manual',
    function () {
        return sg_t_draft_then_publish( 'pending', 'sg_t_publish_with_wp_update_post' );
    }
);

// 対照: 自動生成のあと、手で "contact-2" と打った場合はロックする（WordPress の付番とは区別する）
sg_t_case(
    'manual_edit_to_numbered_slug_still_locks',
    function () {
        sg_t_unmock();
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact' ) );

        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact-us', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ' );
        wp_update_post( array( 'ID' => $id, 'post_name' => 'contact-2' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 対照: 他の投稿が使っているスラッグを手で打ち、WordPress が番号を付けた場合はロックする
// （付番前のスラッグが自動生成の記録と違う＝手の書き換え。2026-10-19 product-qa 2巡目の変異 M4 を捕まえる）
sg_t_case(
    'manual_edit_to_used_slug_locks_even_when_numbered',
    function () {
        sg_t_unmock();
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact' ) );

        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact-us', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ' );
        wp_update_post( array( 'ID' => $id, 'post_name' => 'contact' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 下書きで、WordPress が付けるのとまったく同じ番号（contact-2）を手で打った場合はロックしない。
// 判定は「公開した扱い」で WordPress と同じ計算をするので、手で打ったか見分けられない（readme に書いてある動き）
sg_t_case(
    'wp_number_typed_by_hand_in_draft_is_not_locked',
    function () {
        require_once ABSPATH . 'wp-admin/includes/post.php';
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        $sample = get_sample_permalink( $id );
        wp_update_post( array( 'ID' => $id, 'post_name' => 'contact-2' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' !== (string) $manual;
        return array( $ok, "sample={$sample[1]} post_name={$post->post_name} manual={$manual}" );
    }
);

// クイック編集と同じ流れ: 公開扱いで番号を先に計算してから、親を変えて公開。移動先でもぶつかって番号が2回付く
// （wp_ajax_inline_save の手順。2026-10-19 Codex 2巡目の指摘2）。保存の時点で WordPress と同じ計算をしても
// "contact-2-2" は出てこないので、手の書き換えと見分けられずロックする。readme に書いてある既知の動き
sg_t_case(
    'quick_edit_publish_with_parent_change_numbered_twice_locks',
    function () {
        sg_t_unmock();
        $parent_a = sg_t_insert_post( 'Parent A', array( 'post_type' => 'page' ) );
        $parent_b = sg_t_insert_post( 'Parent B', array( 'post_type' => 'page' ) );
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_type' => 'page', 'post_parent' => $parent_a, 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_unmock();
        sg_t_insert_post( 'Contact A', array( 'post_type' => 'page', 'post_parent' => $parent_a, 'post_name' => 'contact', 'post_status' => 'draft' ) );
        sg_t_insert_post( 'Contact B', array( 'post_type' => 'page', 'post_parent' => $parent_b, 'post_name' => 'contact-2', 'post_status' => 'draft' ) );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );

        // wp_ajax_inline_save: 下書きは公開扱いにして、いまの親で番号を先に付ける
        $pre = wp_unique_post_slug( 'contact', $id, 'publish', 'page', $parent_a );
        wp_update_post( array( 'ID' => $id, 'post_name' => $pre, 'post_parent' => $parent_b, 'post_status' => 'publish' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2-2' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "pre={$pre} post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 編集権限の無い処理（WP-CLI の --user なし・外部の自動化）で公開して番号が付いても、
// 別のリクエストで人が保存したときにロックしない（2026-10-19 product-qa 2巡目の任意2）
sg_t_case(
    'publish_without_edit_permission_then_human_save_is_not_manual',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );

        wp_set_current_user( 0 );
        wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        wp_set_current_user( 1 );

        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && 'contact-2' === $auto && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 対照: 下書きで、WordPress が付けない番号（contact-3）を手で打った場合はロックする
sg_t_case(
    'other_number_typed_by_hand_in_draft_locks',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        wp_update_post( array( 'ID' => $id, 'post_name' => 'contact-3' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-3' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 対照: 名前がぶつかる下書きで、手で別のスラッグを打ちながら公開した場合はロックする
// （2026-10-19 product-qa 4巡目の変異 Q5 を捕まえる）
sg_t_case(
    'own_slug_typed_while_publishing_draft_locks',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id  = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        $req = new WP_REST_Request( 'PUT', '/wp/v2/posts/' . $id );
        $req->set_body_params( array( 'status' => 'publish', 'slug' => 'my-own-slug' ) );
        rest_do_request( $req );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'my-own-slug' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// クイック編集で、名前がぶつかる下書きを下書きのまま保存する（wp_ajax_inline_save は下書きでも
// 公開した扱いで先に番号を付ける）。WordPress の付番なのでロックしない（2026-10-19 Codex・product-qa 4巡目）
sg_t_case(
    'quick_edit_save_draft_numbered_is_not_manual',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id  = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        $pre = wp_unique_post_slug( 'contact', $id, 'publish', 'post', 0 );
        wp_update_post( array( 'ID' => $id, 'post_name' => $pre, 'post_title' => 'お問い合わせ（改）', 'post_status' => 'draft' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && 'draft' === $post->post_status && 'contact-2' === $auto && '1' !== (string) $manual;
        return array( $ok, "pre={$pre} post_name={$post->post_name} status={$post->post_status} auto={$auto} manual={$manual}" );
    }
);

// クイック編集で、下書きの固定ページを名前のぶつからない親へ移して公開する。番号は移す前の親で付くので、
// 保存の時点の親で計算しても出てこず、ロックする（readme に書いてある例外）
sg_t_case(
    'quick_edit_publish_moving_to_parent_without_conflict_locks',
    function () {
        sg_t_unmock();
        $parent_a = sg_t_insert_post( 'Parent A', array( 'post_type' => 'page' ) );
        $parent_b = sg_t_insert_post( 'Parent B', array( 'post_type' => 'page' ) );
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id  = sg_t_insert_post( 'お問い合わせ', array( 'post_type' => 'page', 'post_parent' => $parent_a, 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_unmock();
        sg_t_insert_post( 'Contact A', array( 'post_type' => 'page', 'post_parent' => $parent_a, 'post_name' => 'contact', 'post_status' => 'draft' ) );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $pre = wp_unique_post_slug( 'contact', $id, 'publish', 'page', $parent_a );
        wp_update_post( array( 'ID' => $id, 'post_name' => $pre, 'post_parent' => $parent_b, 'post_status' => 'publish' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "pre={$pre} post_name={$post->post_name} manual={$manual}" );
    }
);

// 権限の無い処理で公開されて番号が付き、記録を合わせる前にぶつかっていた投稿が消えると、
// 次の保存でロックする（readme に書いてある例外。2026-10-19 Codex 4巡目の任意2）
sg_t_case(
    'other_post_deleted_before_record_sync_locks',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        $other = sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        wp_set_current_user( 0 );
        wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        wp_set_current_user( 1 );
        wp_delete_post( $other, true );
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 他のプラグインが保存のときにスラッグを書き換えても、実際に保存されたスラッグを記録し、次の保存でロックしない
sg_t_case(
    'records_slug_changed_by_other_plugin_on_save',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'company-profile', 'STOP' ) );
        $other_plugin = function ( $data ) {
            if ( 'company-profile' === $data['post_name'] ) {
                $data['post_name'] = 'company-profile-jp';
            }
            return $data;
        };
        add_filter( 'wp_insert_post_data', $other_plugin );
        $id = sg_t_insert_post( '会社概要' );
        remove_filter( 'wp_insert_post_data', $other_plugin );
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'company-profile-jp' === $post->post_name && 'company-profile-jp' === $auto && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 予約語（feed）は、他の投稿が無くても WordPress が番号を付ける（1.1.3 からは下書きの時点で付ける）。ロックしない
sg_t_case(
    'reserved_word_numbered_in_draft_stays_unlocked_after_publish',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'feed', 'STOP' ) );
        $id     = sg_t_insert_post( 'フィード', array( 'post_status' => 'draft' ) );
        $before = get_post( $id )->post_name;
        wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'feed-2' === $before && 'feed-2' === $post->post_name && 'feed-2' === $auto && '1' !== (string) $manual;
        return array( $ok, "before={$before} post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 下書きでスラッグを空にして保存しても、ロックせずに作り直す（readme「different, non-empty slug locks it」）
sg_t_case(
    'emptied_slug_in_draft_is_regenerated_not_locked',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact-us', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        wp_update_post( array( 'ID' => $id, 'post_name' => '' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-us' === $post->post_name && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 1.1.3: 下書きに書くスラッグが、公開済みの投稿・固定ページのスラッグを奪わない。
// WordPress は下書きには番号を付けないので、そのまま書くと公開済みのほうが次の保存で "-2" に変わり、
// 固定ページは旧い URL が切れる（1.1.0 から・2026-10-19 product-qa が再現）
function sg_t_draft_does_not_take_published_slug( $post_type ) {
    sg_t_unmock();
    $published = sg_t_insert_post( 'About us', array( 'post_type' => $post_type, 'post_name' => 'about-us' ) );

    sg_t_use_provider( 'gemini' );
    sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
    $id    = sg_t_insert_post( '会社案内', array( 'post_type' => $post_type, 'post_status' => 'draft' ) );
    $draft = get_post( $id )->post_name;

    // 公開済みのほうを保存し直す
    wp_update_post( array( 'ID' => $published, 'post_content' => 'edited' ) );
    $published_after = get_post( $published )->post_name;

    // 下書きを公開してもロックされない
    wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
    $post   = get_post( $id );
    $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
    $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
    $ok     = 'about-us-2' === $draft && 'about-us' === $published_after && 'about-us-2' === $post->post_name && 'about-us-2' === $auto && '1' !== (string) $manual;
    return array( $ok, "draft={$draft} published_after_resave={$published_after} post_name={$post->post_name} auto={$auto} manual={$manual}" );
}

sg_t_case(
    'draft_page_does_not_take_published_page_slug',
    function () {
        return sg_t_draft_does_not_take_published_slug( 'page' );
    }
);
sg_t_case(
    'draft_post_does_not_take_published_post_slug',
    function () {
        return sg_t_draft_does_not_take_published_slug( 'post' );
    }
);

// 固定ページは親ごとに重複を見る: 同じ親の下に公開済みの about-us があれば、子ページの下書きは about-us-2 にする
sg_t_case(
    'draft_child_page_does_not_take_sibling_slug',
    function () {
        sg_t_unmock();
        $parent  = sg_t_insert_post( 'Parent', array( 'post_type' => 'page' ) );
        $sibling = sg_t_insert_post( 'About us', array( 'post_type' => 'page', 'post_parent' => $parent, 'post_name' => 'about-us' ) );

        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
        $id    = sg_t_insert_post( '会社案内', array( 'post_type' => 'page', 'post_parent' => $parent, 'post_status' => 'draft' ) );
        $draft = get_post( $id )->post_name;
        wp_update_post( array( 'ID' => $sibling, 'post_content' => 'edited' ) );
        $sibling_after = get_post( $sibling )->post_name;
        $ok = 'about-us-2' === $draft && 'about-us' === $sibling_after;
        return array( $ok, "draft={$draft} sibling_after_resave={$sibling_after}" );
    }
);

// 別の親（最上位）にだけ about-us がある子ページの下書きは、番号を付けずに about-us にする
sg_t_case(
    'draft_child_page_keeps_slug_used_only_under_other_parent',
    function () {
        sg_t_unmock();
        $parent = sg_t_insert_post( 'Parent', array( 'post_type' => 'page' ) );
        sg_t_insert_post( 'About us', array( 'post_type' => 'page', 'post_name' => 'about-us' ) );

        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
        $id    = sg_t_insert_post( '会社案内', array( 'post_type' => 'page', 'post_parent' => $parent, 'post_status' => 'draft' ) );
        $draft = get_post( $id )->post_name;
        return array( 'about-us' === $draft, "draft={$draft}" );
    }
);

// 固定ページは親ごとに重複を見る: 同じ親の下でぶつかって番号が付いてもロックしない
sg_t_case(
    'page_numbered_under_same_parent_is_not_manual',
    function () {
        sg_t_unmock();
        $parent = sg_t_insert_post( 'Parent', array( 'post_type' => 'page' ) );

        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
        $id = sg_t_insert_post( '会社案内', array( 'post_type' => 'page', 'post_parent' => $parent, 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、同じ親の下の下書きとして作る
        sg_t_unmock();
        sg_t_insert_post( 'About A', array( 'post_type' => 'page', 'post_parent' => $parent, 'post_name' => 'about-us', 'post_status' => 'draft' ) );
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
        wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'about-us-2' === $post->post_name && 'about-us-2' === $auto && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 番号付きのスラッグを記録に合わせたあとなら、ぶつかっていた投稿が消えてもロックしない
sg_t_case(
    'numbered_slug_stays_unlocked_after_other_post_is_deleted',
    function () {
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        $other = sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        wp_delete_post( $other, true );
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited again' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 保存の途中で他のプラグインがパーマリンク表示の計算を呼んでも、WordPress の付番をロックしない
// （2026-10-19 Codex 3巡目の指摘1・product-qa 3巡目の任意1）
sg_t_case(
    'sample_permalink_called_during_publish_is_not_manual',
    function () {
        require_once ABSPATH . 'wp-admin/includes/post.php';
        sg_t_unmock();
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'contact', 'STOP' ) );
        $id = sg_t_insert_post( 'お問い合わせ', array( 'post_status' => 'draft' ) );
        // 名前がぶつかる相手は、プラグインが下書きにスラッグを書いたあとで、下書きとして作る
        // （WordPress は新しい投稿のスラッグを決めるとき下書きのスラッグも避けるが、下書きどうしは番号を付けない）
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact', 'post_status' => 'draft' ) );
        $third_party = function ( $post_id ) {
            get_sample_permalink( $post_id );
        };
        add_action( 'post_updated', $third_party, 10, 1 );
        wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
        remove_action( 'post_updated', $third_party, 10 );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 1.1.1 のときに公開で番号が付き、記録だけ古いまま残った投稿も、次の保存でロックしない
sg_t_case(
    'record_left_by_1_1_1_is_not_treated_as_manual',
    function () {
        sg_t_unmock();
        sg_t_insert_post( 'Existing contact page', array( 'post_name' => 'contact' ) );
        $id = sg_t_insert_post( 'Old contact page', array( 'post_name' => 'contact' ) );
        // 1.1.1 が残した状態: 保存されたのは contact-2、記録は contact
        update_post_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED, 'contact' );

        sg_t_use_provider( 'gemini' );
        wp_update_post( array( 'ID' => $id, 'post_content' => 'edited' ) );
        $post   = get_post( $id );
        $auto   = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'contact-2' === $post->post_name && 'contact-2' === $auto && '1' !== (string) $manual;
        return array( $ok, "post_name={$post->post_name} auto={$auto} manual={$manual}" );
    }
);

// 正常に終わっていない返事（拒否・安全上の停止）もスラッグにしない
sg_t_case(
    'rejected_replies_are_not_used_as_slug',
    function () {
        $got = array();
        sg_t_mock( 200, sg_t_claude_body( 'about-us', 'refusal' ) );
        $got['claude_refusal'] = ( new NPC_Slug_Genius_Claude_Adapter( 'k' ) )->generate_slug( '会社案内' );
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'SAFETY' ) );
        $got['gemini_safety'] = ( new NPC_Slug_Genius_Gemini_Adapter( 'k' ) )->generate_slug( '会社案内' );
        sg_t_mock( 200, sg_t_openai_body( 'about-us', 'content_filter' ) );
        $got['openai_content_filter'] = ( new NPC_Slug_Genius_OpenAI_Adapter( 'k' ) )->generate_slug( '会社案内' );
        $ok     = true;
        $detail = array();
        foreach ( $got as $k => $r ) {
            $ok       = $ok && is_wp_error( $r );
            $detail[] = $k . '=' . ( is_wp_error( $r ) ? $r->get_error_code() : $r );
        }
        return array( $ok, implode( ' ', $detail ) );
    }
);

// 手で書き換えたスラッグは今までどおりロックする（直したことで壊していないかの見張り）
sg_t_case(
    'manual_slug_edit_still_locks',
    function () {
        sg_t_use_provider( 'gemini' );
        sg_t_mock( 200, sg_t_gemini_body( 'pricing', 'STOP' ) );
        $id = sg_t_insert_post( '料金プラン' );
        wp_update_post( array( 'ID' => $id, 'post_name' => 'my-own-slug' ) );
        $post   = get_post( $id );
        $manual = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_MANUAL );
        $ok     = 'my-own-slug' === $post->post_name && '1' === (string) $manual;
        return array( $ok, "post_name={$post->post_name} manual={$manual}" );
    }
);

// 1.1.2 のモデル差し替え: 送る中身が、各社の新しいモデルで通る形になっているか
// （2026-10-19 実測: 旧い形のままだと3社とも HTTP 400）
sg_t_case(
    'gemini_request_uses_3_5_flash_lite_with_minimal_thinking',
    function () {
        sg_t_mock( 200, sg_t_gemini_body( 'about-us', 'STOP' ) );
        ( new NPC_Slug_Genius_Gemini_Adapter( 'k' ) )->generate_slug( '会社案内' );
        $call = $GLOBALS['sg_t_calls'][0];
        $tc   = isset( $call['body']['generationConfig']['thinkingConfig'] ) ? $call['body']['generationConfig']['thinkingConfig'] : null;
        $ok   = false !== strpos( $call['url'], '/models/gemini-3.5-flash-lite:generateContent' )
            && array( 'thinkingLevel' => 'minimal' ) === $tc;
        return array( $ok, 'url=' . $call['url'] . ' thinkingConfig=' . wp_json_encode( $tc ) );
    }
);
sg_t_case(
    'openai_request_uses_gpt_6_luna_without_reasoning',
    function () {
        sg_t_mock( 200, sg_t_openai_body( 'about-us', 'stop' ) );
        ( new NPC_Slug_Genius_OpenAI_Adapter( 'k' ) )->generate_slug( '会社案内' );
        $b  = $GLOBALS['sg_t_calls'][0]['body'];
        $ok = 'gpt-6-luna' === $b['model']
            && ! array_key_exists( 'max_tokens', $b )
            && isset( $b['max_completion_tokens'] )
            && isset( $b['reasoning_effort'] ) && 'none' === $b['reasoning_effort'];
        unset( $b['messages'] );
        return array( $ok, wp_json_encode( $b ) );
    }
);
sg_t_case(
    'claude_request_uses_haiku_5_5_without_temperature',
    function () {
        sg_t_mock( 200, sg_t_claude_body( 'about-us', 'end_turn' ) );
        ( new NPC_Slug_Genius_Claude_Adapter( 'k' ) )->generate_slug( '会社案内' );
        $b  = $GLOBALS['sg_t_calls'][0]['body'];
        $ok = 'claude-haiku-5-5' === $b['model']
            && ! array_key_exists( 'temperature', $b )
            && isset( $b['thinking'] ) && array( 'type' => 'disabled' ) === $b['thinking'];
        unset( $b['messages'], $b['system'] );
        return array( $ok, wp_json_encode( $b ) );
    }
);

// ---- 本物の API で保存の流れを通す（run.sh に SG_TEST_LIVE=1 を付けたときだけ） -------------
// キーは run.sh が一時フォルダに作る sg_t_live_keys() から受け取る（リポジトリには置かない）

add_action(
    'init',
    function () {
        if ( ! isset( $_GET['sg_test'] ) || 'live' !== $_GET['sg_test'] || ! function_exists( 'sg_t_live_keys' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }
        wp_set_current_user( 1 );
        $results = array();
        $keys    = sg_t_live_keys();
        foreach ( array( 'claude', 'openai', 'gemini' ) as $provider ) {
            if ( empty( $keys[ $provider ] ) ) {
                $results[] = array( 'name' => 'live_' . $provider, 'pass' => false, 'detail' => 'API キーが無いので未実施' );
                continue;
            }
            $key = $keys[ $provider ];
            sg_t_cleanup();
            update_option( NPC_Slug_Genius::OPTION_PROVIDER, $provider );
            update_option( 'npc_slug_genius_api_key_' . $provider, $key );
            $id   = sg_t_insert_post( 'WordPressで問い合わせフォームを作る方法' );
            $post = get_post( $id );
            $auto = sg_t_meta( $id, NPC_Slug_Genius::META_KEY_AUTO_GENERATED );
            $ok   = (bool) preg_match( '/^[a-z0-9-]+$/', $post->post_name ) && $auto === $post->post_name;
            $results[] = array( 'name' => 'live_' . $provider, 'pass' => $ok, 'detail' => "post_name={$post->post_name} auto={$auto}" );
            delete_option( 'npc_slug_genius_api_key_' . $provider );
        }
        sg_t_cleanup();
        wp_send_json(
            array(
                'wp_version'     => get_bloginfo( 'version' ),
                'php_version'    => PHP_VERSION,
                'plugin_version' => NPC_SLUG_GENIUS_VERSION,
                'results'        => $results,
                'log_markers'    => null, // 本物の API の回はエラー記録を見ない（見るのは sg_test=run）
                'log_other'      => array(),
            )
        );
    },
    99
);

// ---- 実行 ----------------------------------------------------------------------

add_action(
    'init',
    function () {
        if ( ! isset( $_GET['sg_test'] ) || 'run' !== $_GET['sg_test'] ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }
        wp_set_current_user( 1 );

        // エラー記録が本当に書かれることを、前後の印で確かめる
        $log = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log ) ) {
            unlink( $log );
        }
        trigger_error( 'sg-test-marker-before', E_USER_NOTICE ); // phpcs:ignore

        $results = array();
        foreach ( $GLOBALS['sg_t_cases'] as $name => $fn ) {
            sg_t_cleanup();
            try {
                list( $pass, $detail ) = $fn();
            } catch ( Throwable $e ) {
                $pass   = false;
                $detail = get_class( $e ) . ': ' . $e->getMessage();
            }
            $results[] = array( 'name' => $name, 'pass' => (bool) $pass, 'detail' => $detail );
        }
        sg_t_cleanup();

        trigger_error( 'sg-test-marker-after', E_USER_NOTICE ); // phpcs:ignore
        $lines = file_exists( $log ) ? array_values( array_filter( array_map( 'trim', file( $log ) ) ) ) : array();
        $other = array_values( array_filter( $lines, function ( $l ) {
            return false === strpos( $l, 'sg-test-marker-' );
        } ) );

        wp_send_json(
            array(
                'wp_version'     => get_bloginfo( 'version' ),
                'php_version'    => PHP_VERSION,
                'plugin_version' => NPC_SLUG_GENIUS_VERSION,
                'results'        => $results,
                'case_count'     => count( $GLOBALS['sg_t_cases'] ),
                'log_markers'    => count( $lines ) - count( $other ),
                'log_other'      => $other,
            )
        );
    },
    99
);
