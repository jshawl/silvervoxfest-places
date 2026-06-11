<?php

class SFMF_Place
{
    public function __construct()
    {
        add_action('init', [$this, 'register']);
        add_action('save_post_place', [$this, 'save_meta']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('rest_api_init', [$this, 'register_rest_fields'], 15);
        add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor'], 10, 2);
        add_filter('rest_prepare_place', [$this, 'filter_rest_response'], 10, 3);
        add_action('wp_ajax_update_place', [$this, 'update_place']);
    }

    public function save_meta($post_id)
    {
        if (! wp_verify_nonce($_POST['place_nonce'] ?? '', 'place_meta_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $post_id)) {
            return;
        }
        update_post_meta($post_id, '_place_address', sanitize_text_field($_POST['_place_address'] ?? ''));
        update_post_meta($post_id, '_place_url', sanitize_text_field($_POST['_place_url'] ?? ''));

        $lat = get_post_meta($post_id, '_place_lat', true);
        if ($lat == "") {
            $latlng = $this->geocode_address_nominatim(sanitize_text_field($_POST['_place_address'] ?? ''));
            if ($latlng != false) {
                update_post_meta($post_id, '_place_lat', $latlng['lat']);
                update_post_meta($post_id, '_place_lng', $latlng['lng']);
            }
        }
    }

    public function update_place()
    {
        check_ajax_referer('update_place_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        update_post_meta($post_id, '_place_lat', sanitize_text_field($_POST['lat']));
        update_post_meta($post_id, '_place_lng', sanitize_text_field($_POST['lng']));
        wp_send_json_success('Meta updated');
    }

    public function register_meta_boxes()
    {
        $meta_boxes = [
            "Address" => "address",
            "URL"     => "url",
        ];

        foreach ($meta_boxes as $label => $id) {
            add_meta_box(
                'meta_box_' . $id,
                $label,
                [$this, 'render_place_'.$id.'_meta_box'],
                'place',
                'normal',
                'high'
            );
        }
    }

    public function render_place_address_meta_box($post)
    {
        wp_nonce_field('place_meta_nonce', 'place_nonce');
        $address = get_post_meta($post->ID, '_place_address', true);
        ?>
    <label>Address</label>
    <input type="text" name="_place_address"
           value="<?php echo esc_attr($address); ?>"
           style="width:100%;" />
    <?php
    }

    public function render_place_url_meta_box($post)
    {
        wp_nonce_field('place_meta_nonce', 'place_nonce');
        $url = get_post_meta($post->ID, '_place_url', true);
        ?>
    <label>URL</label>
    <input type="text" name="_place_url"
           value="<?php echo esc_attr($url); ?>"
           style="width:100%;" />
    <?php
    }

    public function register_rest_fields()
    {
        $fields = ['address', 'lat', 'lng', 'url'];
        foreach ($fields as $field) {
            register_rest_field('place', $field, [
                'get_callback' => function ($post) use ($field) {
                    return get_post_meta($post['id'], '_place_' . $field, true);
                },
            ]);
        }

        register_rest_field('place', 'image', [
            'get_callback' => function ($post) {
                $id = get_post_thumbnail_id($post['id']);
                if (! $id) {
                    return null;
                }
                return wp_get_attachment_image_url($id, 'full');
            },
        ]);

        register_rest_field('place', 'type', [
        'get_callback' => function ($post) {
            $terms = get_the_terms($post['id'], 'type');
            if (empty($terms) || is_wp_error($terms)) {
                return "";
            }
            return $terms[0]->name;
        },
    ]);
    }

    public function filter_rest_response($response, $post, $request)
    {
        $allowed = ['id','title', 'address', 'url', 'image', 'type', 'lat', 'lng', 'content'];

        $data = $response->get_data();
        $filtered = array_intersect_key($data, array_flip($allowed));

        $response->set_data($filtered);
        foreach ($response->get_links() as $rel => $links) {
            $response->remove_link($rel);
        }
        return $response;
    }

    public function disable_block_editor($use_block_editor, $post_type)
    {
        if ($post_type === 'place') {
            return false;
        }
        return $use_block_editor;
    }

    public function geocode_address_nominatim($address)
    {
        $url = add_query_arg([
            'q'      => urlencode($address),
            'format' => 'json',
            'limit'  => 1,
        ], 'https://nominatim.openstreetmap.org/search');

        $response = wp_remote_get($url, [
            'headers' => [ 'User-Agent' => 'Places/1.0 (silvervoxfest.com)' ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (! empty($body)) {
            return [ 'lat' => $body[0]['lat'], 'lng' => $body[0]['lon'] ];
        }

        return false;
    }

    public function register()
    {
        $labels = [
            'name'               => 'Places',
            'singular_name'      => 'Place',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Place',
            'edit_item'          => 'Edit Place',
            'new_item'           => 'New Place',
            'view_item'          => 'View Place',
            'search_items'       => 'Search Places',
            'not_found'          => 'No places found',
            'not_found_in_trash' => 'No places found in Trash',
        ];

        $args = [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => true,
            'show_in_rest' => true,
            'supports'     => [
                'title',
                'editor',
                'thumbnail',
            ],
            'menu_icon' => 'dashicons-location-alt',
            'rewrite'   => ['slug' => 'places'],
        ];

        register_taxonomy('type', 'place', [
            'label'             => 'Place Type',
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'rewrite'           => ['slug' => 'type'],
            'show_admin_column' => true
        ]);
        register_post_type('place', $args);
    }
}
