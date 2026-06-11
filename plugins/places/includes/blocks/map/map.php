<?php

defined('ABSPATH') || exit;

class SFMF_Map
{
    public function __construct()
    {
        add_action("init", [$this, 'register']);
        add_action('wp_head', function () { ?>
            <script>
                window.placesMarkerData = {
                    ajax_url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
                    nonce: "<?php echo esc_js(wp_create_nonce('update_place_nonce')); ?>"
                };
            </script>
        <?php });
    }

    public function register()
    {
        $places_map_dir = plugin_dir_path(__FILE__);
        $places_map_url = plugin_dir_url(__FILE__);
        wp_register_style(
            'map-mapbox-style',
            'https://api.mapbox.com/mapbox-gl-js/v3.20.0/mapbox-gl.css',
            [],
            false
        );

        wp_register_style(
            'map-style',
            $places_map_url . 'assets/style.css',
            ['map-mapbox-style'],
            filemtime($places_map_dir . 'assets/style.css')
        );

        wp_register_script(
            'map-editor-script',
            $places_map_url . 'assets/editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor' ],
            filemtime($places_map_dir . 'assets/editor.js'),
            true
        );

        wp_register_script(
            'map-mapbox-script',
            'https://api.mapbox.com/mapbox-gl-js/v3.20.0/mapbox-gl.js',
            [],
            false,
            true
        );

        wp_register_script_module(
            '@places/map',
            $places_map_url . 'assets/map.js',
            [],
            filemtime($places_map_dir . 'assets/map.js')
        );

        wp_register_script_module(
            '@places/marker',
            $places_map_url . 'assets/marker.js',
            [],
            filemtime($places_map_dir . 'assets/marker.js')
        );

        wp_register_script_module(
            '@places/filter',
            $places_map_url . 'assets/filter.js',
            [],
            filemtime($places_map_dir . 'assets/filter.js')
        );

        wp_register_script_module(
            '@places/view',
            $places_map_url . 'assets/view.js',
            ['@places/map', '@places/marker', '@places/filter'],
            filemtime($places_map_dir . 'assets/view.js')
        );

        add_filter('render_block_places/map', function ($content) {
            wp_enqueue_script('map-mapbox-script');
            wp_enqueue_style('dashicons');
            return $content;
        });

        add_action('enqueue_block_editor_assets', function () {
            wp_dequeue_script_module('@places/view');
        });

        register_block_type($places_map_dir);
    }
}
