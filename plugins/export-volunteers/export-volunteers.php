<?php

/**
 * Plugin Name: Export Volunteers
 * Description: Allows editors to export volunteer form submissions
 * Version: 1.0
 * Author: Jesse Shawl
 * Author URI: https://jesse.sh/
 * License: GPLv2 or later
 */

if (! class_exists('GFAPI') && file_exists(plugin_dir_path(__FILE__) . 'mock-gravity-forms.php')) {
    require_once plugin_dir_path(__FILE__) . 'mock-gravity-forms.php';
}

class SFMF_Export
{
    public const FORM_ID = 2;
    public function init()
    {
        add_action('wp_ajax_export_volunteers', [$this,'export_volunteers_handler']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('sfmf_cron_hook', [$this, 'send_csv_attachment']);
    }

    public function activate()
    {
        if (! wp_next_scheduled('sfmf_cron_hook')) {
            $next_send = strtotime('friday 21:00:00 UTC');
            wp_schedule_event($next_send, 'weekly', 'sfmf_cron_hook');
        }
    }

    public function deactivate()
    {
        $timestamp = wp_next_scheduled('sfmf_cron_hook');
        wp_unschedule_event($timestamp, 'sfmf_cron_hook');
    }

    public function add_settings_page()
    {
        add_management_page(
            'Export Volunteers',
            'Export Volunteers',
            'edit_others_posts',
            'gf-export',
            [$this, 'settings_page_html']
        );
    }

    public function register_settings()
    {
        register_setting('sfmf_export_volunteers_settings', 'sfmf_send_csv_attachment_to', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_email']
        ]);
    }

    public function sanitize_email($input)
    {
        return sanitize_text_field($input);
    }

    public function get_entries()
    {
        $search_criteria = [
            "status" => "active"
        ];
        $sorting = null;
        $paging = array( 'offset' => 0, 'page_size' => 1000 );
        $total_count = 0;
        return GFAPI::get_entries(self::FORM_ID, $search_criteria, $sorting, $paging, $total_count);
    }

    public function settings_page_html()
    {
        $entries = $this->get_entries();
        $total_count = count($entries);
        $last_submission = $entries[0]["date_created"];
        $time_ago = human_time_diff(strtotime($last_submission), current_time('timestamp'));
        $url = admin_url('admin-ajax.php?action=export_volunteers');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="notice">There are <?php echo $total_count; ?> total volunteers!
            <small><em>last submission <?php echo $time_ago; ?> ago</em></small></p>
            <a class="button button-primary" href="<?php echo esc_url($url); ?>">Download CSV</a>
            <br /><br />
            <hr />
            <h2>Weekly Email Settings</h2>
            <form method="post" action="options.php">
            <?php settings_fields('sfmf_export_volunteers_settings'); ?>
            <?php do_settings_sections('sfmf_send_csv_attachment_to'); ?>
            <?php $value = get_option('sfmf_send_csv_attachment_to');?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sfmf_send_csv_attachment_to">Send Emails To</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="sfmf_send_csv_attachment_to"
                            name="sfmf_send_csv_attachment_to"
                            value="<?php echo esc_attr($value); ?>"
                            class="regular-text"
                        />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        </div><?php
        if (isset($_REQUEST["debug"])) { ?><pre><?php
        $form = GFAPI::get_form(self::FORM_ID);
            echo "DEBUG: fields\n";
            var_dump($form['fields']);
            echo "DEBUG: entries[0]\n";
            var_dump($entries[0]);
        }?></pre><?php
    }

    public function export_volunteers_handler()
    {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Unauthorized', 403);
        }
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"".  $this->get_csv_filename() ."\"");
        $this->write_csv('php://output');
        wp_die();
    }

    public function get_csv_filename()
    {
        return "volunteer-export-" . date('Y-m-d') . ".csv";
    }

    public function write_csv($output_str)
    {
        $output = fopen($output_str, 'w');
        $rows = $this->get_rows();
        $headers = array_keys($rows[0]);
        fputcsv($output, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($output, $row, ',', '"', '\\');
        }
        fclose($output);
    }

    public function send_csv_attachment()
    {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $this->get_csv_filename();
        $to = get_option('sfmf_send_csv_attachment_to');
        if (! $to) {
            return;
        }
        $this->write_csv($file_path);
        wp_mail(
            $to,
            'Volunteer Export CSV',
            'Please see the attached export.',
            [],
            [ $file_path ]
        );
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }

    public function get_rows()
    {
        $form = GFAPI::get_form(self::FORM_ID);
        $entries = $this->get_entries();
        $rows = [];

        usort($entries, fn ($a, $b) => $a['date_created'] <=> $b['date_created']);
        foreach ($entries as $entry) {
            $row = [];
            $row["Created At"] = $entry['date_created'];
            foreach ($form['fields'] as $field) {
                if ($field->visibility === 'administrative') {
                    continue;
                }
                if (in_array($field->type, ['html', 'section', 'page'])) {
                    continue;
                }
                $field_id = (string) $field->id;

                if ($field->type === 'name') {

                    $first = rgar($entry, $field->id . '.3');
                    $last = rgar($entry, $field->id . '.6');

                    $row['First Name'] = $first;
                    $row['Last Name'] = $last;

                    continue;
                } elseif ($field->inputType === 'checkbox' && !empty($field->inputs)) {
                    $values = [];
                    foreach ($field->inputs as $input) {
                        $input_value = rgar($entry, (string) $input['id']);
                        if ($input_value !== '') {
                            $values[] = $input_value;
                        }
                    }
                    $value = implode(', ', $values);

                } else {
                    $value = rgar($entry, $field_id);
                }

                $row[$field->label] = $value;
            }
            $rows[] = $row;
        }
        return $rows;
    }
}

$export = new SFMF_Export();
register_activation_hook(__FILE__, [$export, 'activate']);
register_deactivation_hook(__FILE__, [$export, 'deactivate']);

add_action('plugins_loaded', [$export, 'init']);
