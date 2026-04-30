<?php

function sfmf_enqueue_styles()
{
    wp_enqueue_style(
        'sfmf-style',
        get_template_directory_uri() . '/assets/css/style.css',
        array(),
        wp_get_theme()->get('Version')
    );
}
add_action('wp_enqueue_scripts', 'sfmf_enqueue_styles');
add_editor_style('assets/css/style.css');

function sfmf_enqueue_dashicon()
{
    wp_enqueue_style('dashicons');
}
add_action('wp_enqueue_scripts', 'sfmf_enqueue_dashicon');


add_action('init', 'sfmf_register_pattern_categories');

function sfmf_register_pattern_categories()
{
    register_block_pattern_category('silvervoxfest-2026/custom', array(
        'label'       => __('silvervoxfest 2026', 'silvervoxfest-2026'),
        'description' => __('silvervoxfest 2026', 'silvervoxfest-2026')
    ));
}

// TODO remove cache flush
add_action('init', function () {
    wp_get_theme()->delete_pattern_cache();
});
