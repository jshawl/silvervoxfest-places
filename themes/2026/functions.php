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

register_block_style('core/paragraph', array(
    'name'  => 'uppercase',
    'label' => 'Uppercase',
));
